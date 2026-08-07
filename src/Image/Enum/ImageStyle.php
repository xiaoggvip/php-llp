<?php

declare(strict_types=1);

namespace PhpLLP\Image\Enum;

class ImageStyle
{
    const VIVID = 'vivid';
    const NATURAL = 'natural';

    /**
     * @return string[]
     */
    public static function all(): array
    {
        return [self::VIVID, self::NATURAL];
    }

    /**
     * @param string $style
     * @return bool
     */
    public static function isValid(string $style): bool
    {
        return in_array($style, self::all(), true);
    }
}