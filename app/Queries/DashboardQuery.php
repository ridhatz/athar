<?php

namespace App\Queries;

use App\Enums\RequestStatus;
use App\Enums\RoleCode;
use App\Models\User;
use Illuminate\Support\Facades\DB;

/**
 * Operational counts for the three dashboards. Deliberately plain: counts and groupings, no analytics.
 *
 * Each card is defined by the list filter it stands for, and its number is computed BY the same
 * RequestListQuery the request list uses. So the card and the list it links to cannot disagree:
 * clicking a "7" always opens exactly seven requests.
 */
final class DashboardQuery
{
    public function __construct(private readonly RequestListQuery $list) {}

    /** Which dashboards a person may open, in the order they are offered. */
    public function perspectivesFor(User $user): array
    {
        $perspectives = [];

        if ($user->hasAnyRole(RoleCode::Requester, RoleCode::Staff, RoleCode::TeamLead, RoleCode::Admin)) {
            $perspectives[] = 'requester';
        }

        if ($user->hasAnyRole(RoleCode::Staff, RoleCode::TeamLead, RoleCode::Admin)) {
            $perspectives[] = 'staff';
        }

        if ($user->hasAnyRole(RoleCode::Admin, RoleCode::Management)) {
            $perspectives[] = 'management';
        }

        return $perspectives;
    }

    /** Requests I asked for. */
    public function requester(User $user): array
    {
        return $this->cards($user, [
            'my_requests' => ['mine' => 1],
            'new' => ['mine' => 1, 'status' => RequestStatus::New->value],
            'in_progress' => ['mine' => 1, 'status' => RequestStatus::InProgress->value],
            'waiting_review' => ['mine' => 1, 'status' => RequestStatus::Review->value],
            'completed' => ['mine' => 1, 'status' => RequestStatus::Completed->value],
            'overdue' => ['mine' => 1, 'overdue' => 1],
        ]);
    }

    /** Work in front of me: assigned to me or my teams. Team leads and admins also see the triage queue. */
    public function staff(User $user): array
    {
        $cards = [
            'assigned_to_me' => ['assigned_to_me' => 1, 'open' => 1],
            'team_work' => ['my_teams' => 1, 'open' => 1],
            'due_today' => ['my_teams' => 1, 'open' => 1, 'due_today' => 1],
            'overdue' => ['my_teams' => 1, 'overdue' => 1],
            'blocked' => ['my_teams' => 1, 'status' => RequestStatus::Blocked->value],
            'awaiting_review' => ['my_teams' => 1, 'status' => RequestStatus::Review->value],
        ];

        if ($user->hasAnyRole(RoleCode::TeamLead, RoleCode::Admin)) {
            $cards['needs_triage'] = ['unrouted' => 1, 'status' => RequestStatus::New->value];
        }

        return $this->cards($user, $cards);
    }

    /**
     * The whole picture, read-only. For ADMIN and MANAGEMENT, who can see every request.
     *
     * @return array<string, mixed>
     */
    public function management(User $user): array
    {
        return [
            'cards' => $this->cards($user, [
                'total' => [],
                'new' => ['status' => RequestStatus::New->value],
                'in_progress' => ['status' => RequestStatus::InProgress->value],
                'review' => ['status' => RequestStatus::Review->value],
                'completed' => ['status' => RequestStatus::Completed->value],
                'blocked' => ['status' => RequestStatus::Blocked->value],
                'overdue' => ['overdue' => 1],
            ]),
            'by_category' => $this->grouped('request_categories', 'requests.category_id', app()->getLocale() === 'en' ? 'name_en' : 'name_id'),
            'by_team' => $this->grouped('teams', 'requests.assigned_team_id', 'name'),
            'by_department' => $this->grouped('departments', 'requests.department_id', 'name'),
            'avg_completion_hours' => $this->averageCompletionHours(),
        ];
    }

    /**
     * @param  array<string, array<string, mixed>>  $definitions  card key => the list filters it stands for
     * @return array<int, array{key: string, count: int, filters: array<string, mixed>}>
     */
    private function cards(User $user, array $definitions): array
    {
        $cards = [];

        foreach ($definitions as $key => $filters) {
            $cards[] = [
                'key' => $key,
                'count' => $this->list->build($user, $filters)->reorder()->count(),
                'filters' => $filters,
            ];
        }

        return $cards;
    }

    /**
     * Requests per group, biggest first. Requests with no group (e.g. no team yet) fall under a null id.
     *
     * @return array<int, array{id: int|null, name: string|null, count: int}>
     */
    private function grouped(string $table, string $foreignKey, string $nameColumn): array
    {
        return DB::table('requests')
            ->leftJoin($table, "{$table}.id", '=', $foreignKey)
            ->whereNull('requests.deleted_at')
            ->selectRaw("{$table}.id as id, {$table}.{$nameColumn} as name, count(*) as total")
            ->groupBy("{$table}.id", "{$table}.{$nameColumn}")
            ->orderByDesc('total')
            ->orderBy('name') // ties keep a stable order, so the bars do not jump between reloads
            ->get()
            ->map(fn ($row) => ['id' => $row->id, 'name' => $row->name, 'count' => (int) $row->total])
            ->all();
    }

    /** Mean time from creation to completion, in hours; null until something has been completed. */
    private function averageCompletionHours(): ?float
    {
        $seconds = DB::table('requests')
            ->whereNull('deleted_at')
            ->whereNotNull('completed_at')
            ->selectRaw('avg(timestampdiff(second, created_at, completed_at)) as seconds')
            ->value('seconds');

        return $seconds === null ? null : round(((float) $seconds) / 3600, 1);
    }
}
