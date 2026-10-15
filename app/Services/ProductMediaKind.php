<?php

namespace App\Services;

use InvalidArgumentException;

final class ProductMediaKind
{
    public const IMAGE = 'image';
    public const SPIN = 'spin';

    /** @return list<string> */
    public static function all(): array
    {
        return [self::IMAGE, self::SPIN];
    }

    public static function assertSupported(string $kind): void
    {
        if (! in_array($kind, self::all(), true)) {
            throw new InvalidArgumentException("Unsupported product media kind [{$kind}].");
        }
    }
}
