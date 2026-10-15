<?php

namespace App\Services;

use InvalidArgumentException;

final class ProductMediaRole
{
    public const FRONT = 'front';
    public const ANGLE = 'angle';
    public const SIDE = 'side';
    public const DETAIL = 'detail';
    public const LIFESTYLE = 'lifestyle';
    public const GALLERY = 'gallery';
    public const SPIN = 'spin';

    /** @return list<string> */
    public static function all(): array
    {
        return [self::FRONT, self::ANGLE, self::SIDE, self::DETAIL, self::LIFESTYLE, self::GALLERY, self::SPIN];
    }

    public static function assertSupported(string $role): void
    {
        if (! in_array($role, self::all(), true)) {
            throw new InvalidArgumentException("Unsupported product media role [{$role}].");
        }
    }
}
