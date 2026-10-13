<?php

namespace App\Actions\Admin;

use App\Models\RequestCategory;
use App\Models\User;
use App\Services\AdminAudit;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Str;

/**
 * Adds a kind of request people can file.
 *
 * The slug is derived from the Indonesian name once, here, and then never changes: it is the
 * stable handle that code, seeders and integrations use, while the displayed names stay editable.
 */
final class CreateCategory
{
    /** `request_categories.slug` is a varchar(60). */
    private const SLUG_LIMIT = 60;

    public function __construct(private readonly AdminAudit $audit) {}

    /**
     * @param  array{name_id: string, name_en: string, guidance_id?: string|null, guidance_en?: string|null, sort_order?: int|null}  $data
     */
    public function handle(User $actor, array $data): RequestCategory
    {
        Gate::forUser($actor)->authorize('create', RequestCategory::class);

        return DB::transaction(function () use ($actor, $data) {
            $category = RequestCategory::query()->create([
                'slug' => $this->uniqueSlug($data['name_id']),
                'name_id' => trim($data['name_id']),
                'name_en' => trim($data['name_en']),
                'guidance_id' => $this->cleaned($data['guidance_id'] ?? null),
                'guidance_en' => $this->cleaned($data['guidance_en'] ?? null),
                'sort_order' => $data['sort_order'] ?? $this->nextSortOrder(),
            ]);

            $this->audit->record($actor, $category, 'CATEGORY_CREATED', null, [
                'slug' => $category->slug,
                'name_id' => $category->name_id,
                'name_en' => $category->name_en,
                'sort_order' => $category->sort_order,
            ]);

            return $category->fresh();
        });
    }

    /** Puts a new category at the end of the list unless the admin placed it. */
    private function nextSortOrder(): int
    {
        return ((int) RequestCategory::query()->max('sort_order')) + 1;
    }

    /**
     * A slug that is free, even if two categories are named alike: `desain`, then `desain-2`.
     * Called inside the transaction, with the unique index as the final arbiter.
     */
    private function uniqueSlug(string $name): string
    {
        $base = Str::limit(Str::slug($name), self::SLUG_LIMIT, '') ?: 'kategori';
        $slug = $base;

        for ($suffix = 2; RequestCategory::query()->where('slug', $slug)->exists(); $suffix++) {
            $tail = '-'.$suffix;
            $slug = Str::limit($base, self::SLUG_LIMIT - strlen($tail), '').$tail;
        }

        return $slug;
    }

    private function cleaned(?string $value): ?string
    {
        $value = trim((string) $value);

        return $value === '' ? null : $value;
    }
}
