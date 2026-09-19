<?php

namespace App\Http\Controllers\Api\V1;

use App\Enums\RoleCode;
use App\Http\Controllers\Controller;
use App\Models\RequestCategory;
use App\Models\Team;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/** Small reference lists the clients need to build forms and pickers. */
class LookupController extends Controller
{
    public function categories(): JsonResponse
    {
        $categories = RequestCategory::query()->active()->orderBy('sort_order')->get()
            ->map(fn (RequestCategory $c) => [
                'id' => $c->id,
                'slug' => $c->slug,
                'name' => $c->label(),
                'guidance' => $c->guidance(),
            ]);

        return response()->json(['data' => $categories]);
    }

    public function teams(): JsonResponse
    {
        $teams = Team::query()->where('is_active', true)->orderBy('name')->get(['id', 'name', 'description']);

        return response()->json(['data' => $teams]);
    }

    /** People who can be given work. Only those who assign work may list them. */
    public function users(Request $request): JsonResponse
    {
        $viewer = $request->user();

        abort_unless($viewer->hasAnyRole(RoleCode::Admin, RoleCode::TeamLead), 403);

        $request->validate(['team_id' => ['nullable', 'integer']]);

        $users = User::query()
            ->where('is_active', true)
            ->whereHas('roles', fn ($q) => $q->whereIn('code', [RoleCode::Staff->value, RoleCode::TeamLead->value]))
            ->when($request->integer('team_id'), fn ($q, $teamId) => $q->whereHas('teams', fn ($t) => $t->whereKey($teamId)))
            ->with('teams:id')
            ->orderBy('name')
            ->get()
            ->map(fn (User $u) => [
                'id' => $u->id,
                'name' => $u->name,
                'team_ids' => $u->teams->pluck('id')->values(),
            ]);

        return response()->json(['data' => $users]);
    }
}
