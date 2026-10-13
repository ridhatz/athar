<?php

namespace App\Actions\Admin;

use App\Models\Department;
use App\Models\User;
use App\Services\AdminAudit;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;

/** Adds a department that people can belong to and file requests on behalf of. */
final class CreateDepartment
{
    public function __construct(private readonly AdminAudit $audit) {}

    /** @param array{name: string} $data */
    public function handle(User $actor, array $data): Department
    {
        Gate::forUser($actor)->authorize('create', Department::class);

        return DB::transaction(function () use ($actor, $data) {
            $department = Department::query()->create(['name' => trim($data['name'])]);

            $this->audit->record($actor, $department, 'DEPARTMENT_CREATED', null, ['name' => $department->name]);

            return $department->fresh();
        });
    }
}
