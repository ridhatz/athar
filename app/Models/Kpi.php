<?php

namespace App\Models;

use App\Enums\KpiStatus;
use Database\Factories\KpiFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

/**
 * One employee's KPI sheet for one month.
 *
 * @property int $id
 * @property int $user_id
 * @property Carbon $period
 * @property Carbon|null $due_date
 * @property KpiStatus $status
 * @property string|null $final_score
 * @property string|null $review_note
 */
#[Fillable(['user_id', 'period', 'due_date', 'status', 'created_by'])]
class Kpi extends Model
{
    /** @use HasFactory<KpiFactory> */
    use HasFactory;

    protected function casts(): array
    {
        return [
            'period' => 'date',
            'due_date' => 'date',
            'status' => KpiStatus::class,
            'submitted_at' => 'datetime',
            'reviewed_at' => 'datetime',
        ];
    }

    /** @return BelongsTo<User, $this> */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /** @return BelongsTo<User, $this> */
    public function reviewer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reviewed_by');
    }

    /** @return HasMany<KpiItem, $this> */
    public function items(): HasMany
    {
        return $this->hasMany(KpiItem::class)->orderBy('sort_order')->orderBy('id');
    }

    /** The month this sheet covers, as "2026-09". */
    public function periodKey(): string
    {
        return $this->period->format('Y-m');
    }

    /** @param Builder<self> $query */
    public function scopeForPeriod(Builder $query, string $period): Builder
    {
        return $query->whereYear('period', substr($period, 0, 4))
            ->whereMonth('period', substr($period, 5, 2));
    }
}
