<?php

namespace App\Enums;

enum ReviewDecision: string
{
    case Pending = 'PENDING';
    case Approved = 'APPROVED';
    case RevisionRequested = 'REVISION_REQUESTED';

    /** @return array<int, string> */
    public static function values(): array
    {
        return array_map(fn (self $d) => $d->value, self::cases());
    }
}
