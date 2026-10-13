<?php

namespace App\Actions\Admin;

use App\Concerns\RecordsReferenceAudit;
use App\Models\Department;
use App\Models\User;
use App\Services\AdminAudit;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;

/**
 * Renames a department or switches it off.
 *
 * Switching off only hides it from the forms that offer a department; people already on record
 * keep theirs, and requests already filed keep pointing at it.
 */
final class UpdateDepartment
{
    use RecordsReferenceAudit;

    public function __construct(private readonly AdminAudit $audit) {}

    /** @param array{name?: string, is_active?: bool} $data */
    public function handle(User $actor, Department $department, array $data): Department
    {
        Gate::forUser($actor)->authorize('update', $department);

        return DB::transaction(function () use ($actor, $department, $data) {
            $department = Department::query()->whereKey($department->id)->lockForUpdate()->firstOrFail();

            $old = $this->snapshot($department);

            if (isset($data['name'])) {
                $department->name = trim($data['name']);
            }
            $department->is_active = $data['is_active'] ?? $department->is_active;
            $department->save();

            $this->recordChange(
                $this->audit, $actor, $department, $old, $this->snapshot($department),
                'DEPARTMENT_UPDATED', 'DEPARTMENT_ACTIVATED', 'DEPARTMENT_DEACTIVATED',
            );

            return $department;
        });
    }

    /** @return array<string, mixed> */
    private function snapshot(Department $department): array
    {
        return [
            'name' => $department->name,
            'is_active' => (bool) $department->is_active,
        ];
    }
}
