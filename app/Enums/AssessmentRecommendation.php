<?php

namespace App\Enums;

/**
 * The role-fit call AssessmentScoring makes from someone's D/I/S/C result.
 *
 * Not a judgement that someone "can't" do the other role — it names which comes more naturally
 * from how they say they communicate, so a lead can staff and coach with that in mind.
 */
enum AssessmentRecommendation: string
{
    case Sales = 'SALES';
    case Marketing = 'MARKETING';
    case Hybrid = 'HYBRID';

    /** @return array<int, string> */
    public static function values(): array
    {
        return array_map(fn (self $r) => $r->value, self::cases());
    }
}
