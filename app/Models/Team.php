<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

/**
 * @property int $id
 * @property string $name
 * @property string|null $description
 * @property bool $is_active
 */
#[Fillable(['name', 'description', 'is_active'])]
class Team extends Model
{
    use HasFactory;

    /**
     * Workers who would be left without any active team if this one were switched off.
     *
     * Filled in by the admin screen (see ReferenceRules); a declared property rather than a
     * dynamic one so it is typed and Eloquent never mistakes it for a column.
     *
     * @var array<int, string>|null
     */
    public ?array $strandedMembers = null;

    protected function casts(): array
    {
        return ['is_active' => 'boolean'];
    }

    /** @return BelongsToMany<User, $this> */
    public function members(): BelongsToMany
    {
        return $this->belongsToMany(User::class);
    }
}
