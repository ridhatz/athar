<?php

namespace App\Http\Controllers\Admin;

use App\Actions\Admin\CreateTeam;
use App\Actions\Admin\UpdateTeam;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\StoreTeamRequest;
use App\Http\Requests\Admin\UpdateTeamRequest;
use App\Http\Resources\TeamAdminResource;
use App\Models\Team;
use App\Services\ReferenceRules;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;

/** The "Teams" screen. ADMIN only; the rules live in the Actions and TeamPolicy. */
class TeamPageController extends Controller
{
    public function index(Request $request, ReferenceRules $rules): Response
    {
        Gate::forUser($request->user())->authorize('viewAny', Team::class);

        $q = trim((string) $request->query('q', ''));

        $teams = Team::query()
            ->withCount('members')
            ->when($q !== '', function ($query) use ($q) {
                $like = '%'.addcslashes($q, '%_\\').'%';
                $query->where(fn ($w) => $w->where('name', 'like', $like)->orWhere('description', 'like', $like));
            })
            ->orderByDesc('is_active')
            ->orderBy('name')
            ->paginate(20)
            ->withQueryString();

        // Teams are few, so asking per row is cheap, and it lets the screen explain a refusal
        // before the admin runs into it.
        $teams->getCollection()->each(function (Team $team) use ($rules) {
            $team->strandedMembers = $team->is_active ? $rules->membersStrandedBy($team) : [];
        });

        return Inertia::render('admin/teams', [
            'teams' => TeamAdminResource::collection($teams),
            'filters' => ['q' => $q],
        ]);
    }

    public function store(StoreTeamRequest $request, CreateTeam $create): RedirectResponse
    {
        $team = $create->handle($request->user(), $request->validated());

        Inertia::flash('toast', ['type' => 'success', 'message' => __('admin.flash.team_created', ['name' => $team->name])]);

        return back();
    }

    public function update(UpdateTeamRequest $request, Team $team, UpdateTeam $update): RedirectResponse
    {
        $update->handle($request->user(), $team, $request->validated());

        Inertia::flash('toast', ['type' => 'success', 'message' => __('admin.flash.updated')]);

        return back();
    }
}
