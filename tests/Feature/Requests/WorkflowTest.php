<?php

use App\Actions\Requests\AssignRequest;
use App\Actions\Requests\ChangeRequestStatus;
use App\Enums\ActivityAction;
use App\Enums\RequestStatus;
use App\Models\ActivityLog;
use App\Models\ServiceRequest;
use Illuminate\Validation\ValidationException;
use Laravel\Sanctum\Sanctum;

/**
 * A request in a chosen state, already owned by Creative / Ahmad, built directly
 * so each test can start exactly where it needs to.
 */
function requestIn(object $c, RequestStatus $status, ?RequestStatus $blockedFrom = null, bool $withPic = true): ServiceRequest
{
    return ServiceRequest::factory()->create([
        'requester_id' => $c->requester->id,
        'department_id' => $c->department->id,
        'category_id' => $c->category->id,
        'status' => $status,
        'blocked_from_status' => $blockedFrom,
        'assigned_team_id' => $c->creative->id,
        'assigned_user_id' => $withPic ? $c->ahmad->id : null,
    ]);
}

/*
|--------------------------------------------------------------------------
| The transition table, written out by hand from the specification.
| Deliberately NOT derived from the code under test.
| "Reserved" moves (TRIAGED→ASSIGNED, REVIEW→COMPLETED, REVIEW→IN_PROGRESS)
| are valid in the workflow but belong to the assign / review use-cases, so the
| generic status endpoint refuses them.
|--------------------------------------------------------------------------
*/
$allowedViaStatusEndpoint = [
    'NEW' => ['TRIAGED', 'CANCELLED'],
    'TRIAGED' => ['CANCELLED'],
    'ASSIGNED' => ['IN_PROGRESS', 'BLOCKED', 'CANCELLED'],
    'IN_PROGRESS' => ['REVIEW', 'BLOCKED', 'CANCELLED'],
    'REVIEW' => ['BLOCKED', 'CANCELLED'],
    'BLOCKED' => ['ASSIGNED', 'CANCELLED'], // blocked from ASSIGNED in this matrix
    'COMPLETED' => [],
    'CANCELLED' => [],
];

$everyPair = [];
foreach (RequestStatus::cases() as $from) {
    foreach (RequestStatus::cases() as $to) {
        $everyPair["{$from->value} → {$to->value}"] = [$from, $to, in_array($to->value, $allowedViaStatusEndpoint[$from->value], true)];
    }
}

it('permits exactly the specified transitions, and nothing else', function (RequestStatus $from, RequestStatus $to, bool $shouldWork) {
    $c = cast();
    $request = requestIn($c, $from, $from === RequestStatus::Blocked ? RequestStatus::Assigned : null);

    $move = fn () => app(ChangeRequestStatus::class)->handle($c->admin, $request, $to, 'reason given');

    if ($shouldWork) {
        expect($move()->status)->toBe($to);
    } else {
        expect($move)->toThrow(ValidationException::class);
        expect($request->fresh()->status)->toBe($from);
    }
})->with($everyPair);

it('walks the whole happy path NEW to REVIEW, logging every step', function () {
    $c = cast();
    $request = makeRequest($c->requester, $c->category);
    $change = app(ChangeRequestStatus::class);

    $change->handle($c->admin, $request, RequestStatus::Triaged);
    app(AssignRequest::class)->handle($c->admin, $request, ['team_id' => $c->creative->id, 'user_id' => $c->ahmad->id]);
    $change->handle($c->ahmad, $request, RequestStatus::InProgress);
    $change->handle($c->ahmad, $request, RequestStatus::Review);

    $statusLog = ActivityLog::query()->where('request_id', $request->id)
        ->where('action', ActivityAction::StatusChanged->value)->orderBy('id')->get()
        ->map(fn ($l) => "{$l->old_value}>{$l->new_value}")->all();

    expect($statusLog)->toBe(['NEW>TRIAGED', 'TRIAGED>ASSIGNED', 'ASSIGNED>IN_PROGRESS', 'IN_PROGRESS>REVIEW'])
        ->and($request->fresh()->status)->toBe(RequestStatus::Review);
});

it('will not start or review work that has nobody responsible', function (RequestStatus $from, RequestStatus $to) {
    $c = cast();
    $request = requestIn($c, $from, withPic: false);

    expectValidationError(
        fn () => app(ChangeRequestStatus::class)->handle($c->admin, $request, $to),
        'to_status',
        'person in charge',
    );

    expect($request->fresh()->status)->toBe($from);
})->with([
    'start' => [RequestStatus::Assigned, RequestStatus::InProgress],
    'review' => [RequestStatus::InProgress, RequestStatus::Review],
]);

it('requires a reason to block or cancel', function (RequestStatus $from, RequestStatus $to) {
    $c = cast();
    $request = requestIn($c, $from);

    foreach ([null, '', '   '] as $blank) {
        try {
            app(ChangeRequestStatus::class)->handle($c->admin, $request, $to, $blank);
            $this->fail('expected a validation error');
        } catch (ValidationException $e) {
            expect($e->errors())->toHaveKey('note');
        }
    }

    expect($request->fresh()->status)->toBe($from);
})->with([
    'block' => [RequestStatus::InProgress, RequestStatus::Blocked],
    'cancel' => [RequestStatus::Assigned, RequestStatus::Cancelled],
]);

it('remembers where a blocked request came from and resumes only there', function (RequestStatus $origin) {
    $c = cast();
    $request = requestIn($c, $origin);
    $change = app(ChangeRequestStatus::class);

    $blocked = $change->handle($c->ahmad, $request, RequestStatus::Blocked, 'waiting for the speaker photo');
    expect($blocked->status)->toBe(RequestStatus::Blocked)
        ->and($blocked->blocked_from_status)->toBe($origin);

    // It cannot jump to some other working state.
    $wrong = collect([RequestStatus::Assigned, RequestStatus::InProgress, RequestStatus::Review])
        ->first(fn ($s) => $s !== $origin);
    expect(fn () => $change->handle($c->ahmad, $request, $wrong))->toThrow(ValidationException::class);

    $resumed = $change->handle($c->ahmad, $request, $origin);
    expect($resumed->status)->toBe($origin)->and($resumed->blocked_from_status)->toBeNull();
})->with([RequestStatus::Assigned, RequestStatus::InProgress, RequestStatus::Review]);

it('stamps cancelled_at and keeps the full history of a cancelled request', function () {
    $c = cast();
    $request = assignedRequest($c);
    $before = ActivityLog::query()->where('request_id', $request->id)->count();

    $cancelled = app(ChangeRequestStatus::class)->handle($c->admin, $request, RequestStatus::Cancelled, 'event postponed');

    expect($cancelled->cancelled_at)->not->toBeNull()
        ->and($cancelled->completed_at)->toBeNull()
        ->and(ActivityLog::query()->where('request_id', $request->id)->count())->toBe($before + 1);

    $last = ActivityLog::query()->where('request_id', $request->id)->latest('id')->first();
    expect($last->old_value)->toBe('ASSIGNED')->and($last->new_value)->toBe('CANCELLED')->and($last->note)->toBe('event postponed');
});

it('refuses to touch a finished request with a clear message', function (RequestStatus $terminal) {
    $c = cast();
    $request = requestIn($c, $terminal);

    expectValidationError(
        fn () => app(ChangeRequestStatus::class)->handle($c->admin, $request, RequestStatus::InProgress),
        'to_status',
        'already completed or cancelled',
    );
})->with([RequestStatus::Completed, RequestStatus::Cancelled]);

it('points people to review, not the status endpoint, for approval', function () {
    $c = cast();
    $request = requestIn($c, RequestStatus::Review);

    Sanctum::actingAs($c->creativeLead);
    $this->postJson("/api/v1/requests/{$request->request_number}/status", ['to_status' => 'COMPLETED'])
        ->assertUnprocessable()
        ->assertJsonValidationErrors('to_status');

    expect($request->fresh()->status)->toBe(RequestStatus::Review);
});

it('rejects an unknown status value', function () {
    $c = cast();
    $request = requestIn($c, RequestStatus::Assigned);

    Sanctum::actingAs($c->admin);
    $this->postJson("/api/v1/requests/{$request->id}/status", ['to_status' => 'DONE_ISH'])
        ->assertUnprocessable()
        ->assertJsonValidationErrors('to_status');
});

it('tells the client which moves the caller may make right now', function () {
    $c = cast();
    $request = requestIn($c, RequestStatus::Assigned);

    Sanctum::actingAs($c->ahmad);
    $moves = collect($this->getJson("/api/v1/requests/{$request->request_number}")
        ->assertOk()->json('data.allowed_transitions'))->keyBy('status');

    // The PIC can start or block the work. Cancelling is for the requester and managers, not the PIC.
    expect($moves->keys()->sort()->values()->all())->toBe(['BLOCKED', 'IN_PROGRESS'])
        ->and($moves['BLOCKED']['requires_note'])->toBeTrue()
        ->and($moves['IN_PROGRESS']['requires_note'])->toBeFalse();

    // A requester on the same request only gets what they may actually do: withdraw it.
    Sanctum::actingAs($c->requester);
    $theirs = $this->getJson("/api/v1/requests/{$request->request_number}")->json('data.allowed_transitions');
    expect(array_column($theirs, 'status'))->toBe(['CANCELLED']);
});
