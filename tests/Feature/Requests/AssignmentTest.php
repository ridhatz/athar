<?php

use App\Actions\Requests\AssignRequest;
use App\Actions\Requests\ChangeRequestStatus;
use App\Enums\ActivityAction;
use App\Enums\RequestStatus;
use App\Models\ActivityLog;
use App\Models\Assignment;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Database\QueryException;
use Laravel\Sanctum\Sanctum;

function logsOf($request): array
{
    return ActivityLog::query()->where('request_id', $request->id)->orderBy('id')->get()
        ->map(fn ($l) => [$l->action->value, $l->field, $l->old_value, $l->new_value])->all();
}

it('triages and assigns a brand-new request in one step, logging each move', function () {
    $c = cast();
    $request = makeRequest($c->requester, $c->category);

    Sanctum::actingAs($c->admin);
    $this->postJson("/api/v1/requests/{$request->request_number}/assign", [
        'team_id' => $c->creative->id,
        'user_id' => $c->ahmad->id,
        'note' => 'Ahmad handles posters',
    ])
        ->assertOk()
        ->assertJsonPath('data.status', 'ASSIGNED')
        ->assertJsonPath('data.assigned_team.name', 'Creative')
        ->assertJsonPath('data.assigned_user.name', 'Ahmad');

    expect(logsOf($request))->toBe([
        ['REQUEST_CREATED', null, null, 'ATH-000001'],
        ['STATUS_CHANGED', 'status', 'NEW', 'TRIAGED'],
        ['TEAM_ASSIGNED', 'assigned_team', null, 'Creative'],
        ['PIC_ASSIGNED', 'assigned_user', null, 'Ahmad'],
        ['STATUS_CHANGED', 'status', 'TRIAGED', 'ASSIGNED'],
    ]);

    expect(ActivityLog::query()->where('action', 'TEAM_ASSIGNED')->value('note'))->toBe('Ahmad handles posters');
});

it('can assign to a team alone, leaving the PIC for later', function () {
    $c = cast();
    $request = makeRequest($c->requester, $c->category);

    $assigned = app(AssignRequest::class)->handle($c->admin, $request, ['team_id' => $c->creative->id]);

    expect($assigned->status)->toBe(RequestStatus::Assigned)
        ->and($assigned->assigned_team_id)->toBe($c->creative->id)
        ->and($assigned->assigned_user_id)->toBeNull();

    // ...and a PIC can then be named without repeating the team.
    $withPic = app(AssignRequest::class)->handle($c->creativeLead, $request, ['user_id' => $c->budi->id]);
    expect($withPic->assigned_user_id)->toBe($c->budi->id)->and($withPic->assigned_team_id)->toBe($c->creative->id);
});

it('keeps the whole assignment history: Creative, then Ahmad, then Budi', function () {
    $c = cast();
    $request = makeRequest($c->requester, $c->category);
    $assign = app(AssignRequest::class);

    $assign->handle($c->admin, $request, ['team_id' => $c->creative->id]);
    $assign->handle($c->creativeLead, $request, ['user_id' => $c->ahmad->id]);
    $assign->handle($c->creativeLead, $request, ['user_id' => $c->budi->id]);

    $history = Assignment::query()->where('request_id', $request->id)->orderBy('id')->get();

    expect($history)->toHaveCount(3)
        ->and($history->map(fn ($a) => [$a->team_id, $a->user_id])->all())->toBe([
            [$c->creative->id, null],
            [$c->creative->id, $c->ahmad->id],
            [$c->creative->id, $c->budi->id],
        ])
        ->and($history->map(fn ($a) => (bool) $a->is_current)->all())->toBe([false, false, true])
        ->and($history[0]->ended_at)->not->toBeNull()
        ->and($history[1]->ended_at)->not->toBeNull()
        ->and($history[2]->ended_at)->toBeNull();

    $picLog = collect(logsOf($request))->filter(fn ($l) => str_starts_with($l[0], 'PIC_'))->values()->all();
    expect($picLog)->toBe([
        ['PIC_ASSIGNED', 'assigned_user', null, 'Ahmad'],
        ['PIC_CHANGED', 'assigned_user', 'Ahmad', 'Budi'],
    ]);
});

it('lets the database hold only one current assignment per request', function () {
    $c = cast();
    $request = assignedRequest($c);

    expect(fn () => Assignment::query()->create([
        'request_id' => $request->id,
        'team_id' => $c->creative->id,
        'assigned_by' => $c->admin->id,
        'assigned_at' => now(),
        'is_current' => true,
    ]))->toThrow(QueryException::class);
});

it('keeps the status when a PIC changes mid-work', function () {
    $c = cast();
    $request = assignedRequest($c);
    app(ChangeRequestStatus::class)->handle($c->ahmad, $request, RequestStatus::InProgress);

    $reassigned = app(AssignRequest::class)->handle($c->creativeLead, $request, ['user_id' => $c->budi->id]);

    expect($reassigned->status)->toBe(RequestStatus::InProgress)->and($reassigned->assigned_user_id)->toBe($c->budi->id);
});

it('infers the team when the named person belongs to exactly one', function () {
    $c = cast();
    $request = makeRequest($c->requester, $c->category);

    $assigned = app(AssignRequest::class)->handle($c->admin, $request, ['user_id' => $c->ahmad->id]);

    expect($assigned->assigned_team_id)->toBe($c->creative->id)->and($assigned->assigned_user_id)->toBe($c->ahmad->id);
});

it('asks for a team when it cannot be inferred', function () {
    $c = cast();
    $request = makeRequest($c->requester, $c->category);
    $twoTeams = $c->ahmad->joinTeam($c->it);

    expectValidationError(
        fn () => app(AssignRequest::class)->handle($c->admin, $request, ['user_id' => $twoTeams->id]),
        'team_id',
    );
    expectValidationError(fn () => app(AssignRequest::class)->handle($c->admin, $request, []), 'team_id');
});

it('only lets a member of the team, with a working role, be the PIC', function () {
    $c = cast();
    $request = makeRequest($c->requester, $c->category);
    $assign = app(AssignRequest::class);

    // Dewi is IT, not Creative.
    expectValidationError(fn () => $assign->handle($c->admin, $request, ['team_id' => $c->creative->id, 'user_id' => $c->dewi->id]), 'user_id');
    // A requester is not a worker.
    expectValidationError(fn () => $assign->handle($c->admin, $request, ['team_id' => $c->creative->id, 'user_id' => $c->requester->id]), 'user_id');
    // A deactivated staff member cannot be given work.
    $c->budi->forceFill(['is_active' => false])->save();
    expectValidationError(fn () => $assign->handle($c->admin, $request, ['team_id' => $c->creative->id, 'user_id' => $c->budi->id]), 'user_id');

    expect($request->fresh()->status)->toBe(RequestStatus::New)->and(Assignment::query()->count())->toBe(0);
});

it('rejects an assignment that changes nothing', function () {
    $c = cast();
    $request = assignedRequest($c);

    expectValidationError(
        fn () => app(AssignRequest::class)->handle($c->admin, $request, ['team_id' => $c->creative->id, 'user_id' => $c->ahmad->id]),
        'team_id',
        'Nothing would change',
    );
});

it('will not move work to another team without naming who does it', function () {
    $c = cast();
    $request = assignedRequest($c);
    app(ChangeRequestStatus::class)->handle($c->ahmad, $request, RequestStatus::InProgress);

    expectValidationError(
        fn () => app(AssignRequest::class)->handle($c->admin, $request, ['team_id' => $c->it->id]),
        'user_id',
        'person in charge',
    );

    // Naming the new PIC in the same call is fine, and the old team/PIC stay in history.
    $moved = app(AssignRequest::class)->handle($c->admin, $request, ['team_id' => $c->it->id, 'user_id' => $c->dewi->id]);
    expect($moved->status)->toBe(RequestStatus::InProgress)
        ->and($moved->assigned_team_id)->toBe($c->it->id)
        ->and($moved->assigned_user_id)->toBe($c->dewi->id)
        ->and(Assignment::query()->where('request_id', $request->id)->count())->toBe(2);
});

it('will not clear the PIC while work depends on one, but can before work starts', function () {
    $c = cast();
    $request = assignedRequest($c);
    $assign = app(AssignRequest::class);

    $cleared = $assign->handle($c->admin, $request, ['user_id' => null]);
    expect($cleared->assigned_user_id)->toBeNull()->and($cleared->status)->toBe(RequestStatus::Assigned);
    expect(collect(logsOf($request))->last(fn ($l) => str_starts_with($l[0], 'PIC_')))
        ->toBe(['PIC_UNASSIGNED', 'assigned_user', 'Ahmad', null]);

    $assign->handle($c->admin, $request, ['user_id' => $c->ahmad->id]);
    app(ChangeRequestStatus::class)->handle($c->ahmad, $request, RequestStatus::InProgress);

    expectValidationError(fn () => $assign->handle($c->admin, $request, ['user_id' => null]), 'user_id');
});

it('cannot reassign a finished request', function () {
    $c = cast();
    $request = assignedRequest($c);
    app(ChangeRequestStatus::class)->handle($c->admin, $request, RequestStatus::Cancelled, 'not needed');

    expectValidationError(fn () => app(AssignRequest::class)->handle($c->admin, $request, ['team_id' => $c->it->id]), 'team_id');
});

describe('who may assign', function () {
    it('allows an admin, and the lead of the request\'s own team', function () {
        $c = cast();
        $request = assignedRequest($c);

        expect(app(AssignRequest::class)->handle($c->creativeLead, $request, ['user_id' => $c->budi->id])->assigned_user_id)
            ->toBe($c->budi->id);
    });

    it('allows any team lead to route a request that has no team yet', function () {
        $c = cast();
        $request = makeRequest($c->requester, $c->category);

        expect(app(AssignRequest::class)->handle($c->itLead, $request, ['team_id' => $c->it->id])->assigned_team_id)
            ->toBe($c->it->id);
    });

    it('refuses the lead of a different team once the request belongs to a team', function () {
        $c = cast();
        $request = assignedRequest($c);

        expect(fn () => app(AssignRequest::class)->handle($c->itLead, $request, ['team_id' => $c->it->id, 'user_id' => $c->dewi->id]))
            ->toThrow(AuthorizationException::class);
    });

    it('refuses staff, requesters and management, without changing anything', function (string $who) {
        $c = cast();
        $request = makeRequest($c->requester, $c->category);

        expect(fn () => app(AssignRequest::class)->handle($c->{$who}, $request, ['team_id' => $c->creative->id]))
            ->toThrow(AuthorizationException::class);

        expect($request->fresh()->status)->toBe(RequestStatus::New)
            ->and(ActivityLog::query()->where('action', ActivityAction::TeamAssigned->value)->count())->toBe(0);
    })->with(['ahmad', 'requester', 'management']);
});
