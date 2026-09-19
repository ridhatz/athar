<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * @property int $id
 * @property string $slug
 * @property string $name_id
 * @property string $name_en
 * @property string|null $guidance_id
 * @property string|null $guidance_en
 * @property int $sort_order
 * @property bool $is_active
 */
#[Fillable(['slug', 'name_id', 'name_en', 'guidance_id', 'guidance_en', 'sort_order', 'is_active'])]
class RequestCategory extends Model
{
    use HasFactory;

    protected function casts(): array
    {
        return ['is_active' => 'boolean', 'sort_order' => 'integer'];
    }

    /** Name in the current UI language. */
    public function label(): string
    {
        return app()->getLocale() === 'en' ? $this->name_en : $this->name_id;
    }

    /** Form hint in the current UI language, falling back to the other language. */
    public function guidance(): ?string
    {
        return app()->getLocale() === 'en'
            ? ($this->guidance_en ?? $this->guidance_id)
            : ($this->guidance_id ?? $this->guidance_en);
    }

    /** @param Builder<self> $query */
    public function scopeActive(Builder $query): Builder
    {
        return $query->where('is_active', true);
    }

    /** @return HasMany<ServiceRequest, $this> */
    public function requests(): HasMany
    {
        return $this->hasMany(ServiceRequest::class, 'category_id');
    }
}
