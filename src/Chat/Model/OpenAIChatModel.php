<?php

declare(strict_types=1);

namespace PhpLLP\Chat\Model;

class OpenAIChatModel
{
    const GPT_4O = 'gpt-4o';
    const GPT_4O_MINI = 'gpt-4o-mini';
    const GPT_4_TURBO = 'gpt-4-turbo';
    const GPT_4 = 'gpt-4';
    const GPT_35_TURBO = 'gpt-3.5-turbo';

    /**
     * @return string[]
     */
    public static function all(): array
    {
        return [
            self::GPT_4O,
            self::GPT_4O_MINI,
            self::GPT_4_TURBO,
            self::GPT_4,
            self::GPT_35_TURBO,
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