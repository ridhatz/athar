<?php

namespace App\Console\Commands;

use App\Enums\NotificationType;
use App\Enums\RequestStatus;
use App\Enums\RoleCode;
use App\Models\ServiceRequest;
use App\Models\User;
use App\Notifications\RequestNotification;
use Illuminate\Console\Command;
use Illuminate\Notifications\DatabaseNotification;
use Illuminate\Support\Collection;

/**
 * Reminds the people doing the work when a deadline is close, and again when it has passed.
 * Runs hourly (routes/console.php). Each reminder is sent once: "approaching" once per deadline,
 * "overdue" once per day, so re-running the command never nags.
 */
class NotifyDeadlinesCommand extends Command
{
    protected $signature = 'athar:notify-deadlines';

    protected $description = 'Send deadline-approaching and overdue reminders';

    public function handle(): int
    {
        $timezone = config('athar.timezone');
        $horizon = now()->addHours((int) config('athar.notifications.deadline_warning_hours'));
        $sent = 0;

        ServiceRequest::query()
            ->whereNotIn('status', [RequestStatus::Completed->value, RequestStatus::Cancelled->value])
            ->where('deadline', '<=', $horizon)
            ->with(['assignedUser'])
            ->orderBy('id')
            ->chunkById(100, function ($requests) use ($timezone, &$sent) {
                foreach ($requests as $request) {
                    $overdue = $request->deadline->isPast();
                    $type = $overdue ? NotificationType::Overdue : NotificationType::DeadlineApproaching;
                    $key = $overdue
                        ? "overdue:{$request->id}:".now($timezone)->format('Y-m-d')
                        : "approaching:{$request->id}:{$request->deadline->timestamp}";
                    $due = $request->deadline->setTimezone($timezone)->format('d M Y, H:i');

                    foreach ($this->recipients($request) as $user) {
                        if ($this->alreadySent($user, $key)) {
                            continue;
                        }

                        $user->notify(new RequestNotification($type, $request, ['due' => $due], $key));
                        $sent++;
                    }
                }
            });

        $this->components->info("Sent {$sent} deadline reminder(s).");

        return self::SUCCESS;
    }

    /**
     * The person responsible; failing that the team's leads; failing that the admins.
     *
     * @return Collection<int, User>
     */
    private function recipients(ServiceRequest $request): Collection
    {
        if ($request->assignedUser?->is_active) {
            return collect([$request->assignedUser]);
        }

        $query = User::query()->where('is_active', true);

        if ($request->assigned_team_id !== null) {
            $leads = (clone $query)
                ->whereHas('roles', fn ($q) => $q->where('code', RoleCode::TeamLead->value))
                ->whereHas('teams', fn ($q) => $q->whereKey($request->assigned_team_id))
                ->get();

            if ($leads->isNotEmpty()) {
                return $leads;
            }
        }

        return $query->whereHas('roles', fn ($q) => $q->where('code', RoleCode::Admin->value))->get();
    }

    private function alreadySent(User $user, string $key): bool
    {
        return DatabaseNotification::query()
            ->where('notifiable_type', $user->getMorphClass())
            ->where('notifiable_id', $user->id)
            ->where('data->dedupe_key', $key)
            ->exists();
    }
}
