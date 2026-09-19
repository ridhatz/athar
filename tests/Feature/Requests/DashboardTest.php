<?php

use App\Actions\Requests\AssignRequest;
use App\Actions\Requests\ChangeRequestStatus;
use App\Actions\Requests\DecideReview;
use App\Enums\RequestStatus;
use App\Enums\ReviewDecision;
use App\Models\Team;
use Illuminate\Support\Carbon;
use Inertia\Testing\AssertableInertia as Assert;
use Laravel\Sanctum\Sanctum;

/**
 * A busy little organisation:
 *   r1  Sari's, unrouted, NEW                      (the triage queue)
 *   r2  Sari's, Creative/Ahmad, ASSIGNED
 *   r3  Sari's, Creative/Ahmad, IN_PROGRESS, overdue
 *   r4  Sari's, Creative/Ahmad, REVIEW
 *   r5  Hendra's, Creative/Budi, BLOCKED
 *   r6  Hendra's, IT/Dewi, COMPLETED
 */
function busyOffice(): object
{
    $c = cast();
    $assign = app(AssignRequest::class);
    $change = app(ChangeRequestStatus::class);

    $c->r1 = makeRequest($c->requester, $c->category, ['title' => 'r1']);

    $c->r2 = makeRequest($c->requester, $c->category, ['title' => 'r2']);
    $assign->handle($c->admin, $c->r2, ['team_id' => $c->creative->id, 'user_id' => $c->ahmad->id]);

    $c->r3 = makeRequest($c->requester, $c->category, ['title' => 'r3']);
    $assign->handle($c->admin, $c->r3, ['team_id' => $c->creative->id, 'user_id' => $c->ahmad->id]);
    $change->handle($c->ahmad, $c->r3, RequestStatus::InProgress);
    $c->r3->forceFill(['deadline' => now()->subDay()])->save();

    $c->r4 = makeRequest($c->requester, $c->category, ['title' => 'r4']);
    $assign->handle($c->admin, $c->r4, ['team_id' => $c->creative->id, 'user_id' => $c->ahmad->id]);
    $change->handle($c->ahmad, $c->r4, RequestStatus::InProgress);
    $change->handle($c->ahmad, $c->r4, RequestStatus::Review);

    $c->r5 = makeRequest($c->otherRequester, $c->category, ['title' => 'r5']);
    $assign->handle($c->admin, $c->r5, ['team_id' => $c->creative->id, 'user_id' => $c->budi->id]);
    $change->handle($c->budi, $c->r5, RequestStatus::Blocked, 'waiting');

    $c->r6 = makeRequest($c->otherRequester, $c->category, ['title' => 'r6']);
    $assign->handle($c->admin, $c->r6, ['team_id' => $c->it->id, 'user_id' => $c->dewi->id]);
    $change->handle($c->dewi, $c->r6, RequestStatus::InProgress);
    $change->handle($c->dewi, $c->r6, RequestStatus::Review);
    app(DecideReview::class)->handle($c->itLead, $c->r6, ReviewDecision::Approved);

    return $c;
}

function cardsOf(string $perspective): array
{
    $json = test()->getJson("/api/v1/dashboards/{$perspective}")->assertOk()->json('data');

    return collect($json['cards'])->keyBy('key')->map(fn ($card) => $card['count'])->all();
}

it('offers each role exactly the dashboards it should have', function (string $who, array $expected) {
    $c = cast();
    $this->actingAs($c->{$who})->get('/dashboard')->assertInertia(fn (Assert $page) => $page
        ->component('dashboard')
        ->where('perspectives', $expected)
    );
})->with([
    'requester' => ['requester', ['requester']],
    'staff' => ['ahmad', ['requester', 'staff']],
    'team lead' => ['creativeLead', ['requester', 'staff']],
    'admin' => ['admin', ['requester', 'staff', 'management']],
    'management' => ['management', ['management']],
]);

it('refuses a dashboard the role does not have', function (string $who, string $perspective) {
    $c = cast();
    Sanctum::actingAs($c->{$who});

    $this->getJson("/api/v1/dashboards/{$perspective}")->assertForbidden();
})->with([
    ['requester', 'staff'],
    ['requester', 'management'],
    ['ahmad', 'management'],
    ['creativeLead', 'management'],
    ['management', 'requester'],
    ['management', 'staff'],
]);

it('rejects an unknown dashboard', function () {
    $c = cast();
    Sanctum::actingAs($c->admin);

    $this->getJson('/api/v1/dashboards/secrets')->assertNotFound();
});

it('counts the requester dashboard from their own requests only', function () {
    $c = busyOffice();
    Sanctum::actingAs($c->requester);

    expect(cardsOf('requester'))->toBe([
        'my_requests' => 4, 'new' => 1, 'in_progress' => 1, 'waiting_review' => 1, 'completed' => 0, 'overdue' => 1,
    ]);

    Sanctum::actingAs($c->otherRequester);
    expect(cardsOf('requester'))->toBe([
        'my_requests' => 2, 'new' => 0, 'in_progress' => 0, 'waiting_review' => 0, 'completed' => 1, 'overdue' => 0,
    ]);
});

it('counts the staff dashboard from work in front of them', function () {
    $c = busyOffice();

    Sanctum::actingAs($c->ahmad);
    expect(cardsOf('staff'))->toBe([
        'assigned_to_me' => 3,     // r2, r3, r4 (open, his)
        'team_work' => 4,          // + Budi's blocked r5
        'due_today' => 0,
        'overdue' => 1,            // r3
        'blocked' => 1,            // r5, a teammate's, still the team's
        'awaiting_review' => 1,    // r4
    ]);

    Sanctum::actingAs($c->dewi);
    expect(cardsOf('staff'))->toBe([
        'assigned_to_me' => 0, 'team_work' => 0, 'due_today' => 0, 'overdue' => 0, 'blocked' => 0, 'awaiting_review' => 0,
    ]);
});

it('shows team leads and admins the triage queue, and only them', function () {
    $c = busyOffice();

    Sanctum::actingAs($c->creativeLead);
    expect(cardsOf('staff'))->toHaveKey('needs_triage')->and(cardsOf('staff')['needs_triage'])->toBe(1);

    Sanctum::actingAs($c->admin);
    expect(cardsOf('staff')['needs_triage'])->toBe(1);

    Sanctum::actingAs($c->ahmad);
    expect(cardsOf('staff'))->not->toHaveKey('needs_triage');
});

it('draws the management picture across the whole organisation', function () {
    $c = busyOffice();
    Sanctum::actingAs($c->management);

    $data = $this->getJson('/api/v1/dashboards/management')->assertOk()->json('data');
    $cards = collect($data['cards'])->pluck('count', 'key')->all();

    expect($cards)->toBe([
        'total' => 6, 'new' => 1, 'in_progress' => 1, 'review' => 1, 'completed' => 1, 'blocked' => 1, 'overdue' => 1,
    ]);

    $byTeam = collect($data['by_team'])->mapWithKeys(fn ($r) => [($r['name'] ?? 'none') => $r['count']])->all();
    expect($byTeam)->toEqual(['Creative' => 4, 'IT' => 1, 'none' => 1])
        ->and(array_sum(array_column($data['by_category'], 'count')))->toBe(6)
        ->and($data['by_department'][0]['name'])->toBe('Humas')
        ->and($data['by_department'][0]['count'])->toBe(6);
});

it('averages the time from creation to completion', function () {
    $c = busyOffice();
    $c->r6->forceFill(['created_at' => now()->subHours(6), 'completed_at' => now()])->save();
    Sanctum::actingAs($c->management);

    expect($this->getJson('/api/v1/dashboards/management')->json('data.avg_completion_hours'))->toEqual(6.0);
});

it('has no average until something has been completed', function () {
    $c = cast();
    makeRequest($c->requester, $c->category);
    Sanctum::actingAs($c->management);

    expect($this->getJson('/api/v1/dashboards/management')->json('data.avg_completion_hours'))->toBeNull();
});

it('lets every card be verified: its number is exactly the list it links to', function (string $who, string $perspective) {
    $c = busyOffice();
    $user = $c->{$who};
    Sanctum::actingAs($user);

    $cards = $this->getJson("/api/v1/dashboards/{$perspective}")->assertOk()->json('data.cards');
    expect($cards)->not->toBeEmpty();

    foreach ($cards as $card) {
        $total = $this->getJson('/api/v1/requests?'.http_build_query($card['filters'] + ['per_page' => 1]))
            ->assertOk()->json('meta.total');

        expect($total)->toBe($card['count'], "card [{$card['key']}] of {$who}/{$perspective} disagrees with its list");
    }
})->with([
    ['requester', 'requester'],
    ['otherRequester', 'requester'],
    ['ahmad', 'staff'],
    ['budi', 'staff'],
    ['dewi', 'staff'],
    ['creativeLead', 'staff'],
    ['admin', 'staff'],
    ['admin', 'management'],
    ['management', 'management'],
]);

it('counts "due today" by the calendar day in the display time zone', function () {
    $this->travelTo(Carbon::parse('2026-10-01 10:00:00', 'Asia/Jakarta'));
    $c = cast();

    $make = function (string $title, string $deadline) use ($c) {
        $r = makeRequest($c->requester, $c->category, ['title' => $title, 'deadline' => Carbon::parse('2026-10-05', 'Asia/Jakarta')]);
        app(AssignRequest::class)->handle($c->admin, $r, ['team_id' => $c->creative->id, 'user_id' => $c->ahmad->id]);
        $r->forceFill(['deadline' => Carbon::parse($deadline, 'Asia/Jakarta')->utc()])->save();
    };

    $make('this morning, already passed today', '2026-10-01 08:00'); // overdue AND today
    $make('tonight', '2026-10-01 23:30');                              // today
    $make('just after midnight', '2026-10-02 00:30');                  // tomorrow in Jakarta, though still 1 Oct in UTC
    $make('last night', '2026-09-30 23:30');                           // yesterday in Jakarta

    Sanctum::actingAs($c->ahmad);
    $cards = cardsOf('staff');

    expect($cards['due_today'])->toBe(2)->and($cards['overdue'])->toBe(2);
});

it('sends the front door and the old URL to the dashboard', function () {
    $c = cast();

    $this->get('/dashboard')->assertRedirect('/login');   // a guest first: actingAs would stay signed in for the rest of the test

    $this->actingAs($c->requester)->get('/')->assertRedirect('/dashboard');
    $this->actingAs($c->requester)->get('/dashboard')->assertOk();
});

it('sends the web page every perspective the person may open', function () {
    $c = busyOffice();

    $this->actingAs($c->admin)->get('/dashboard')->assertInertia(fn (Assert $page) => $page
        ->has('data.requester.cards', 6)
        ->has('data.staff.cards', 7)
        ->has('data.management.cards', 7)
        ->has('data.management.by_category')
    );

    $this->actingAs($c->requester)->get('/dashboard')->assertInertia(fn (Assert $page) => $page
        ->has('data.requester.cards', 6)
        ->missing('data.management')
        ->missing('data.staff')
    );
});
