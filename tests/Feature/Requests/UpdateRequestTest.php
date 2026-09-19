<?php

use App\Actions\Requests\ChangeRequestStatus;
use App\Enums\RequestStatus;
use App\Models\ActivityLog;
use App\Models\RequestCategory;
use Laravel\Sanctum\Sanctum;

function requestUrl(object $request): string
{
    return "/api/v1/requests/{$request->request_number}";
}

function changesOf(object $request): array
{
    return ActivityLog::query()->where('request_id', $request->id)->where('action', '!=', 'REQUEST_CREATED')
        ->orderBy('id')->get()->map(fn ($l) => [$l->action->value, $l->field, $l->old_value, $l->new_value])->all();
}

it('lets the requester correct their request before it is triaged, logging old and new values', function () {
    $c = cast();
    $request = makeRequest($c->requester, $c->category, ['title' => 'Poster', 'priority' => 'LOW']);
    $documentation = RequestCategory::factory()->create(['name_en' => 'Documentation']);
    $newDeadline = now()->addDays(10)->startOfSecond();

    Sanctum::actingAs($c->requester);
    $this->patchJson(requestUrl($request), [
        'title' => 'Poster seminar',
        'category_id' => $documentation->id,
        'priority' => 'HIGH',
        'deadline' => $newDeadline->toIso8601String(),
    ])->assertOk()->assertJsonPath('data.title', 'Poster seminar');

    $log = collect(changesOf($request))->keyBy(fn ($l) => $l[1]);
    expect($log['title'])->toBe(['REQUEST_UPDATED', 'title', 'Poster', 'Poster seminar'])
        ->and($log['category'])->toBe(['REQUEST_UPDATED', 'category', 'Design', 'Documentation'])
        ->and($log['priority'])->toBe(['PRIORITY_CHANGED', 'priority', 'LOW', 'HIGH'])
        ->and($log['deadline'][0])->toBe('DEADLINE_CHANGED')
        ->and($log['deadline'][3])->toBe($newDeadline->utc()->toIso8601String());
});

it('logs nothing when nothing actually changes', function () {
    $c = cast();
    $request = makeRequest($c->requester, $c->category, ['title' => 'Poster']);
    Sanctum::actingAs($c->requester);

    $this->patchJson(requestUrl($request), ['title' => 'Poster', 'priority' => 'NORMAL'])->assertOk();
    $this->patchJson(requestUrl($request), ['title' => '  Poster  '])->assertOk(); // whitespace alone is not a change

    expect(changesOf($request))->toBe([]);
});

it('stops the requester editing once the request has been triaged', function () {
    $c = cast();
    $request = makeRequest($c->requester, $c->category);
    app(ChangeRequestStatus::class)->handle($c->admin, $request, RequestStatus::Triaged);

    Sanctum::actingAs($c->requester);
    $this->patchJson(requestUrl($request), ['title' => 'sneaky'])->assertForbidden();
    $this->patchJson(requestUrl($request), ['priority' => 'CRITICAL'])->assertForbidden();

    expect($request->fresh()->title)->not->toBe('sneaky');
});

it('lets an admin override priority at any time, but not a team lead', function () {
    $c = cast();
    $request = assignedRequest($c);

    Sanctum::actingAs($c->creativeLead);
    $this->patchJson(requestUrl($request), ['priority' => 'CRITICAL'])->assertForbidden();

    Sanctum::actingAs($c->admin);
    $this->patchJson(requestUrl($request), ['priority' => 'CRITICAL'])->assertOk()->assertJsonPath('data.priority', 'CRITICAL');

    expect(changesOf($request))->toContain(['PRIORITY_CHANGED', 'priority', 'NORMAL', 'CRITICAL']);
    expect(ActivityLog::query()->where('action', 'PRIORITY_CHANGED')->value('actor_id'))->toBe($c->admin->id);
});

it('lets a team lead adjust the details of their own team\'s request', function () {
    $c = cast();
    $request = assignedRequest($c);

    Sanctum::actingAs($c->creativeLead);
    $this->patchJson(requestUrl($request), ['deadline' => now()->addDays(20)->toIso8601String()])->assertOk();
    expect(changesOf($request)[count(changesOf($request)) - 1][0])->toBe('DEADLINE_CHANGED');
});

it('does not let staff, other requesters or the PIC edit details', function (string $who) {
    $c = cast();
    $request = assignedRequest($c);
    Sanctum::actingAs($c->{$who});

    $this->patchJson(requestUrl($request), ['title' => 'nope'])->assertForbidden();
})->with(['ahmad', 'budi']);

it('rejects a deadline in the past and invalid values', function (array $payload, string $field) {
    $c = cast();
    $request = makeRequest($c->requester, $c->category);
    Sanctum::actingAs($c->requester);

    $this->patchJson(requestUrl($request), $payload)->assertUnprocessable()->assertJsonValidationErrors($field);
})->with([
    'past deadline' => [['deadline' => '2020-01-01T00:00:00Z'], 'deadline'],
    'blank title' => [['title' => ''], 'title'],
    'bad priority' => [['priority' => 'MEGA'], 'priority'],
    'unknown category' => [['category_id' => 424242], 'category_id'],
]);

it('refuses to edit a completed or cancelled request', function () {
    $c = cast();
    $request = makeRequest($c->requester, $c->category);
    app(ChangeRequestStatus::class)->handle($c->requester, $request, RequestStatus::Cancelled, 'no longer needed');

    Sanctum::actingAs($c->admin);
    $this->patchJson(requestUrl($request), ['title' => 'late edit'])->assertUnprocessable()->assertJsonValidationErrors('status');
});

it('ignores attempts to change the number or the owner through the API', function () {
    $c = cast();
    $request = makeRequest($c->requester, $c->category);
    Sanctum::actingAs($c->admin);

    $this->patchJson(requestUrl($request), ['request_number' => 'ATH-999999', 'requester_id' => $c->otherRequester->id, 'status' => 'COMPLETED'])
        ->assertOk();

    $fresh = $request->fresh();
    expect($fresh->request_number)->toBe('ATH-000001')
        ->and($fresh->requester_id)->toBe($c->requester->id)
        ->and($fresh->status)->toBe(RequestStatus::New);
});
