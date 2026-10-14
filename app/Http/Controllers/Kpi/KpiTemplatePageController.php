<?php

namespace App\Http\Controllers\Kpi;

use App\Actions\Kpi\SaveKpiTemplate;
use App\Http\Controllers\Controller;
use App\Http\Requests\Kpi\SaveKpiTemplateRequest;
use App\Http\Resources\KpiTemplateResource;
use App\Models\KpiTemplate;
use App\Models\Team;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;

/** The "Template KPI" screen. ADMIN only; the rules live in the Action and KpiTemplatePolicy. */
class KpiTemplatePageController extends Controller
{
    public function index(Request $request): Response
    {
        Gate::forUser($request->user())->authorize('viewAny', KpiTemplate::class);

        return Inertia::render('kpi/templates', [
            // resolve() so the screen gets a plain array; a bare collection would arrive wrapped
            // in {"data": …} and the page would try to map over an object.
            'templates' => KpiTemplateResource::collection(
                KpiTemplate::query()->with(['team', 'items'])->orderBy('name')->get()
            )->resolve(),
            // Teams that already have a blueprint are offered too, so an existing one can be moved.
            'teams' => Team::query()->where('is_active', true)->orderBy('name')->get(['id', 'name']),
        ]);
    }

    public function store(SaveKpiTemplateRequest $request, SaveKpiTemplate $save): RedirectResponse
    {
        $template = $save->handle($request->user(), null, $request->validated());

        Inertia::flash('toast', ['type' => 'success', 'message' => __('kpi.flash.template_saved', ['name' => $template->name])]);

        return back();
    }

    public function update(SaveKpiTemplateRequest $request, KpiTemplate $template, SaveKpiTemplate $save): RedirectResponse
    {
        $saved = $save->handle($request->user(), $template, $request->validated());

        Inertia::flash('toast', ['type' => 'success', 'message' => __('kpi.flash.template_saved', ['name' => $saved->name])]);

        return back();
    }
}
