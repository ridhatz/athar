<?php

namespace App\Http\Resources;

use App\Models\KpiItem;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * One line of a KPI sheet.
 *
 * The raw numbers travel alongside the formatted labels: the screen shows "<90%" but the input
 * box has to edit 90, and the two must never drift apart.
 */
class KpiItemResource extends JsonResource
{
    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        /** @var KpiItem $item */
        $item = $this->resource;

        return [
            'id' => $item->id,
            'name' => $item->name,
            'target_value' => (float) $item->target_value,
            'target_label' => $item->targetLabel(),
            'direction' => $item->direction->value,
            'unit' => $item->unit,
            'weight' => (float) $item->weight,
            'frequency' => $item->frequency->value,
            'achievement' => $item->achievement === null ? null : (float) $item->achievement,
            'achievement_label' => $item->achievementLabel(),
            'score' => $item->score === null ? null : (float) $item->score,
        ];
    }
}
