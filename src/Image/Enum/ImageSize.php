<?php

declare(strict_types=1);

namespace PhpLLP\Image\Enum;

class ImageSize
{
    const SIZE_256X256 = '256x256';
    const SIZE_512X512 = '512x512';
    const SIZE_1024X1024 = '1024x1024';
    const SIZE_1792X1024 = '1792x1024';
    const SIZE_1024X1792 = '1024x1792';

    /**
     * @return string[]
     */
    public static function all(): array
    {
        return [
            self::SIZE_256X256,
            self::SIZE_512X512,
            self::SIZE_1024X1024,
            self::SIZE_1792X1024,
            self::SIZE_1024X1792,
        ];
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