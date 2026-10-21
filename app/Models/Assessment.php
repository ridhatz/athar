<?php

namespace App\Models;

use App\Enums\AssessmentRecommendation;
use Database\Factories\AssessmentFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * One submission of the Communication Behavior Assessment. Never edited after the fact: a retake
 * is a new row, so "someone's result" always means their latest one (see scopeLatestPerUser).
 *
 * @property int $id
 * @property int $user_id
 * @property array<int, array{most: int, least: int}> $answers
 * @property int $score_d
 * @property int $score_i
 * @property int $score_s
 * @property int $score_c
 * @property string $dominant_style
 * @property int $sales_index
 * @property int $marketing_index
 * @property AssessmentRecommendation $recommendation
 * @property Carbon $submitted_at
 */
#[Fillable(['user_id', 'answers', 'score_d', 'score_i', 'score_s', 'score_c', 'dominant_style', 'sales_index', 'marketing_index', 'recommendation', 'submitted_at'])]
class Assessment extends Model
{
    /** @use HasFactory<AssessmentFactory> */
    use HasFactory;

    protected function casts(): array
    {
        return [
            'answers' => 'array',
            'recommendation' => AssessmentRecommendation::class,
            'submitted_at' => 'datetime',
        ];
    }

    /** @return BelongsTo<User, $this> */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /** @return array{D: int, I: int, S: int, C: int} */
    public function scores(): array
    {
        return ['D' => $this->score_d, 'I' => $this->score_i, 'S' => $this->score_s, 'C' => $this->score_c];
    }

    /**
     * Only the most recent submission per person, newest first — the shape both the employee's
     * own result and the admin roster want, so the query lives here once instead of twice.
     *
     * @param  Builder<self>  $query
     * @return Builder<self>
     */
    public function scopeLatestPerUser(Builder $query): Builder
    {
        return $query->whereIn('id', function ($sub) {
            $sub->selectRaw('MAX(id)')->from('assessments')->groupBy('user_id');
        })->orderByDesc('submitted_at');
    }
}
