<?php

namespace App\Enums;

/**
 * How often a KPI is measured. Display only for now: every sheet is a month, and this says what
 * the number on it represents.
 */
enum KpiFrequency: string
{
    case Monthly = 'MONTHLY';
    case Quarterly = 'QUARTERLY';
    case Yearly = 'YEARLY';

    /** @return array<int, string> */
    public static function values(): array
    {
        return array_map(fn (self $f) => $f->value, self::cases());
    }
}
