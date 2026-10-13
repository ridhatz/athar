<?php

namespace App\Enums;

/**
 * Which way a KPI is supposed to move, which is what decides how it scores.
 *
 * "Raise donations to 55.000" rewards a bigger number; "keep operating cost under 90%" rewards a
 * smaller one. Same weight, opposite arithmetic — so this is stored, never inferred from the text
 * of the target.
 */
enum KpiDirection: string
{
    case HigherBetter = 'HIGHER_BETTER';
    case LowerBetter = 'LOWER_BETTER';

    /** @return array<int, string> */
    public static function values(): array
    {
        return array_map(fn (self $d) => $d->value, self::cases());
    }

    /** How the target reads on screen: 55.000 versus <90%. */
    public function prefix(): string
    {
        return $this === self::LowerBetter ? '<' : '';
    }
}
