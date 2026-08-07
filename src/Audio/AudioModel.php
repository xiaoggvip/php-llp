<?php

declare(strict_types=1);

namespace PhpLLP\Audio;

class AudioModel
{
    const WHISPER_1 = 'whisper-1';
    const WHISPER_2 = 'whisper-2';

    /**
     * @return string[]
     */
    public static function all(): array
    {
        return [self::WHISPER_1, self::WHISPER_2];
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