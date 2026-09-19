<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * One entry in a request's assignment history: which team and/or person held the work,
 * from when to when. A request has at most one current row (is_current = true); older
 * rows are closed with ended_at and kept forever.
 *
 * @property int $id
 * @property int $request_id
 * @property int|null $team_id
 * @property int|null $user_id
 * @property int $assigned_by
 * @property string|null $note
 * @property Carbon $assigned_at
 * @property Carbon|null $ended_at
 * @property bool|null $is_current
 */
#[Fillable(['request_id', 'team_id', 'user_id', 'assigned_by', 'note', 'assigned_at', 'ended_at', 'is_current'])]
class Assignment extends Model
{
    public $timestamps = false;

    protected function casts(): array
    {
        return [
            'assigned_at' => 'datetime',
            'ended_at' => 'datetime',
            'is_current' => 'boolean',
        ];
    }

    /** @return BelongsTo<ServiceRequest, $this> */
    public function request(): BelongsTo
    {
        return $this->belongsTo(ServiceRequest::class, 'request_id');
    }

    /** @return BelongsTo<Team, $this> */
    public function team(): BelongsTo
    {
        return $this->belongsTo(Team::class);
    }

    /** @return BelongsTo<User, $this> */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /** @return BelongsTo<User, $this> */
    public function assigner(): BelongsTo
    {
        return $this->belongsTo(User::class, 'assigned_by');
    }
}
