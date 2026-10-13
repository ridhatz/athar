<?php

namespace App\Http\Controllers\Kpi;

use App\Enums\KpiStatus;
use App\Enums\RoleCode;
use App\Http\Controllers\Controller;
use App\Models\Department;
use App\Models\Kpi;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;

/**
 * The "Analisis KPI" screen.
 *
 * Every number here comes from the same query the list screen uses, narrowed the same way, so a
 * figure on a card always matches what you find when you click through to it.
 */
class KpiAnalysisController extends Controller
{
    public function index(Request $request): Response
    {
        $user = $request->user();
        Gate::forUser($user)->authorize('viewAny', Kpi::class);

        $year = (int) ($request->query('year') ?: now()->year);
        $employee = $request->query('employee');
        $department = $request->query('department');
        $period = $request->query('period');

        $base = fn () => Kpi::query()
            ->tap(fn (Builder $q) => $this->visibleTo($q, $user))
            ->whereYear('period', $year)
            ->when(is_numeric($employee), fn (Builder $q) => $q->where('user_id', (int) $employee))
            ->when(is_numeric($department), fn (Builder $q) => $q->whereHas('user',
                fn ($u) => $u->where('department_id', (int) $department)))
            ->when(is_string($period) && preg_match('/^\d{4}-\d{2}$/', $period),
                fn (Builder $q) => $q->forPeriod($period));

        $scored = (clone $base())->whereNotNull('final_score');

        $byStatus = (clone $base())
            ->selectRaw('status, COUNT(*) as total')
            ->groupBy('status')
            ->pluck('total', 'status');

        return Inertia::render('kpi/analysis', [
            'filters' => [
                'year' => $year,
                'employee' => is_numeric($employee) ? (int) $employee : '',
                'department' => is_numeric($department) ? (int) $department : '',
                'period' => is_string($period) ? $period : '',
            ],
            'stats' => [
                'total' => (clone $base())->count(),
                'people' => (clone $base())->distinct('user_id')->count('user_id'),
                'average' => round((float) (clone $scored)->avg('final_score'), 2),
                'highest' => round((float) (clone $scored)->max('final_score'), 2),
                'lowest' => round((float) (clone $scored)->min('final_score'), 2),
                'pending' => (int) ($byStatus[KpiStatus::Pending->value] ?? 0),
                'awaiting_review' => (int) ($byStatus[KpiStatus::Submitted->value] ?? 0),
                'reviewed' => (int) ($byStatus[KpiStatus::Reviewed->value] ?? 0),
                'revision' => (int) ($byStatus[KpiStatus::Revision->value] ?? 0),
            ],
            // Twelve points whether or not every month has data, so the line keeps its shape.
            'trend' => $this->trend($base(), $year),
            'distribution' => collect(KpiStatus::values())
                ->map(fn (string $s) => ['status' => $s, 'total' => (int) ($byStatus[$s] ?? 0)])
                ->values(),
            'years' => $this->years($user),
            'employees' => User::query()->where('is_active', true)
                ->whereHas('roles', fn ($r) => $r->whereIn('code', [RoleCode::Staff->value, RoleCode::TeamLead->value]))
                ->orderBy('name')->get(['id', 'name']),
            'departments' => Department::query()->orderBy('name')->get(['id', 'name']),
        ]);
    }

    /**
     * Average score per month for the year.
     *
     * @param  Builder<Kpi>  $query
     * @return array<int, array{month: int, average: float|null}>
     */
    private function trend(Builder $query, int $year): array
    {
        $byMonth = $query->clone()
            ->whereNotNull('final_score')
            ->selectRaw('MONTH(period) as m, AVG(final_score) as avg_score')
            ->groupBy('m')
            ->pluck('avg_score', 'm');

        return collect(range(1, 12))
            ->map(fn (int $m) => [
                'month' => $m,
                // Null, not zero: a month nobody has been scored in is unknown, not a bad month.
                'average' => isset($byMonth[$m]) ? round((float) $byMonth[$m], 1) : null,
            ])
            ->all();
    }

    /** @return array<int, int> */
    private function years(User $user): array
    {
        $years = Kpi::query()
            ->tap(fn (Builder $q) => $this->visibleTo($q, $user))
            ->selectRaw('DISTINCT YEAR(period) as y')
            ->orderByDesc('y')
            ->pluck('y')
            ->map(fn ($y) => (int) $y)
            ->all();

        return $years === [] ? [now()->year] : $years;
    }

    /** @param Builder<Kpi> $query */
    private function visibleTo(Builder $query, User $user): void
    {
        if ($user->isAdmin()) {
            return;
        }

        if (in_array(RoleCode::TeamLead, $user->roleCodes(), true)) {
            $teamIds = $user->teamIds();
            $query->where(fn (Builder $q) => $q
                ->where('user_id', $user->id)
                ->orWhereHas('user.teams', fn ($t) => $t->whereIn('teams.id', $teamIds)));

            return;
        }

        $query->where('user_id', $user->id);
    }
}
