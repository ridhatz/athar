<?php

use App\Enums\AssessmentRecommendation;
use App\Services\AssessmentScoring;
use App\Support\AssessmentQuestions;
use Illuminate\Validation\ValidationException;

beforeEach(function () {
    $this->scoring = app(AssessmentScoring::class);
});

/** @return array<int, array{most: int, least: int}> */
function answersFromPicks(int $most, int $least): array
{
    return array_fill(0, AssessmentQuestions::COUNT, ['most' => $most, 'least' => $least]);
}

describe('net score per dimension', function () {
    it('gives a dimension chosen "most" every time its maximum net score', function () {
        $result = $this->scoring->score(answersFromPicks(most: 0, least: 1));

        // Picked most on all 24, least never: net = 24 - 0.
        expect($result['score_d'])->toBe(24)
            // Picked least on all 24, most never: net = 0 - 24.
            ->and($result['score_i'])->toBe(-24)
            // Never picked either way.
            ->and($result['score_s'])->toBe(0)
            ->and($result['score_c'])->toBe(0);
    });

    it('always sums the four nets to zero, because every item hands out one +1 and one -1', function () {
        // An arbitrary, not-uniform set of answers.
        $answers = [];
        for ($i = 0; $i < AssessmentQuestions::COUNT; $i++) {
            $most = $i % 4;
            $least = ($i + 1) % 4;
            $answers[] = ['most' => $most, 'least' => $least];
        }

        $result = $this->scoring->score($answers);

        expect($result['score_d'] + $result['score_i'] + $result['score_s'] + $result['score_c'])->toBe(0);
    });

    it('names the highest-scoring dimension as the dominant style', function () {
        $result = $this->scoring->score(answersFromPicks(most: 2, least: 0));

        expect($result['dominant_style'])->toBe('S');
    });
});

describe('the Sales / Marketing / Hybrid call', function () {
    it('calls Sales when Dominance and Influence clearly lead', function () {
        // D "most" every time, C "least" every time: sales_index (D+I) = 24, marketing_index (I+C) = -24.
        $result = $this->scoring->score(answersFromPicks(most: 0, least: 3));

        expect($result['sales_index'])->toBe(24)
            ->and($result['marketing_index'])->toBe(-24)
            ->and($result['recommendation'])->toBe(AssessmentRecommendation::Sales);
    });

    it('calls Marketing when Conscientiousness and Influence clearly lead', function () {
        $result = $this->scoring->score(answersFromPicks(most: 3, least: 0));

        expect($result['sales_index'])->toBe(-24)
            ->and($result['marketing_index'])->toBe(24)
            ->and($result['recommendation'])->toBe(AssessmentRecommendation::Marketing);
    });

    it('calls Hybrid when the two indexes land exactly equal', function () {
        $half = intdiv(AssessmentQuestions::COUNT, 2);
        $answers = [
            ...array_fill(0, $half, ['most' => 0, 'least' => 1]),
            ...array_fill(0, AssessmentQuestions::COUNT - $half, ['most' => 3, 'least' => 1]),
        ];

        $result = $this->scoring->score($answers);

        expect($result['sales_index'])->toBe($result['marketing_index'])
            ->and($result['recommendation'])->toBe(AssessmentRecommendation::Hybrid);
    });

    it('respects a configured margin rather than a hardcoded one', function () {
        config(['athar.assessment.role_fit_margin' => 100]);

        // The same clearly-Sales answers as above, but no diff could ever clear a margin of 100.
        $result = $this->scoring->score(answersFromPicks(most: 0, least: 3));

        expect($result['recommendation'])->toBe(AssessmentRecommendation::Hybrid);
    });
});

describe('validation', function () {
    it('refuses anything other than exactly 24 answers', function () {
        expect(fn () => $this->scoring->score(array_fill(0, 23, ['most' => 0, 'least' => 1])))
            ->toThrow(ValidationException::class);
    });

    it('refuses an item whose most and least are the same column', function () {
        $answers = answersFromPicks(most: 0, least: 1);
        $answers[5] = ['most' => 2, 'least' => 2];

        expect(fn () => $this->scoring->score($answers))->toThrow(ValidationException::class);
    });

    it('refuses a column index outside 0-3', function () {
        $answers = answersFromPicks(most: 0, least: 1);
        $answers[0] = ['most' => 4, 'least' => 1];

        expect(fn () => $this->scoring->score($answers))->toThrow(ValidationException::class);
    });
});
