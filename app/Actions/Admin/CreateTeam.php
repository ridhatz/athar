<?php

namespace App\Actions\Admin;

use App\Models\Team;
use App\Models\User;
use App\Services\AdminAudit;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;

/** Opens a new team so work can be assigned to it. New teams start active. */
final class CreateTeam
{
    public function __construct(private readonly AdminAudit $audit) {}

    /** @param array{name: string, description?: string|null} $data */
    public function handle(User $actor, array $data): Team
    {
        Gate::forUser($actor)->authorize('create', Team::class);

        return DB::transaction(function () use ($actor, $data) {
            $team = Team::query()->create([
                'name' => trim($data['name']),
                'description' => $this->cleaned($data['description'] ?? null),
            ]);

            $this->audit->record($actor, $team, 'TEAM_CREATED', null, [
                'name' => $team->name,
                'description' => $team->description,
            ]);

            // Reload so the database default for is_active is in what we hand back.
            return $team->fresh();
        });
    }

    private function cleaned(?string $value): ?string
    {
        $value = trim((string) $value);

        return $value === '' ? null : $value;
    }
}
