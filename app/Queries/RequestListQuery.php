<?php

namespace App\Queries;

use App\Enums\RequestStatus;
use App\Models\ServiceRequest;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;

/**
 * Builds the filtered, sorted, permission-scoped request list.
 * Used by both the web pages and the API so they can never disagree.
 *
 * Visibility is applied first and unconditionally: no filter can widen it.
 */
final class RequestListQuery
{
    private const PRIORITY_ORDER = "CASE requests.priority WHEN 'LOW' THEN 1 WHEN 'NORMAL' THEN 2 WHEN 'HIGH' THEN 3 WHEN 'CRITICAL' THEN 4 END";

    /**
     * @param  array<string, mixed>  $filters
     * @return Builder<ServiceRequest>
     */
    public function build(User $user, array $filters): Builder
    {
        $query = ServiceRequest::query()
            ->visibleTo($user)
            ->with(['category', 'department', 'requester', 'assignedTeam', 'assignedUser']);

        $query
            ->when($filters['status'] ?? null, fn (Builder $q, $v) => $q->where('requests.status', $v))
            ->when($filters['priority'] ?? null, fn (Builder $q, $v) => $q->where('requests.priority', $v))
            ->when($filters['category_id'] ?? null, fn (Builder $q, $v) => $q->where('requests.category_id', $v))
            ->when($filters['team_id'] ?? null, fn (Builder $q, $v) => $q->where('requests.assigned_team_id', $v))
            ->when($filters['mine'] ?? false, fn (Builder $q) => $q->where('requests.requester_id', $user->id))
            ->when($filters['assigned_to_me'] ?? false, fn (Builder $q) => $q->where('requests.assigned_user_id', $user->id))
            ->when($filters['overdue'] ?? false, fn (Builder $q) => $q->overdue())
            ->when($filters['open'] ?? false, fn (Builder $q) => $q->whereNotIn('requests.status', [RequestStatus::Completed->value, RequestStatus::Cancelled->value]))
            ->when($filters['my_teams'] ?? false, fn (Builder $q) => $q->whereIn('requests.assigned_team_id', $user->teamIds()))
            ->when($filters['unrouted'] ?? false, fn (Builder $q) => $q->whereNull('requests.assigned_team_id'))
            ->when($filters['due_today'] ?? false, function (Builder $q) {
                $zone = config('athar.timezone');
                $q->whereBetween('requests.deadline', [now($zone)->startOfDay()->utc(), now($zone)->endOfDay()->utc()]);
            })
            ->when($filters['q'] ?? null, function (Builder $q, string $term) {
                $like = '%'.addcslashes($term, '%_\\').'%';
                $q->where(fn (Builder $w) => $w
                    ->where('requests.request_number', 'like', $like)
                    ->orWhere('requests.title', 'like', $like));
            });

        $direction = ($filters['dir'] ?? 'desc') === 'asc' ? 'asc' : 'desc';

        match ($filters['sort'] ?? 'created_at') {
            'priority' => $query->orderByRaw(self::PRIORITY_ORDER.' '.$direction),
            'deadline' => $query->orderBy('requests.deadline', $direction),
            'updated_at' => $query->orderBy('requests.updated_at', $direction),
            default => $query->orderBy('requests.created_at', $direction),
        };

        // A stable tiebreaker keeps pagination from shuffling rows that share a timestamp.
        return $query->orderByDesc('requests.id');
    }
}
