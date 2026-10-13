<?php

namespace App\Models;

use Database\Factories\KpiTemplateFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * What a team's KPIs usually are, so a month can be generated instead of retyped for everyone.
 *
 * A template without a team is the fallback for people whose team has none of its own.
 *
 * @property int $id
 * @property int|null $team_id
 * @property string $name
 * @property bool $is_active
 */
#[Fillable(['team_id', 'name', 'is_active'])]
class KpiTemplate extends Model
{
    /** @use HasFactory<KpiTemplateFactory> */
    use HasFactory;

    protected function casts(): array
    {
        return ['is_active' => 'boolean'];
    }

    /** @return BelongsTo<Team, $this> */
    public function team(): BelongsTo
    {
        return $this->belongsTo(Team::class);
    }

    /** @return HasMany<KpiTemplateItem, $this> */
    public function items(): HasMany
    {
        return $this->hasMany(KpiTemplateItem::class)->orderBy('sort_order')->orderBy('id');
    }

    /** @param Builder<self> $query */
    public function scopeActive(Builder $query): Builder
    {
        return $query->where('is_active', true);
    }
}
