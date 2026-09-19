<?php

namespace App\Models;

use App\Enums\RequestPriority;
use App\Enums\RequestStatus;
use App\Enums\RoleCode;
use App\Exceptions\ImmutableRecordException;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Carbon;

/**
 * The central work object. Stored in the `requests` table; the class is named
 * ServiceRequest so it never collides with Illuminate\Http\Request.
 *
 * @property int $id
 * @property string $request_number
 * @property int $requester_id
 * @property int $department_id
 * @property int $category_id
 * @property string $title
 * @property string $description
 * @property RequestPriority $priority
 * @property Carbon $deadline
 * @property RequestStatus $status
 * @property RequestStatus|null $blocked_from_status
 * @property int|null $assigned_team_id
 * @property int|null $assigned_user_id
 * @property Carbon|null $completed_at
 * @property Carbon|null $cancelled_at
 */
#[Fillable([
    'request_number', 'requester_id', 'department_id', 'category_id', 'title', 'description',
    'priority', 'deadline', 'status', 'blocked_from_status', 'assigned_team_id', 'assigned_user_id',
    'completed_at', 'cancelled_at',
])]
class ServiceRequest extends Model
{
    use HasFactory, SoftDeletes;

    protected $table = 'requests';

    protected function casts(): array
    {
        return [
            'priority' => RequestPriority::class,
            'status' => RequestStatus::class,
            'blocked_from_status' => RequestStatus::class,
            'deadline' => 'datetime',
            'completed_at' => 'datetime',
            'cancelled_at' => 'datetime',
        ];
    }

    protected static function booted(): void
    {
        // The request number and the original requester are part of the request's identity.
        static::updating(function (self $request) {
            foreach (['request_number', 'requester_id'] as $immutable) {
                if ($request->isDirty($immutable)) {
                    throw new ImmutableRecordException("Request {$immutable} can never change.");
                }
            }
        });
    }

    /**
     * Restrict a query to the requests this user is allowed to see.
     * This is the single definition of visibility; the policy's view() defers to it.
     *
     * - ADMIN / MANAGEMENT: everything.
     * - TEAM_LEAD: requests of their teams, plus requests with no team yet (the triage queue).
     * - STAFF: requests assigned to them or to one of their teams.
     * - Everyone: requests they created.
     *
     * @param  Builder<self>  $query
     * @return Builder<self>
     */
    public function scopeVisibleTo(Builder $query, User $user): Builder
    {
        if ($user->hasAnyRole(RoleCode::Admin, RoleCode::Management)) {
            return $query;
        }

        return $query->where(function (Builder $visible) use ($user) {
            $visible->where('requests.requester_id', $user->id);

            $teamIds = $user->teamIds();

            if ($user->hasRole(RoleCode::TeamLead)) {
                $visible->orWhereNull('requests.assigned_team_id')
                    ->orWhereIn('requests.assigned_team_id', $teamIds);
            }

            if ($user->hasRole(RoleCode::Staff)) {
                $visible->orWhere('requests.assigned_user_id', $user->id)
                    ->orWhereIn('requests.assigned_team_id', $teamIds);
            }
        });
    }

    /** Open requests whose deadline has passed. */
    public function scopeOverdue(Builder $query): Builder
    {
        return $query->whereNotIn('requests.status', [RequestStatus::Completed->value, RequestStatus::Cancelled->value])
            ->where('requests.deadline', '<', now());
    }

    public function isOverdue(): bool
    {
        return $this->status->isOpen() && $this->deadline->isPast();
    }

    /** @return BelongsTo<User, $this> */
    public function requester(): BelongsTo
    {
        return $this->belongsTo(User::class, 'requester_id');
    }

    /** @return BelongsTo<Department, $this> */
    public function department(): BelongsTo
    {
        return $this->belongsTo(Department::class);
    }

    /** @return BelongsTo<RequestCategory, $this> */
    public function category(): BelongsTo
    {
        return $this->belongsTo(RequestCategory::class, 'category_id');
    }

    /** @return BelongsTo<Team, $this> */
    public function assignedTeam(): BelongsTo
    {
        return $this->belongsTo(Team::class, 'assigned_team_id');
    }

    /** @return BelongsTo<User, $this> */
    public function assignedUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'assigned_user_id');
    }

    /** @return HasMany<Assignment, $this> */
    public function assignments(): HasMany
    {
        return $this->hasMany(Assignment::class, 'request_id');
    }

    /** @return HasMany<Comment, $this> */
    public function comments(): HasMany
    {
        return $this->hasMany(Comment::class, 'request_id');
    }

    /** @return HasMany<Attachment, $this> */
    public function attachments(): HasMany
    {
        return $this->hasMany(Attachment::class, 'request_id');
    }

    /** @return HasMany<Review, $this> */
    public function reviews(): HasMany
    {
        return $this->hasMany(Review::class, 'request_id');
    }

    /** @return HasMany<ActivityLog, $this> */
    public function activityLogs(): HasMany
    {
        return $this->hasMany(ActivityLog::class, 'request_id');
    }
}
