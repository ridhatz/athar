<?php

namespace App\Models;

use App\Enums\ReviewDecision;
use App\Exceptions\ImmutableRecordException;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * One round of review. Created PENDING when the PIC sends work to review; decided exactly once.
 * A revision produces a new round when the work is submitted again, so nothing is overwritten.
 *
 * @property int $id
 * @property int $request_id
 * @property int $round
 * @property int $requested_by
 * @property Carbon $requested_at
 * @property int|null $reviewer_id
 * @property ReviewDecision $decision
 * @property string|null $decision_note
 * @property Carbon|null $decided_at
 */
#[Fillable(['request_id', 'round', 'requested_by', 'requested_at', 'reviewer_id', 'decision', 'decision_note', 'decided_at'])]
class Review extends Model
{
    public $timestamps = false;

    protected function casts(): array
    {
        return [
            'decision' => ReviewDecision::class,
            'requested_at' => 'datetime',
            'decided_at' => 'datetime',
        ];
    }

    protected static function booted(): void
    {
        // A decision is final.
        static::updating(function (self $review) {
            if ($review->getOriginal('decision') !== ReviewDecision::Pending) {
                throw new ImmutableRecordException('A review that has been decided cannot be changed.');
            }
        });
        static::deleting(fn () => throw new ImmutableRecordException('Reviews cannot be deleted.'));
    }

    /** @return BelongsTo<ServiceRequest, $this> */
    public function request(): BelongsTo
    {
        return $this->belongsTo(ServiceRequest::class, 'request_id');
    }

    /** @return BelongsTo<User, $this> */
    public function requester(): BelongsTo
    {
        return $this->belongsTo(User::class, 'requested_by');
    }

    /** @return BelongsTo<User, $this> */
    public function reviewer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reviewer_id');
    }
}
