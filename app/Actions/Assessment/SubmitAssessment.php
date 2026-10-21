<?php

namespace App\Actions\Assessment;

use App\Models\Assessment;
use App\Models\User;
use App\Services\AssessmentScoring;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;

/**
 * Records one submission of the Communication Behavior Assessment and scores it.
 *
 * Always a new row, never an update — a retake does not erase the last one (see Assessment's
 * class docs), so there is nothing here to freeze or overwrite, unlike KPI achievements.
 */
final class SubmitAssessment
{
    public function __construct(private readonly AssessmentScoring $scoring) {}

    /**
     * @param  array<int, array{most: int, least: int}>  $answers
     */
    public function handle(User $actor, array $answers): Assessment
    {
        Gate::forUser($actor)->authorize('create', Assessment::class);

        $result = $this->scoring->score($answers);

        return DB::transaction(fn () => Assessment::query()->create([
            'user_id' => $actor->id,
            'answers' => $answers,
            ...$result,
            'submitted_at' => now(),
        ]));
    }
}
