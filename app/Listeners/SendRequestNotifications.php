<?php

namespace App\Listeners;

use App\Enums\NotificationType;
use App\Enums\RequestStatus;
use App\Enums\RoleCode;
use App\Events\RequestNotable;
use App\Models\ServiceRequest;
use App\Models\User;
use App\Notifications\RequestNotification;
use Illuminate\Support\Collection;
use Throwable;

/**
 * Decides who needs to know, and tells them. Nothing here changes a request; and a failure to
 * deliver (mail server down, say) is reported but never undoes or blocks the work that triggered it.
 */
class SendRequestNotifications
{
    public function handle(RequestNotable $event): void
    {
        try {
            foreach ($this->plan($event) as [$type, $recipients, $params]) {
                foreach ($this->distinct($recipients, $event->actor) as $user) {
                    $user->notify(new RequestNotification($type, $event->request, $params));
                }
            }
        } catch (Throwable $e) {
            report($e);
        }
    }

    /**
     * @return array<int, array{0: NotificationType, 1: Collection<int, User>, 2: array<string, mixed>}>
     */
    private function plan(RequestNotable $event): array
    {
        // Read the committed state: the model the action held may carry stale relations.
        $request = $event->request->fresh(['requester', 'assignedUser']) ?? $event->request;
        $base = ['actor' => $event->actor?->name ?? __('common.system')];

        return match ($event->kind) {
            RequestNotable::ASSIGNED => $this->assigned($event, $request, $base),
            RequestNotable::STATUS_CHANGED => $this->statusChanged($event, $request, $base),
            default => [],
        };
    }

    /** @param array<string, mixed> $base */
    private function assigned(RequestNotable $event, ServiceRequest $request, array $base): array
    {
        if (($event->context['pic_id'] ?? null) !== null) {
            return [[NotificationType::AssignedPic, User::query()->whereKey($event->context['pic_id'])->get(), $base]];
        }

        // Routed to a team with nobody named yet: the team's leads decide who takes it.
        return [[NotificationType::AssignedTeam, $this->teamLeads($request), $base]];
    }

    /** @param array<string, mixed> $base */
    private function statusChanged(RequestNotable $event, ServiceRequest $request, array $base): array
    {
        $from = RequestStatus::from($event->context['from']);
        $to = RequestStatus::from($event->context['to']);
        $params = $base + ['from' => $from->value, 'to' => $to->value];

        $requester = collect([$request->requester]);
        $pic = collect([$request->assignedUser])->filter();

        if ($to === RequestStatus::Triaged) {
            return []; // an internal step; the assignment that follows says the useful thing
        }

        if ($to === RequestStatus::Completed) {
            return [[NotificationType::Completed, $requester->merge($pic), $params]];
        }

        if ($to === RequestStatus::Review && $from === RequestStatus::InProgress) {
            return [
                [NotificationType::ReviewRequested, $this->reviewers($request), $params],
                [NotificationType::StatusChanged, $requester, $params],
            ];
        }

        if ($from === RequestStatus::Review && $to === RequestStatus::InProgress) {
            return [
                [NotificationType::RevisionRequested, $pic, $params],
                [NotificationType::StatusChanged, $requester, $params],
            ];
        }

        // The PIC is told about being assigned by the assignment notification itself.
        if ($to === RequestStatus::Assigned && $from === RequestStatus::Triaged) {
            return [[NotificationType::StatusChanged, $requester, $params]];
        }

        $audience = $to === RequestStatus::Blocked
            ? $requester->merge($pic)->merge($this->teamLeads($request))
            : $requester->merge($pic);

        return [[NotificationType::StatusChanged, $audience, $params]];
    }

    /** @return Collection<int, User> */
    private function teamLeads(ServiceRequest $request): Collection
    {
        return User::query()
            ->whereHas('roles', fn ($q) => $q->where('code', RoleCode::TeamLead->value))
            ->when($request->assigned_team_id, fn ($q, $team) => $q->whereHas('teams', fn ($t) => $t->whereKey($team)))
            ->get();
    }

    /** Whoever may decide the review: the team's leads, or admins if the team has none. Never the person who did the work. */
    private function reviewers(ServiceRequest $request): Collection
    {
        $leads = $this->teamLeads($request)->reject(fn (User $u) => $u->id === $request->assigned_user_id);

        if ($leads->isNotEmpty()) {
            return $leads;
        }

        return User::query()
            ->whereHas('roles', fn ($q) => $q->where('code', RoleCode::Admin->value))
            ->whereKeyNot($request->assigned_user_id ?? 0)
            ->get();
    }

    /**
     * Each person once, active only, and never the person who did the thing.
     *
     * @param  Collection<int, User>  $users
     * @return Collection<int, User>
     */
    private function distinct(Collection $users, ?User $actor): Collection
    {
        return $users->filter()->unique('id')->filter(fn (User $u) => $u->is_active && $u->id !== $actor?->id)->values();
    }
}
