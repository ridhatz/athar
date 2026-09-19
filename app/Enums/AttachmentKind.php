<?php

namespace App\Enums;

/** What a file is for. Deliverable-type files can only come from the working side. */
enum AttachmentKind: string
{
    case Brief = 'BRIEF';
    case Reference = 'REFERENCE';
    case Logo = 'LOGO';
    case Document = 'DOCUMENT';
    case Draft = 'DRAFT';
    case Revision = 'REVISION';
    case Deliverable = 'DELIVERABLE';
    case Other = 'OTHER';

    /** Output the team produces, as opposed to material the requester supplies. */
    public function isWorkProduct(): bool
    {
        return in_array($this, [self::Draft, self::Revision, self::Deliverable], true);
    }

    /** @return array<int, string> */
    public static function values(): array
    {
        return array_map(fn (self $k) => $k->value, self::cases());
    }
}
