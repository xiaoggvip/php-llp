<?php

declare(strict_types=1);

namespace PhpLLP\Chat\Model;

class MistralChatModel
{
    const MISTRAL_LARGE = 'mistral-large-latest';
    const MISTRAL_MEDIUM = 'mistral-medium-latest';
    const MISTRAL_SMALL = 'mistral-small-latest';
    const MISTRAL_NEMO = 'mistral-nemo';

    /**
     * @return string[]
     */
    public static function all(): array
    {
        return [
            self::MISTRAL_LARGE,
            self::MISTRAL_MEDIUM,
            self::MISTRAL_SMALL,
            self::MISTRAL_NEMO,
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