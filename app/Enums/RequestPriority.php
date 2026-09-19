<?php

namespace App\Enums;

/**
 * Chosen by the requester or an administrator. Never inferred by software in V1.
 */
enum RequestPriority: string
{
    case Low = 'LOW';
    case Normal = 'NORMAL';
    case High = 'HIGH';
    case Critical = 'CRITICAL';

    /** Higher number = more urgent. Used for sorting. */
    public function weight(): int
    {
        return match ($this) {
            self::Low => 1,
            self::Normal => 2,
            self::High => 3,
            self::Critical => 4,
        };
    }

    /** @return array<int, string> */
    public static function values(): array
    {
        return array_map(fn (self $p) => $p->value, self::cases());
    }
}
