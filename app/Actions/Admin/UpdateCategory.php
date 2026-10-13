<?php

namespace App\Actions\Admin;

use App\Concerns\RecordsReferenceAudit;
use App\Models\RequestCategory;
use App\Models\User;
use App\Services\AdminAudit;
use App\Services\ReferenceRules;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;

/**
 * Changes the names, the form hints, the order, or whether a category is still offered.
 *
 * The slug is deliberately absent: it was fixed when the category was created. Switching a
 * category off keeps it on the requests already filed under it, but it stops being offered on
 * the form — and the last active one cannot be switched off, or nobody could file anything.
 */
final class UpdateCategory
{
    use RecordsReferenceAudit;

    public function __construct(
        private readonly ReferenceRules $rules,
        private readonly AdminAudit $audit,
    ) {}

    /**
     * @param  array{name_id?: string, name_en?: string, guidance_id?: string|null, guidance_en?: string|null, sort_order?: int, is_active?: bool}  $data
     */
    public function handle(User $actor, RequestCategory $category, array $data): RequestCategory
    {
        Gate::forUser($actor)->authorize('update', $category);

        return DB::transaction(function () use ($actor, $category, $data) {
            $category = RequestCategory::query()->whereKey($category->id)->lockForUpdate()->firstOrFail();

            $old = $this->snapshot($category);

            if (($data['is_active'] ?? $category->is_active) === false && $category->is_active) {
                $this->rules->assertCategoryCanBeDeactivated($category);
            }

            foreach (['name_id', 'name_en'] as $field) {
                if (isset($data[$field])) {
                    $category->{$field} = trim($data[$field]);
                }
            }

            foreach (['guidance_id', 'guidance_en'] as $field) {
                if (array_key_exists($field, $data)) {
                    $guidance = trim((string) $data[$field]);
                    $category->{$field} = $guidance === '' ? null : $guidance;
                }
            }

            if (isset($data['sort_order'])) {
                $category->sort_order = $data['sort_order'];
            }
            $category->is_active = $data['is_active'] ?? $category->is_active;
            $category->save();

            $this->recordChange(
                $this->audit, $actor, $category, $old, $this->snapshot($category),
                'CATEGORY_UPDATED', 'CATEGORY_ACTIVATED', 'CATEGORY_DEACTIVATED',
            );

            return $category;
        });
    }

    /** @return array<string, mixed> */
    private function snapshot(RequestCategory $category): array
    {
        return [
            'name_id' => $category->name_id,
            'name_en' => $category->name_en,
            'guidance_id' => $category->guidance_id,
            'guidance_en' => $category->guidance_en,
            'sort_order' => (int) $category->sort_order,
            'is_active' => (bool) $category->is_active,
        ];
    }
}
