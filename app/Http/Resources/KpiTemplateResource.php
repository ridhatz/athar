<?php

namespace App\Http\Resources;

use App\Models\KpiTemplate;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** A team's KPI blueprint as the administrator edits it. */
class KpiTemplateResource extends JsonResource
{
    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        /** @var KpiTemplate $template */
        $template = $this->resource;
        $items = $template->relationLoaded('items') ? $template->items : collect();

        return [
            'id' => $template->id,
            'name' => $template->name,
            'team' => $template->team ? ['id' => $template->team->id, 'name' => $template->team->name] : null,
            'is_active' => $template->is_active,
            // Shown on the list so a blueprint that does not add up to 100 is obvious at a glance.
            'total_weight' => round((float) $items->sum('weight'), 2),
            'items' => $items->map(fn ($item) => [
                'name' => $item->name,
                'target_value' => (float) $item->target_value,
                'direction' => $item->direction->value,
                'unit' => $item->unit,
                'weight' => (float) $item->weight,
                'frequency' => $item->frequency->value,
            ])->values(),
        ];
    }
}
