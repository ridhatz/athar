<?php

namespace App\Actions\Requests;

use App\Enums\ActivityAction;
use App\Enums\RequestPriority;
use App\Models\RequestCategory;
use App\Models\ServiceRequest;
use App\Models\User;
use App\Services\ActivityLogger;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;

/**
 * Edits request details. Every field that really changes gets its own audit row with
 * the previous and the new value. Sending a value equal to the current one is a no-op.
 */
final class UpdateRequest
{
    private const DETAIL_FIELDS = ['title', 'description', 'category_id', 'deadline'];

    public function __construct(private readonly ActivityLogger $log) {}

    /**
     * @param  array{title?: string, description?: string, category_id?: int, deadline?: mixed, priority?: string}  $input
     */
    public function handle(User $actor, ServiceRequest $request, array $input): ServiceRequest
    {
        return DB::transaction(function () use ($actor, $request, $input) {
            $current = ServiceRequest::query()->whereKey($request->id)->lockForUpdate()->firstOrFail();

            if ($current->status->isTerminal()) {
                throw ValidationException::withMessages(['status' => __('errors.request_closed')]);
            }

            $changes = $this->diff($current, $input);

            if ($changes === []) {
                return $current;
            }

            $this->authorizeChanges($actor, $current, array_keys($changes));

            $old = [];
            foreach ($changes as $field => $new) {
                $old[$field] = $current->{$field};
                $current->{$field} = $new;
            }

            $categoryNames = $this->categoryNames($old, $changes);
            $current->save();

            foreach ($changes as $field => $new) {
                $this->record($actor, $current, $field, $old[$field], $new, $categoryNames);
            }

            return $current;
        });
    }

    /**
     * Only the fields that were sent AND differ from what is stored.
     *
     * @param  array<string, mixed>  $input
     * @return array<string, mixed>
     */
    private function diff(ServiceRequest $current, array $input): array
    {
        $changes = [];

        foreach (['title', 'description'] as $text) {
            if (array_key_exists($text, $input) && trim((string) $input[$text]) !== $current->{$text}) {
                $changes[$text] = trim((string) $input[$text]);
            }
        }

        if (array_key_exists('category_id', $input) && (int) $input['category_id'] !== $current->category_id) {
            $changes['category_id'] = (int) $input['category_id'];
        }

        if (array_key_exists('deadline', $input)) {
            $deadline = Carbon::parse($input['deadline']);
            if (! $deadline->equalTo($current->deadline)) {
                if ($deadline->isPast()) {
                    throw ValidationException::withMessages(['deadline' => __('errors.deadline_past')]);
                }
                $changes['deadline'] = $deadline;
            }
        }

        if (array_key_exists('priority', $input)) {
            $priority = RequestPriority::from($input['priority']);
            if ($priority !== $current->priority) {
                $changes['priority'] = $priority;
            }
        }

        return $changes;
    }

    /** @param array<int, string> $fields */
    private function authorizeChanges(User $actor, ServiceRequest $request, array $fields): void
    {
        $gate = Gate::forUser($actor);

        if (array_intersect($fields, self::DETAIL_FIELDS) !== [] && ! $gate->allows('editDetails', $request)) {
            throw new AuthorizationException;
        }

        if (in_array('priority', $fields, true) && ! $gate->allows('editPriority', $request)) {
            throw new AuthorizationException;
        }
    }

    /**
     * Resolve category ids to readable names once, before saving, so the audit row
     * shows "Design → Documentation" rather than "3 → 5".
     *
     * @param  array<string, mixed>  $old
     * @param  array<string, mixed>  $changes
     * @return array<int, string>
     */
    private function categoryNames(array $old, array $changes): array
    {
        if (! isset($changes['category_id'])) {
            return [];
        }

        return RequestCategory::query()
            ->whereIn('id', [$old['category_id'], $changes['category_id']])
            ->pluck('name_en', 'id')
            ->all();
    }

    /** @param array<int, string> $categoryNames */
    private function record(User $actor, ServiceRequest $request, string $field, mixed $old, mixed $new, array $categoryNames): void
    {
        [$action, $label, $oldText, $newText] = match ($field) {
            'priority' => [ActivityAction::PriorityChanged, 'priority', $old->value, $new->value],
            'deadline' => [ActivityAction::DeadlineChanged, 'deadline', $old->toIso8601String(), $new->toIso8601String()],
            'category_id' => [ActivityAction::RequestUpdated, 'category', $categoryNames[$old] ?? (string) $old, $categoryNames[$new] ?? (string) $new],
            default => [ActivityAction::RequestUpdated, $field, (string) $old, (string) $new],
        };

        $this->log->record($request, $actor, $action, $label, $oldText, $newText);
    }
}
