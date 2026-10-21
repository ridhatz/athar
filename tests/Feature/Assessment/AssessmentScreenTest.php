<?php

use App\Models\Assessment;
use App\Support\AssessmentQuestions;
use Inertia\Testing\AssertableInertia as Assert;

beforeEach(function () {
    $this->c = cast();
});

/** A full, valid 24-answer payload: "most" on column 0 (D), "least" on column 2 (S) throughout. */
function validAnswers(): array
{
    return array_fill(0, AssessmentQuestions::COUNT, ['most' => 0, 'least' => 2]);
}

describe('who may open the screen', function () {
    it('lets any active, signed-in person in — requester, staff, management alike', function () {
        foreach (['requester', 'ahmad', 'management', 'admin'] as $who) {
            $this->actingAs($this->c->{$who})->get('/assessment')->assertOk();
        }
    });

    it('sends the question bank every time', function () {
        $this->actingAs($this->c->ahmad)->get('/assessment')->assertInertia(
            fn (Assert $page) => $page->component('assessment/index')->has('questions', AssessmentQuestions::COUNT)
        );
    });
});

describe('taking it', function () {
    it('records a submission and scores it', function () {
        $this->actingAs($this->c->ahmad)->post('/assessment', ['answers' => validAnswers()])->assertRedirect();

        $assessment = Assessment::query()->where('user_id', $this->c->ahmad->id)->sole();

        // most=D(24x), least=S(24x): D net 24, S net -24, I and C untouched.
        expect($assessment->score_d)->toBe(24)
            ->and($assessment->score_s)->toBe(-24)
            ->and($assessment->score_i)->toBe(0)
            ->and($assessment->score_c)->toBe(0)
            ->and($assessment->dominant_style)->toBe('D');
    });

    it('refuses anything other than exactly 24 answers', function () {
        $short = array_slice(validAnswers(), 0, 10);

        $this->actingAs($this->c->ahmad)->post('/assessment', ['answers' => $short])
            ->assertSessionHasErrors('answers');

        expect(Assessment::query()->count())->toBe(0);
    });

    it('refuses a column index outside 0-3 at the HTTP boundary', function () {
        $answers = validAnswers();
        $answers[0] = ['most' => 9, 'least' => 1];

        $this->actingAs($this->c->ahmad)->post('/assessment', ['answers' => $answers])
            ->assertSessionHasErrors();

        expect(Assessment::query()->count())->toBe(0);
    });

    it('refuses most and least being the same column, via the domain rule', function () {
        $answers = validAnswers();
        $answers[0] = ['most' => 1, 'least' => 1];

        // Valid shape per the FormRequest (both 0-3), but AssessmentScoring's own rule catches it.
        $this->actingAs($this->c->ahmad)->post('/assessment', ['answers' => $answers])
            ->assertSessionHasErrors();

        expect(Assessment::query()->count())->toBe(0);
    });

    it('lets a retake add a new row rather than overwrite the old one', function () {
        $this->actingAs($this->c->ahmad)->post('/assessment', ['answers' => validAnswers()]);

        $different = array_fill(0, AssessmentQuestions::COUNT, ['most' => 3, 'least' => 0]);
        $this->actingAs($this->c->ahmad)->post('/assessment', ['answers' => $different]);

        expect(Assessment::query()->where('user_id', $this->c->ahmad->id)->count())->toBe(2);

        $this->actingAs($this->c->ahmad)->get('/assessment')->assertInertia(
            fn (Assert $page) => $page->where('myLatest.scores.C', 24)
        );
    });
});

describe('what the screen shows each audience', function () {
    it('never sends the admin roster to a non-admin', function () {
        $this->actingAs($this->c->ahmad)->get('/assessment')->assertInertia(
            fn (Assert $page) => $page->where('canViewAll', false)->where('all', null)->where('stats', null)
        );
    });

    it('sends every submitted result to an admin, including other people\'s', function () {
        $this->actingAs($this->c->ahmad)->post('/assessment', ['answers' => validAnswers()]);
        $this->actingAs($this->c->budi)->post('/assessment', ['answers' => validAnswers()]);

        $this->actingAs($this->c->admin)->get('/assessment')->assertInertia(
            fn (Assert $page) => $page
                ->where('canViewAll', true)
                ->has('all.data', 2)
                ->where('stats.submitted', 2)
        );
    });

    it('counts a retake once, not twice, toward how many people have submitted', function () {
        $this->actingAs($this->c->ahmad)->post('/assessment', ['answers' => validAnswers()]);
        $this->actingAs($this->c->ahmad)->post('/assessment', ['answers' => validAnswers()]);

        $this->actingAs($this->c->admin)->get('/assessment')->assertInertia(
            fn (Assert $page) => $page->has('all.data', 1)->where('stats.submitted', 1)
        );
    });

    it('shows the roster the latest row per person, not an old one', function () {
        $this->actingAs($this->c->ahmad)->post('/assessment', ['answers' => validAnswers()]);
        $newer = array_fill(0, AssessmentQuestions::COUNT, ['most' => 1, 'least' => 2]);
        $this->actingAs($this->c->ahmad)->post('/assessment', ['answers' => $newer]);

        $this->actingAs($this->c->admin)->get('/assessment')->assertInertia(
            fn (Assert $page) => $page->where('all.data.0.scores.I', 24)
        );
    });
});
