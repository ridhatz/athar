<?php

namespace App\Enums;

/**
 * Where one KPI sheet stands.
 *
 * The same shape as a request's review loop: the employee fills in achievements and submits, a
 * supervisor approves or sends it back. Revision is a real state, not a deletion — the sheet keeps
 * its history and goes round again.
 */
enum KpiStatus: string
{
    case Pending = 'PENDING';
    case Submitted = 'SUBMITTED';
    case Reviewed = 'REVIEWED';
    case Revision = 'REVISION';

    /** @return array<int, string> */
    public static function values(): array
    {
        return array_map(fn (self $s) => $s->value, self::cases());
    }

    /** May the employee still edit their achievements? */
    public function isEditableByOwner(): bool
    {
        return $this === self::Pending || $this === self::Revision;
    }

    /** Is it waiting on a supervisor? */
    public function awaitsReview(): bool
    {
        return $this === self::Submitted;
    }
}
