<?php

namespace App\Models;

use App\Enums\KpiDirection;
use App\Enums\KpiFrequency;
use Database\Factories\KpiItemFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * One line on a KPI sheet: what the target is, which way it should move, how much it is worth,
 * and what the employee reported.
 *
 * @property int $id
 * @property int $kpi_id
 * @property string $name
 * @property string $target_value
 * @property KpiDirection $direction
 * @property string|null $unit
 * @property string $weight
 * @property KpiFrequency $frequency
 * @property string|null $achievement
 * @property string|null $score
 */
#[Fillable(['kpi_id', 'name', 'target_value', 'direction', 'unit', 'weight', 'frequency', 'achievement', 'sort_order'])]
class KpiItem extends Model
{
    /** @use HasFactory<KpiItemFactory> */
    use HasFactory;

    protected function casts(): array
    {
        return [
            'direction' => KpiDirection::class,
            'frequency' => KpiFrequency::class,
        ];
    }

    /** @return BelongsTo<Kpi, $this> */
    public function kpi(): BelongsTo
    {
        return $this->belongsTo(Kpi::class);
    }

    /** How the target reads on screen: "<90%", "55.000", "1.470.000.000". */
    public function targetLabel(): string
    {
        return $this->direction->prefix().$this->formatted($this->target_value);
    }

    /** How the achievement reads, or a dash when nothing has been reported. */
    public function achievementLabel(): string
    {
        return $this->achievement === null ? '—' : $this->formatted($this->achievement);
    }

    /** Trims the trailing zeros the decimal column keeps, then appends the unit. */
    private function formatted(string $value): string
    {
        $number = (float) $value;
        $text = $number == (int) $number
            ? number_format($number, 0, ',', '.')
            : rtrim(rtrim(number_format($number, 4, ',', '.'), '0'), ',');

        return $text.($this->unit ?? '');
    }
}
