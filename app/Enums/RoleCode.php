<?php

namespace App\Enums;

enum RoleCode: string
{
    case Requester = 'REQUESTER';
    case Staff = 'STAFF';
    case TeamLead = 'TEAM_LEAD';
    case Admin = 'ADMIN';
    case Management = 'MANAGEMENT';

    /** @return array<int, string> */
    public static function values(): array
    {
        return array_map(fn (self $r) => $r->value, self::cases());
    }
}
