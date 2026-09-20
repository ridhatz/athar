<?php

namespace App\Queries;

use App\Enums\RequestStatus;
use App\Enums\RoleCode;
use App\Models\ServiceRequest;
use App\Models\User;

/**
 * Who is working on what, and how loaded they are. Open to every signed-in person, so it
 * shows only COUNTS and CATEGORY NAMES ("Website, Design"), never request titles or numbers:
 * transparency about capacity must not leak what other people asked for.
 *
 * The colour is a hint for whoever assigns work. It never stops anyone being given more.
 */
final class WorkloadBoard
{
    /** green, yellow, orange, or red for a number of open tasks. */
    public function level(int $openTasks): string
    {
        $config = config('athar.workload');

        return match (true) {
            $openTasks >= $config['red_from'] => 'red',
            $openTasks >= $config['orange_from'] => 'orange',
            $openTasks >= $config['yellow_from'] => 'yellow',
            default => 'green',
        };
    }

    /**
     * Everyone who does work, busiest first.
     *
     * @return array<int, array<string, mixed>>
     */
    public function board(): array
    {
        $people = User::query()
            ->where('is_active', true)
            ->whereHas('roles', fn ($q) => $q->whereIn('code', [RoleCode::Staff->value, RoleCode::TeamLead->value]))
            ->with('teams:id,name')
            ->orderBy('name')
            ->get();

        $ids = $people->pluck('id')->all();
        $categoryName = app()->getLocale() === 'en' ? 'name_en' : 'name_id';

        $open = ServiceRequest::query()
            ->whereIn('requests.assigned_user_id', $ids)
            ->whereNotIn('requests.status', $this->closed())
            ->join('request_categories', 'request_categories.id', '=', 'requests.category_id')
            ->selectRaw("requests.assigned_user_id as user_id, request_categories.{$categoryName} as category, count(*) as n")
            ->groupBy('requests.assigned_user_id', "request_categories.{$categoryName}")
            ->orderByDesc('n')
            ->get()
            ->groupBy('user_id');

        $done = ServiceRequest::query()
            ->whereIn('assigned_user_id', $ids)
            ->where('status', RequestStatus::Completed->value)
            ->where('completed_at', '>=', now()->subDays(30))
            ->selectRaw('assigned_user_id as user_id, count(*) as n')
            ->groupBy('assigned_user_id')
            ->pluck('n', 'user_id');

        $rows = $people->map(function (User $user) use ($open, $done) {
            $mine = $open->get($user->id, collect());
            $count = (int) $mine->sum('n');

            return [
                'id' => $user->id,
                'name' => $user->name,
                'teams' => $user->teams->pluck('name')->values()->all(),
                'open' => $count,
                'level' => $this->level($count),
                'working_on' => $mine->map(fn ($row) => ['category' => $row->category, 'count' => (int) $row->n])->values()->all(),
                'completed_30d' => (int) ($done[$user->id] ?? 0),
            ];
        })->all();

        usort($rows, fn ($a, $b) => [$b['open'], $a['name']] <=> [$a['open'], $b['name']]);

        return array_map(fn (array $row, int $i) => ['rank' => $i + 1] + $row, $rows, array_keys($rows));
    }

    /**
     * Open-task count and colour for some people, for the assignment picker.
     *
     * @param  array<int, int>  $userIds
     * @return array<int, array{open: int, level: string}>
     */
    public function loadFor(array $userIds): array
    {
        $counts = ServiceRequest::query()
            ->whereIn('assigned_user_id', $userIds)
            ->whereNotIn('status', $this->closed())
            ->selectRaw('assigned_user_id as user_id, count(*) as n')
            ->groupBy('assigned_user_id')
            ->pluck('n', 'user_id');

        $load = [];

        foreach ($userIds as $id) {
            $n = (int) ($counts[$id] ?? 0);
            $load[$id] = ['open' => $n, 'level' => $this->level($n)];
        }

        return $load;
    }

    /** @return array<int, string> */
    private function closed(): array
    {
        return [RequestStatus::Completed->value, RequestStatus::Cancelled->value];
    }
}
