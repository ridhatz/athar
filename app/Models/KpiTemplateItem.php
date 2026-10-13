<?php

namespace App\Models;

use App\Enums\KpiDirection;
use App\Enums\KpiFrequency;
use Database\Factories\KpiTemplateItemFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * One line of a template: everything a KPI needs except the achievement, which only exists once
 * the month is real.
 *
 * @property int $id
 * @property int $kpi_template_id
 * @property string $name
 * @property string $target_value
 * @property KpiDirection $direction
 * @property string|null $unit
 * @property string $weight
 * @property KpiFrequency $frequency
 */
#[Fillable(['kpi_template_id', 'name', 'target_value', 'direction', 'unit', 'weight', 'frequency', 'sort_order'])]
class KpiTemplateItem extends Model
{
    /** @use HasFactory<KpiTemplateItemFactory> */
    use HasFactory;

    protected function casts(): array
    {
        return [
            'direction' => KpiDirection::class,
            'frequency' => KpiFrequency::class,
        ];
    }

    /** @return BelongsTo<KpiTemplate, $this> */
    public function template(): BelongsTo
    {
        return $this->belongsTo(KpiTemplate::class, 'kpi_template_id');
    }
}
