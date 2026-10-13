<?php

namespace App\Http\Resources;

use App\Models\RequestCategory;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * A category as the administrator sees it: both languages side by side, because the admin edits
 * the pair rather than whatever the current UI language happens to be.
 */
class CategoryAdminResource extends JsonResource
{
    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        /** @var RequestCategory $category */
        $category = $this->resource;

        return [
            'id' => $category->id,
            'slug' => $category->slug,
            'name_id' => $category->name_id,
            'name_en' => $category->name_en,
            'guidance_id' => $category->guidance_id,
            'guidance_en' => $category->guidance_en,
            'sort_order' => $category->sort_order,
            'is_active' => $category->is_active,
            'requests_count' => $category->requests_count ?? $category->requests()->count(),
            'created_at' => $category->created_at?->toIso8601String(),
        ];
    }
}
