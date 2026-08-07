<?php

declare(strict_types=1);

namespace PhpLLP\Image\Enum;

class ImageModel
{
    const DALL_E_3 = 'dall-e-3';
    const DALL_E_2 = 'dall-e-2';

    /**
     * @return string[]
     */
    public static function all(): array
    {
        return [self::DALL_E_3, self::DALL_E_2];
    }

    /**
     * @param string $model
     * @return bool
     */
    public static function isValid(string $model): bool
    {
        return in_array($model, self::all(), true);
    }
}