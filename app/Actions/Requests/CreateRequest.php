<?php

namespace App\Actions\Requests;

use App\Enums\ActivityAction;
use App\Enums\RequestPriority;
use App\Enums\RequestStatus;
use App\Models\ServiceRequest;
use App\Models\User;
use App\Services\ActivityLogger;
use App\Services\RequestNumberGenerator;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;

/**
 * Turns a need into a trackable work object: numbered, stored, and logged in one transaction.
 */
final class CreateRequest
{
    public function __construct(
        private readonly RequestNumberGenerator $numbers,
        private readonly ActivityLogger $log,
    ) {}

    /**
     * @param  array{title: string, description: string, category_id: int, deadline: mixed, priority?: string|null, department_id?: int|null}  $data
     */
    public function handle(User $actor, array $data): ServiceRequest
    {
        Gate::forUser($actor)->authorize('create', ServiceRequest::class);

        return DB::transaction(function () use ($actor, $data) {
            $request = ServiceRequest::query()->create([
                'request_number' => $this->numbers->next(),
                'requester_id' => $actor->id,
                'department_id' => $this->resolveDepartment($actor, $data['department_id'] ?? null),
                'category_id' => $data['category_id'],
                'title' => trim($data['title']),
                'description' => trim($data['description']),
                'priority' => RequestPriority::tryFrom($data['priority'] ?? '') ?? RequestPriority::Normal,
                'deadline' => $data['deadline'],
                'status' => RequestStatus::New,
            ]);

            $this->log->record($request, $actor, ActivityAction::RequestCreated, new: $request->request_number);

            return $request;
        });
    }

    /**
     * A requester files on behalf of their own department. An admin may name another one,
     * and anyone without a department on record must say which one this is for.
     */
    private function resolveDepartment(User $actor, ?int $requested): int
    {
        $departmentId = $actor->isAdmin() ? ($requested ?? $actor->department_id) : ($actor->department_id ?? $requested);

        if ($departmentId === null) {
            throw ValidationException::withMessages([
                'department_id' => __('errors.department_required'),
            ]);
        }

        return $departmentId;
    }
}
