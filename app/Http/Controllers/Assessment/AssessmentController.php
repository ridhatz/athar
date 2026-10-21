<?php

namespace App\Http\Controllers\Assessment;

use App\Actions\Assessment\SubmitAssessment;
use App\Http\Controllers\Controller;
use App\Http\Requests\Assessment\SubmitAssessmentRequest;
use App\Http\Resources\AssessmentResource;
use App\Models\Assessment;
use App\Models\User;
use App\Support\AssessmentQuestions;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;

/** The one "Asesmen" screen: take your own, see your own result, and — for an admin — everyone's. */
class AssessmentController extends Controller
{
    public function index(Request $request): Response
    {
        $user = $request->user();
        Gate::forUser($user)->authorize('viewAny', Assessment::class);

        $canViewAll = Gate::forUser($user)->allows('viewAll', Assessment::class);

        $myLatest = Assessment::query()
            ->where('user_id', $user->id)
            ->latest('submitted_at')
            ->first();

        return Inertia::render('assessment/index', [
            'questions' => AssessmentQuestions::all(),
            // ->resolve() so this lands as a plain object; a bare Resource embedded in Inertia
            // props still auto-wraps in {"data": …} otherwise, and the page reads fields off it
            // directly (myLatest.scores.C, not myLatest.data.scores.C).
            'myLatest' => $myLatest ? (new AssessmentResource($myLatest))->resolve() : null,
            'canViewAll' => $canViewAll,
            'all' => $canViewAll
                ? AssessmentResource::collection(
                    Assessment::query()->with('user')->latestPerUser()->paginate(20)
                )
                : null,
            'stats' => $canViewAll ? [
                'eligible' => User::query()->where('is_active', true)->count(),
                'submitted' => Assessment::query()->distinct('user_id')->count('user_id'),
            ] : null,
        ]);
    }

    public function store(SubmitAssessmentRequest $request, SubmitAssessment $submit): RedirectResponse
    {
        $submit->handle($request->user(), $request->validated('answers'));

        Inertia::flash('toast', ['type' => 'success', 'message' => __('assessment.flash.submitted')]);

        return back();
    }
}
