<?php

use App\Actions\Requests\AssignRequest;
use App\Actions\Requests\ChangeRequestStatus;
use App\Actions\Requests\DecideReview;
use App\Enums\RequestStatus;
use App\Enums\ReviewDecision;
use App\Exceptions\ImmutableRecordException;
use App\Models\ActivityLog;
use App\Models\Review;
use App\Models\ServiceRequest;
use Illuminate\Auth\Access\AuthorizationException;
use Laravel\Sanctum\Sanctum;

function inReview(object $c): ServiceRequest
{
    $request = assignedRequest($c);
    $change = app(ChangeRequestStatus::class);
    $change->handle($c->ahmad, $request, RequestStatus::InProgress);
    $change->handle($c->ahmad, $request, RequestStatus::Review);

    return $request->fresh();
}

function reviewUrl(object $request): string
{
    return "/api/v1/requests/{$request->request_number}/review";
}

it('opens a review round when work is sent to review', function () {
    $c = cast();
    $request = inReview($c);

    $review = Review::query()->sole();
    expect($review->round)->toBe(1)
        ->and($review->decision)->toBe(ReviewDecision::Pending)
        ->and($review->requested_by)->toBe($c->ahmad->id)
        ->and($review->reviewer_id)->toBeNull();

    expect(ActivityLog::query()->where('request_id', $request->id)->where('action', 'REVIEW_REQUESTED')->value('new_value'))->toBe('1');
});

it('completes the request when the reviewer approves', function () {
    $c = cast();
    $request = inReview($c);
    Sanctum::actingAs($c->creativeLead);

    $this->postJson(reviewUrl($request), ['decision' => 'APPROVED', 'note' => 'Great work'])
        ->assertOk()->assertJsonPath('data.status', 'COMPLETED');

    $fresh = $request->fresh();
    $review = Review::query()->sole();
    expect($fresh->status)->toBe(RequestStatus::Completed)
        ->and($fresh->completed_at)->not->toBeNull()
        ->and($review->decision)->toBe(ReviewDecision::Approved)
        ->and($review->reviewer_id)->toBe($c->creativeLead->id)
        ->and($review->decision_note)->toBe('Great work')
        ->and($review->decided_at)->not->toBeNull();

    $actions = ActivityLog::query()->where('request_id', $request->id)->orderBy('id')->pluck('action')->map->value->all();
    expect(array_slice($actions, -3))->toBe(['REVIEW_REQUESTED', 'REVIEW_APPROVED', 'STATUS_CHANGED']);
});

it('sends work back for revision, and keeps every round on record', function () {
    $c = cast();
    $request = inReview($c);
    $change = app(ChangeRequestStatus::class);

    Sanctum::actingAs($c->creativeLead);
    $this->postJson(reviewUrl($request), ['decision' => 'REVISION_REQUESTED', 'note' => 'Make the logo bigger'])
        ->assertOk()->assertJsonPath('data.status', 'IN_PROGRESS');

    // The PIC fixes it and submits again: a NEW round, the old one untouched.
    $change->handle($c->ahmad, $request, RequestStatus::Review);
    $this->postJson(reviewUrl($request), ['decision' => 'APPROVED'])->assertOk()->assertJsonPath('data.status', 'COMPLETED');

    $rounds = Review::query()->orderBy('round')->get();
    expect($rounds->pluck('round')->all())->toBe([1, 2])
        ->and($rounds->pluck('decision')->map->value->all())->toBe(['REVISION_REQUESTED', 'APPROVED'])
        ->and($rounds[0]->decision_note)->toBe('Make the logo bigger');

    Sanctum::actingAs($c->requester);
    $listed = $this->getJson("/api/v1/requests/{$request->request_number}/reviews")->assertOk()->json('data');
    expect(array_column($listed, 'round'))->toBe([1, 2])
        ->and($listed[0]['decision_note'])->toBe('Make the logo bigger')
        ->and($listed[0]['reviewer']['name'])->toBe($c->creativeLead->name);
});

it('requires a reason when asking for a revision', function () {
    $c = cast();
    $request = inReview($c);
    Sanctum::actingAs($c->creativeLead);

    foreach ([null, '', '   '] as $blank) {
        $this->postJson(reviewUrl($request), ['decision' => 'REVISION_REQUESTED', 'note' => $blank])
            ->assertUnprocessable()->assertJsonValidationErrors('note');
    }

    expect($request->fresh()->status)->toBe(RequestStatus::Review)->and(Review::query()->sole()->decision)->toBe(ReviewDecision::Pending);
});

it('does not let anyone sign off their own work', function () {
    $c = cast();
    $request = inReview($c);

    // The PIC, even though a team lead in general could.
    Sanctum::actingAs($c->ahmad);
    $this->postJson(reviewUrl($request), ['decision' => 'APPROVED'])->assertForbidden();

    // A team lead who is the PIC cannot approve either.
    $lead = $c->creativeLead;
    $ownRequest = makeRequest($c->requester, $c->category);
    app(AssignRequest::class)->handle($c->admin, $ownRequest, ['team_id' => $c->creative->id, 'user_id' => $lead->id]);
    app(ChangeRequestStatus::class)->handle($lead, $ownRequest, RequestStatus::InProgress);
    app(ChangeRequestStatus::class)->handle($lead, $ownRequest, RequestStatus::Review);
    Sanctum::actingAs($lead);
    $this->postJson(reviewUrl($ownRequest), ['decision' => 'APPROVED'])->assertForbidden();

    // ...but an admin can review it.
    Sanctum::actingAs($c->admin);
    $this->postJson(reviewUrl($ownRequest), ['decision' => 'APPROVED'])->assertOk();
});

it('lets only a manager of the team review', function (string $who, int $status) {
    $c = cast();
    $request = inReview($c);
    Sanctum::actingAs($c->{$who});

    $this->postJson(reviewUrl($request), ['decision' => 'APPROVED'])->assertStatus($status);
    expect($request->fresh()->status)->toBe($status === 200 ? RequestStatus::Completed : RequestStatus::Review);
})->with([
    'admin' => ['admin', 200],
    'team lead of the team' => ['creativeLead', 200],
    'team lead of another team' => ['itLead', 404],  // cannot even see it
    'management' => ['management', 403],
    'the requester' => ['requester', 403],
    'a teammate' => ['budi', 403],
]);

it('only decides work that is actually in review, and only once', function () {
    $c = cast();
    $notYet = assignedRequest($c);

    expectValidationError(fn () => app(DecideReview::class)->handle($c->admin, $notYet, ReviewDecision::Approved), 'decision', 'not waiting');

    $request = inReview($c);
    app(DecideReview::class)->handle($c->creativeLead, $request, ReviewDecision::Approved);
    expectValidationError(fn () => app(DecideReview::class)->handle($c->creativeLead, $request, ReviewDecision::Approved), 'decision');
});

it('rejects an unknown decision', function () {
    $c = cast();
    $request = inReview($c);
    Sanctum::actingAs($c->creativeLead);

    $this->postJson(reviewUrl($request), ['decision' => 'PENDING'])->assertUnprocessable()->assertJsonValidationErrors('decision');
    $this->postJson(reviewUrl($request), ['decision' => 'MAYBE'])->assertUnprocessable();
});

it('cannot change a decision once made', function () {
    $c = cast();
    $request = inReview($c);
    app(DecideReview::class)->handle($c->creativeLead, $request, ReviewDecision::Approved);

    $review = Review::query()->sole();
    $review->decision = ReviewDecision::RevisionRequested;
    expect(fn () => $review->save())->toThrow(ImmutableRecordException::class);
    expect(fn () => $review->delete())->toThrow(ImmutableRecordException::class);
});

it('keeps the review pending while a request is blocked, and resumes it', function () {
    $c = cast();
    $request = inReview($c);
    $change = app(ChangeRequestStatus::class);

    $change->handle($c->ahmad, $request, RequestStatus::Blocked, 'waiting for the client');
    $change->handle($c->ahmad, $request, RequestStatus::Review);

    expect(Review::query()->count())->toBe(1)->and(Review::query()->sole()->decision)->toBe(ReviewDecision::Pending);
});

it('tells the client whether the caller may decide', function () {
    $c = cast();
    $request = inReview($c);

    foreach (['creativeLead' => true, 'ahmad' => false, 'requester' => false] as $who => $expected) {
        Sanctum::actingAs($c->{$who});
        $this->getJson("/api/v1/requests/{$request->request_number}")->assertJsonPath('data.permissions.review_decision', $expected);
    }
});

it('still refuses to complete through the generic status endpoint', function () {
    $c = cast();
    $request = inReview($c);
    Sanctum::actingAs($c->admin);

    $this->postJson("/api/v1/requests/{$request->request_number}/status", ['to_status' => 'COMPLETED'])->assertUnprocessable();
    expect(fn () => app(DecideReview::class)->handle($c->ahmad, $request, ReviewDecision::Approved))->toThrow(AuthorizationException::class);
});
