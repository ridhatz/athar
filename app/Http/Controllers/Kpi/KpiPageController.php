<?php

namespace App\Http\Controllers\Kpi;

use App\Actions\Kpi\CreateKpi;
use App\Actions\Kpi\GenerateKpiFromTemplate;
use App\Actions\Kpi\ReviewKpi;
use App\Actions\Kpi\SubmitKpi;
use App\Actions\Kpi\UpdateKpiAchievements;
use App\Enums\KpiStatus;
use App\Enums\RoleCode;
use App\Http\Controllers\Controller;
use App\Http\Requests\Kpi\FillKpiRequest;
use App\Http\Requests\Kpi\ReviewKpiRequest;
use App\Http\Requests\Kpi\StoreKpiRequest;
use App\Http\Resources\KpiResource;
use App\Models\Kpi;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;

/** The "Manajemen KPI" screen: whose sheets you may see, and what you may do to them. */
class KpiPageController extends Controller
{
    public function index(Request $request): Response
    {
        $user = $request->user();
        Gate::forUser($user)->authorize('viewAny', Kpi::class);

        $q = trim((string) $request->query('q', ''));
        $status = $request->query('status');
        $period = $request->query('period');

        $kpis = Kpi::query()
            ->with(['user.teams', 'user.department', 'items', 'reviewer'])
            ->tap(fn (Builder $query) => $this->visibleTo($query, $user))
            ->when($q !== '', function (Builder $query) use ($q) {
                $like = '%'.addcslashes($q, '%_\\').'%';
                $query->whereHas('user', fn ($u) => $u->where('name', 'like', $like));
            })
            ->when(in_array($status, KpiStatus::values(), true), fn (Builder $query) => $query->where('status', $status))
            ->when(is_string($period) && preg_match('/^\d{4}-\d{2}$/', $period), fn (Builder $query) => $query->forPeriod($period))
            ->orderByDesc('period')
            ->orderBy('id')
            ->paginate(20)
            ->withQueryString();

        return Inertia::render('kpi/index', [
            'kpis' => KpiResource::collection($kpis),
            'filters' => [
                'q' => $q,
                'status' => in_array($status, KpiStatus::values(), true) ? $status : '',
                'period' => is_string($period) ? $period : '',
            ],
            'statuses' => KpiStatus::values(),
            'periods' => $this->periods($user),
            // Only an administrator opens sheets or generates a month.
            'canCreate' => Gate::forUser($user)->allows('create', Kpi::class),
            'employees' => Gate::forUser($user)->allows('create', Kpi::class)
                ? User::query()->where('is_active', true)
                    ->whereHas('roles', fn ($r) => $r->whereIn('code', [RoleCode::Staff->value, RoleCode::TeamLead->value]))
                    ->orderBy('name')->get(['id', 'name'])
                : [],
        ]);
    }

    public function store(StoreKpiRequest $request, CreateKpi $create): RedirectResponse
    {
        $kpi = $create->handle($request->user(), $request->validated());

        Inertia::flash('toast', ['type' => 'success', 'message' => __('kpi.flash.created', ['name' => $kpi->user->name])]);

        return back();
    }

    public function fill(FillKpiRequest $request, Kpi $kpi, UpdateKpiAchievements $update): RedirectResponse
    {
        $update->handle($request->user(), $kpi, $request->validated('achievements'));

        Inertia::flash('toast', ['type' => 'success', 'message' => __('kpi.flash.saved')]);

        return back();
    }

    public function submit(Request $request, Kpi $kpi, SubmitKpi $submit): RedirectResponse
    {
        $submit->handle($request->user(), $kpi);

        Inertia::flash('toast', ['type' => 'success', 'message' => __('kpi.flash.submitted')]);

        return back();
    }

    public function review(ReviewKpiRequest $request, Kpi $kpi, ReviewKpi $review): RedirectResponse
    {
        $approved = $request->boolean('approved');
        $review->handle($request->user(), $kpi, $approved, $request->validated('review_note'));

        Inertia::flash('toast', [
            'type' => 'success',
            'message' => __($approved ? 'kpi.flash.approved' : 'kpi.flash.returned'),
        ]);

        return back();
    }

    public function generate(Request $request, GenerateKpiFromTemplate $generate): RedirectResponse
    {
        $validated = $request->validate([
            'period' => ['required', 'string', 'regex:/^\d{4}-(0[1-9]|1[0-2])$/'],
            'due_date' => ['nullable', 'date'],
        ]);

        $result = $generate->handle($request->user(), $validated['period'], $validated['due_date'] ?? null);

        Inertia::flash('toast', [
            'type' => $result['created'] === 0 ? 'info' : 'success',
            'message' => __('kpi.flash.generated', ['created' => $result['created'], 'skipped' => $result['skipped']]),
        ]);

        return back();
    }

    /**
     * Narrows the list to what this person is entitled to see: an administrator sees everyone, a
     * team lead sees their teams, and everybody else sees only their own sheet.
     *
     * @param  Builder<Kpi>  $query
     */
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

    /**
     * The months that actually have sheets, newest first, for the period filter.
     *
     * @return array<int, string>
     */
    private function periods(User $user): array
    {
        return Kpi::query()
            ->tap(fn (Builder $query) => $this->visibleTo($query, $user))
            ->selectRaw("DATE_FORMAT(period, '%Y-%m') as p")
            ->distinct()
            ->orderByDesc('p')
            ->pluck('p')
            ->all();
    }
}
