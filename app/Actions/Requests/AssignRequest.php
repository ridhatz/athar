<?php

namespace App\Actions\Requests;

use App\Enums\ActivityAction;
use App\Enums\RequestStatus;
use App\Enums\RoleCode;
use App\Events\RequestNotable;
use App\Models\Assignment;
use App\Models\ServiceRequest;
use App\Models\Team;
use App\Models\User;
use App\Services\ActivityLogger;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;

/**
 * Hands a request to a team and/or a person (the PIC).
 *
 * Input semantics
 *  - team_id given            → route to that team.
 *  - team_id omitted          → keep the current team.
 *  - user_id given (or null)  → set (or clear) the PIC.
 *  - user_id omitted          → keep the PIC, unless the team changed, in which case it is cleared.
 *
 * History is never overwritten: the previous assignment row is closed and a new one is added,
 * so "Creative → Ahmad → Budi" remains readable forever.
 */
final class AssignRequest
{
    public function __construct(
        private readonly ActivityLogger $log,
        private readonly ChangeRequestStatus $status,
    ) {}

    /**
     * @param  array{team_id?: int|null, user_id?: int|null, note?: string|null}  $input
     */
    public function handle(User $actor, ServiceRequest $request, array $input): ServiceRequest
    {
        return DB::transaction(function () use ($actor, $request, $input) {
            $current = ServiceRequest::query()->whereKey($request->id)->lockForUpdate()->firstOrFail();

            if ($current->status->isTerminal()) {
                throw ValidationException::withMessages(['team_id' => __('errors.request_closed')]);
            }

            Gate::forUser($actor)->authorize('assign', $current);

            [$team, $newUserId] = $this->resolveTarget($current, $input);

            $teamChanged = $team->id !== $current->assigned_team_id;
            $userChanged = $newUserId !== $current->assigned_user_id;

            if (! $teamChanged && ! $userChanged) {
                throw ValidationException::withMessages(['team_id' => __('errors.no_change')]);
            }

            if ($newUserId === null && $this->needsPic($current)) {
                throw ValidationException::withMessages(['user_id' => __('errors.pic_required_reassign')]);
            }

            $note = $input['note'] ?? null;
            $oldTeamName = $current->assignedTeam?->name;
            $oldUserName = $current->assignedUser?->name;
            $hadTeam = $current->assigned_team_id !== null;
            $hadUser = $current->assigned_user_id !== null;

            // A brand-new request is triaged by being assigned; both steps stay explicit in the log.
            if ($current->status === RequestStatus::New) {
                $this->status->apply($actor, $current, RequestStatus::Triaged);
            }

            $this->closeCurrentAssignment($current);
            $newUser = $newUserId !== null ? User::query()->findOrFail($newUserId) : null;

            Assignment::query()->create([
                'request_id' => $current->id,
                'team_id' => $team->id,
                'user_id' => $newUserId,
                'assigned_by' => $actor->id,
                'note' => $note !== null ? mb_substr(trim($note), 0, 500) ?: null : null,
                'assigned_at' => now(),
                'is_current' => true,
            ]);

            $current->assigned_team_id = $team->id;
            $current->assigned_user_id = $newUserId;
            $current->save();

            $noteForFirstEntry = $note;

            if ($teamChanged) {
                $this->log->record(
                    $current, $actor,
                    $hadTeam ? ActivityAction::TeamChanged : ActivityAction::TeamAssigned,
                    'assigned_team', $oldTeamName, $team->name, $noteForFirstEntry,
                );
                $noteForFirstEntry = null;
            }

            if ($userChanged) {
                $action = match (true) {
                    $newUser === null => ActivityAction::PicUnassigned,
                    $hadUser => ActivityAction::PicChanged,
                    default => ActivityAction::PicAssigned,
                };

                $this->log->record($current, $actor, $action, 'assigned_user', $oldUserName, $newUser?->name, $noteForFirstEntry);
            }

            if ($newUserId !== null && $userChanged) {
                DB::afterCommit(fn () => event(new RequestNotable(RequestNotable::ASSIGNED, $current, $actor, ['pic_id' => $newUserId])));
            } elseif ($teamChanged && $newUserId === null) {
                DB::afterCommit(fn () => event(new RequestNotable(RequestNotable::ASSIGNED, $current, $actor, ['team_id' => $team->id])));
            }

            if ($current->status === RequestStatus::Triaged) {
                $this->status->apply($actor, $current, RequestStatus::Assigned);
            }

            return $current;
        });
    }

    /**
     * Work out the team and PIC this call is asking for, and validate them.
     *
     * @param  array<string, mixed>  $input
     * @return array{0: Team, 1: int|null}
     */
    private function resolveTarget(ServiceRequest $current, array $input): array
    {
        $userProvided = array_key_exists('user_id', $input);
        $requestedUserId = $userProvided && $input['user_id'] !== null ? (int) $input['user_id'] : null;

        $teamId = isset($input['team_id']) ? (int) $input['team_id'] : $current->assigned_team_id;

        // "Request → Ahmad" with no team named: infer it when the person belongs to exactly one.
        if ($teamId === null && $requestedUserId !== null) {
            $memberOf = User::query()->find($requestedUserId)?->teamIds() ?? [];
            $teamId = count($memberOf) === 1 ? $memberOf[0] : null;
        }

        if ($teamId === null) {
            throw ValidationException::withMessages(['team_id' => __('errors.team_required')]);
        }

        $team = Team::query()->where('is_active', true)->find($teamId);

        if ($team === null) {
            throw ValidationException::withMessages(['team_id' => __('errors.team_invalid')]);
        }

        $teamChanged = $team->id !== $current->assigned_team_id;

        $userId = $userProvided
            ? $requestedUserId
            : ($teamChanged ? null : $current->assigned_user_id);

        if ($userId !== null) {
            $this->assertAssignable($userId, $team);
        }

        return [$team, $userId];
    }

    /** A PIC must be an active member of the team and hold a working role. */
    private function assertAssignable(int $userId, Team $team): void
    {
        $user = User::query()->find($userId);

        $valid = $user !== null
            && $user->is_active
            && $user->isInTeam($team->id)
            && $user->hasAnyRole(RoleCode::Staff, RoleCode::TeamLead);

        if (! $valid) {
            throw ValidationException::withMessages(['user_id' => __('errors.user_invalid')]);
        }
    }

    /** Some states cannot exist without a named person, including a blocked request that will resume into one. */
    private function needsPic(ServiceRequest $request): bool
    {
        return $request->status->requiresPic()
            || ($request->status === RequestStatus::Blocked && $request->blocked_from_status?->requiresPic());
    }

    private function closeCurrentAssignment(ServiceRequest $request): void
    {
        Assignment::query()
            ->where('request_id', $request->id)
            ->where('is_current', true)
            ->update(['ended_at' => now(), 'is_current' => null]);
    }
}
