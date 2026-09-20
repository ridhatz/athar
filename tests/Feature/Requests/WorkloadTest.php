<?php

use App\Actions\Requests\AssignRequest;
use App\Actions\Requests\ChangeRequestStatus;
use App\Actions\Requests\DecideReview;
use App\Enums\RequestStatus;
use App\Enums\ReviewDecision;
use App\Models\RequestCategory;
use App\Queries\WorkloadBoard;
use Inertia\Testing\AssertableInertia as Assert;
use Laravel\Sanctum\Sanctum;

/** Give $count open tasks to a person, in the given category. */
function giveTasks(object $c, $person, int $count, ?RequestCategory $category = null, string $title = 'Secret plans'): void
{
    for ($i = 0; $i < $count; $i++) {
        $request = makeRequest($c->requester, $category ?? $c->category, ['title' => $title]);
        app(AssignRequest::class)->handle($c->admin, $request, ['team_id' => $c->creative->id, 'user_id' => $person->id]);
    }
}

function boardRow(array $board, string $name): array
{
    return collect($board)->firstWhere('name', $name);
}

it('turns a task count into green, yellow, orange or red', function (int $tasks, string $colour) {
    expect(app(WorkloadBoard::class)->level($tasks))->toBe($colour);
})->with([
    [0, 'green'], [2, 'green'],
    [3, 'yellow'], [4, 'yellow'],
    [5, 'orange'], [6, 'orange'],
    [7, 'red'], [12, 'red'],   // "more than 6 is red"
]);

it('shows who is working on what, busiest first, by category', function () {
    $c = cast();
    $website = RequestCategory::factory()->create(['name_en' => 'Website', 'name_id' => 'Website']);
    giveTasks($c, $c->ahmad, 2, $website);
    giveTasks($c, $c->ahmad, 1);              // Design
    giveTasks($c, $c->budi, 1);

    $board = app(WorkloadBoard::class)->board();

    expect($board[0])->toMatchArray(['rank' => 1, 'name' => 'Ahmad', 'open' => 3, 'level' => 'yellow', 'teams' => ['Creative']])
        ->and($board[0]['working_on'])->toBe([['category' => 'Website', 'count' => 2], ['category' => 'Design', 'count' => 1]])
        ->and($board[1])->toMatchArray(['rank' => 2, 'name' => 'Budi', 'open' => 1, 'level' => 'green']);
});

it('lists only people who do work, and only active ones', function () {
    $c = cast();
    $c->dewi->forceFill(['is_active' => false])->save();

    $names = array_column(app(WorkloadBoard::class)->board(), 'name');

    expect($names)->toContain('Ahmad', 'Budi')
        ->not->toContain('Dewi')                  // deactivated
        ->not->toContain($c->requester->name)     // requesters do not do work
        ->not->toContain($c->management->name)
        ->not->toContain($c->admin->name);
});

it('does not count finished work as load, and counts recent completions', function () {
    $c = cast();
    giveTasks($c, $c->ahmad, 1);
    $done = makeRequest($c->requester, $c->category);
    app(AssignRequest::class)->handle($c->admin, $done, ['team_id' => $c->creative->id, 'user_id' => $c->ahmad->id]);
    app(ChangeRequestStatus::class)->handle($c->ahmad, $done, RequestStatus::InProgress);
    app(ChangeRequestStatus::class)->handle($c->ahmad, $done, RequestStatus::Review);
    app(DecideReview::class)->handle($c->creativeLead, $done, ReviewDecision::Approved);

    $row = boardRow(app(WorkloadBoard::class)->board(), 'Ahmad');

    expect($row['open'])->toBe(1)->and($row['completed_30d'])->toBe(1);
});

it('is red above six tasks, yet more work can still be assigned', function () {
    $c = cast();
    giveTasks($c, $c->ahmad, 7);
    expect(boardRow(app(WorkloadBoard::class)->board(), 'Ahmad'))->toMatchArray(['open' => 7, 'level' => 'red']);

    // Red is information for the person assigning. It is not a limit.
    $eighth = makeRequest($c->requester, $c->category);
    $assigned = app(AssignRequest::class)->handle($c->admin, $eighth, ['team_id' => $c->creative->id, 'user_id' => $c->ahmad->id]);

    expect($assigned->assigned_user_id)->toBe($c->ahmad->id)
        ->and(boardRow(app(WorkloadBoard::class)->board(), 'Ahmad')['open'])->toBe(8);
});

it('lets people keep requesting work from someone who is already red', function () {
    $c = cast();
    giveTasks($c, $c->ahmad, 7);
    Sanctum::actingAs($c->requester);

    $this->postJson('/api/v1/requests', requestPayload($c->category))->assertCreated();
});

it('shows every signed-in person the board, without leaking what the requests are', function (string $who) {
    $c = cast();
    giveTasks($c, $c->ahmad, 2, null, 'Confidential merger poster');
    Sanctum::actingAs($c->{$who});

    $response = $this->getJson('/api/v1/workload')->assertOk();

    expect($response->json('data.0.name'))->toBe('Ahmad')
        ->and($response->json('data.0.open'))->toBe(2)
        ->and($response->json('thresholds.red_from'))->toBe(7)
        ->and($response->getContent())->not->toContain('Confidential')->not->toContain('ATH-');
})->with(['requester', 'otherRequester', 'management', 'ahmad', 'admin']);

it('needs a signed-in user', function () {
    $this->getJson('/api/v1/workload')->assertUnauthorized();
    $this->get('/workload')->assertRedirect('/login');
});

it('renders the board page for anyone', function () {
    $c = cast();
    giveTasks($c, $c->ahmad, 1);

    $this->actingAs($c->requester)->get('/workload')->assertInertia(fn (Assert $page) => $page
        ->component('workload')
        ->where('board.0.name', 'Ahmad')
        ->where('thresholds.red_from', 7)
    );
});

it('shows the assigner each persons load in the picker', function () {
    $c = cast();
    giveTasks($c, $c->ahmad, 7);
    $request = makeRequest($c->requester, $c->category);

    $this->actingAs($c->admin)->get("/requests/{$request->request_number}")->assertInertia(fn (Assert $page) => $page
        ->where('assignables.users', fn ($users) => collect($users)->firstWhere('name', 'Ahmad')['level'] === 'red'
            && collect($users)->firstWhere('name', 'Ahmad')['open'] === 7
            && collect($users)->firstWhere('name', 'Budi')['level'] === 'green')
    );
});
