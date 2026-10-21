<?php

namespace App\Http\Resources;

use App\Models\Assessment;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** One assessment result as a screen needs it — never includes the raw 24 answers. */
class AssessmentResource extends JsonResource
{
    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        /** @var Assessment $assessment */
        $assessment = $this->resource;

        return [
            'id' => $assessment->id,
            'user' => $assessment->user ? [
                'id' => $assessment->user->id,
                'name' => $assessment->user->name,
            ] : null,
            'scores' => $assessment->scores(),
            'dominant_style' => $assessment->dominant_style,
            'sales_index' => $assessment->sales_index,
            'marketing_index' => $assessment->marketing_index,
            'recommendation' => $assessment->recommendation->value,
            'submitted_at' => $assessment->submitted_at->toIso8601String(),
        ];
    }
}
