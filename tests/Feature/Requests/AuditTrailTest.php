<?php

use App\Enums\ActivityAction;
use App\Exceptions\ImmutableRecordException;
use App\Models\ActivityLog;
use App\Services\ActivityLogger;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use Laravel\Sanctum\Sanctum;

it('refuses to modify or delete an activity entry through Eloquent', function () {
    $c = cast();
    $request = makeRequest($c->requester, $c->category);
    $entry = ActivityLog::query()->where('request_id', $request->id)->sole();

    $entry->note = 'rewritten history';
    expect(fn () => $entry->save())->toThrow(ImmutableRecordException::class);
    expect(fn () => $entry->delete())->toThrow(ImmutableRecordException::class);
    expect($entry->fresh()->note)->toBeNull();
});

it('refuses raw UPDATE and DELETE at the database, where triggers are permitted', function () {
    $hasTriggers = DB::selectOne(
        "SELECT COUNT(*) AS n FROM information_schema.TRIGGERS WHERE TRIGGER_SCHEMA = DATABASE() AND TRIGGER_NAME LIKE 'activity_logs_block_%'",
    )->n;

    if ((int) $hasTriggers < 2) {
        $this->markTestSkipped('This database server does not allow the audit triggers (see the migration). The model guard still applies.');
    }

    $c = cast();
    $request = makeRequest($c->requester, $c->category);

    expect(fn () => DB::table('activity_logs')->where('request_id', $request->id)->update(['note' => 'tampered']))
        ->toThrow(QueryException::class, 'append-only');
    expect(fn () => DB::table('activity_logs')->where('request_id', $request->id)->delete())
        ->toThrow(QueryException::class, 'append-only');
    expect(fn () => ActivityLog::query()->where('request_id', $request->id)->update(['note' => 'tampered']))
        ->toThrow(QueryException::class, 'append-only');

    expect(ActivityLog::query()->where('request_id', $request->id)->count())->toBe(1);
});

it('cannot hard-delete a request that has history', function () {
    $c = cast();
    $request = makeRequest($c->requester, $c->category);

    // The foreign keys are RESTRICT: history pins the request in place.
    expect(fn () => DB::table('requests')->where('id', $request->id)->delete())->toThrow(QueryException::class);
});

it('hides internal entries from the requester but shows them to the working side', function () {
    $c = cast();
    $request = assignedRequest($c);
    app(ActivityLogger::class)->record($request, $c->ahmad, ActivityAction::RequestUpdated, 'note', null, null, 'Designer needs about 2 hours once assets arrive', internal: true);

    $notes = fn ($user) => (function () use ($user, $request) {
        Sanctum::actingAs($user);

        return collect(test()->getJson("/api/v1/requests/{$request->request_number}/activity")->assertOk()->json('data'))
            ->pluck('note')->filter()->values()->all();
    })();

    expect($notes($c->requester))->toBe([])
        ->and($notes($c->ahmad))->toBe(['Designer needs about 2 hours once assets arrive'])
        ->and($notes($c->creativeLead))->toBe(['Designer needs about 2 hours once assets arrive'])
        ->and($notes($c->management))->toBe(['Designer needs about 2 hours once assets arrive']);
});

it('shows the requester the visible history, in order, with who did what', function () {
    $c = cast();
    $request = assignedRequest($c);
    Sanctum::actingAs($c->requester);

    $entries = $this->getJson("/api/v1/requests/{$request->request_number}/activity")->assertOk()->json('data');

    expect(array_column($entries, 'action'))->toBe([
        'REQUEST_CREATED', 'STATUS_CHANGED', 'TEAM_ASSIGNED', 'PIC_ASSIGNED', 'STATUS_CHANGED',
    ])
        ->and($entries[0]['actor']['name'])->toBe($c->requester->name)
        ->and($entries[2]['actor']['name'])->toBe($c->admin->name)
        ->and($entries[3]['new_value'])->toBe('Ahmad');
});

it('keeps assignment history to the working side only', function () {
    $c = cast();
    $request = assignedRequest($c);

    Sanctum::actingAs($c->requester);
    $this->getJson("/api/v1/requests/{$request->request_number}/assignments")->assertForbidden();

    Sanctum::actingAs($c->creativeLead);
    $this->getJson("/api/v1/requests/{$request->request_number}/assignments")->assertOk()
        ->assertJsonPath('data.0.team.name', 'Creative')
        ->assertJsonPath('data.0.user.name', 'Ahmad')
        ->assertJsonPath('data.0.assigned_by.name', $c->admin->name)
        ->assertJsonPath('data.0.is_current', true);
});

it('rejects a status that is not part of the workflow at the database level', function () {
    $c = cast();
    $request = makeRequest($c->requester, $c->category);

    expect(fn () => DB::table('requests')->where('id', $request->id)->update(['status' => 'DONE_ISH']))
        ->toThrow(QueryException::class);
    expect(fn () => DB::table('requests')->where('id', $request->id)->update(['priority' => 'MEGA']))
        ->toThrow(QueryException::class);
});
