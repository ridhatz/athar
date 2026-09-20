<?php

namespace App\Actions\Requests;

use App\Enums\ActivityAction;
use App\Enums\AttachmentKind;
use App\Enums\RequestPriority;
use App\Enums\RequestStatus;
use App\Models\Attachment;
use App\Models\ServiceRequest;
use App\Models\User;
use App\Services\ActivityLogger;
use App\Services\FileGuard;
use App\Services\RequestNumberGenerator;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;
use Throwable;

/**
 * Turns a need into a trackable work object: numbered, stored, and logged in one transaction.
 */
final class CreateRequest
{
    public function __construct(
        private readonly RequestNumberGenerator $numbers,
        private readonly ActivityLogger $log,
        private readonly AddAttachment $attach,
        private readonly FileGuard $guard,
    ) {}

    /**
     * Files may be attached at the moment of opening (a PDF brief, a logo, a reference). They are all
     * checked BEFORE anything is saved, and if any one is refused the request is not created at all,
     * so nobody ends up with a half-filed ticket.
     *
     * @param  array{title: string, description: string, category_id: int, deadline: mixed, priority?: string|null, department_id?: int|null}  $data
     * @param  array<int, UploadedFile>  $files
     */
    public function handle(User $actor, array $data, array $files = []): ServiceRequest
    {
        Gate::forUser($actor)->authorize('create', ServiceRequest::class);

        foreach ($files as $file) {
            try {
                $this->guard->inspect($file);
            } catch (ValidationException $e) {
                // Report it on the field the form actually has, and name the offending file.
                throw ValidationException::withMessages([
                    'files' => $file->getClientOriginalName().': '.implode(' ', $e->errors()['file'] ?? []),
                ]);
            }
        }

        $stored = [];

        try {
            return $this->create($actor, $data, $files, $stored);
        } catch (Throwable $e) {
            // The transaction rolled the rows back; remove the bytes too.
            $disk = Storage::disk((string) config('athar.attachments.disk'));
            foreach ($stored as $attachment) {
                $disk->delete($attachment->stored_path);
            }

            throw $e;
        }
    }

    /**
     * @param  array<int, UploadedFile>  $files
     * @param  array<int, Attachment>  $stored
     */
    private function create(User $actor, array $data, array $files, array &$stored): ServiceRequest
    {
        return DB::transaction(function () use ($actor, $data, $files, &$stored) {
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

            foreach ($files as $file) {
                $stored[] = $this->attach->handle($actor, $request, $file, AttachmentKind::Brief);
            }

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
