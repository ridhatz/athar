<?php

namespace App\Http\Resources;

use App\Models\Kpi;
use App\Services\KpiScoring;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Facades\Gate;

/**
 * A KPI sheet as the screen needs it, including what the signed-in person is allowed to do with
 * it — the UI only shows or hides; the server decides.
 */
class KpiResource extends JsonResource
{
    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        /** @var Kpi $kpi */
        $kpi = $this->resource;
        $user = $request->user();
        $items = $kpi->relationLoaded('items') ? $kpi->items : collect();

        return [
            'id' => $kpi->id,
            'user' => $kpi->user ? [
                'id' => $kpi->user->id,
                'name' => $kpi->user->name,
                'teams' => $kpi->user->relationLoaded('teams')
                    ? $kpi->user->teams->pluck('name')->values()
                    : [],
                'department' => $kpi->user->department?->name,
            ] : null,
            'period' => $kpi->periodKey(),
            'period_start' => $kpi->period->toDateString(),
            'period_end' => $kpi->period->copy()->endOfMonth()->toDateString(),
            'due_date' => $kpi->due_date?->toDateString(),
            'status' => $kpi->status->value,
            'final_score' => $kpi->final_score === null ? null : (float) $kpi->final_score,
            'progress' => app(KpiScoring::class)->progress($items),
            'review_note' => $kpi->review_note,
            'reviewer' => $kpi->reviewer?->name,
            'reviewed_at' => $kpi->reviewed_at?->toIso8601String(),
            'submitted_at' => $kpi->submitted_at?->toIso8601String(),
            'items' => KpiItemResource::collection($items),
            'can' => $user === null ? [] : [
                'fill' => Gate::forUser($user)->allows('fill', $kpi),
                'review' => Gate::forUser($user)->allows('review', $kpi),
                'update' => Gate::forUser($user)->allows('update', $kpi),
            ],
        ];
    }
}
