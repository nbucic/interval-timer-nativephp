<?php

namespace App\Enum;

use Override;

enum BeepLeadIn: int
{
    case Five = 5;
    case Three = 3;

    public static function fromNumberToEnum(int $value): BeepLeadIn
    {
        return match ($value) {
            3 => self::Three,
            default => self::Five,
        };
    }
}
