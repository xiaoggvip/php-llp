<?php

declare(strict_types=1);

namespace PhpLLP\Chat\Model;

class OllamaChatModel
{
    const LLAMA3_1 = 'llama3.1';
    const LLAMA3 = 'llama3';
    const MISTRAL = 'mistral';
    const MIXTRAL = 'mixtral';
    const CODE_LLAMA = 'codellama';
    const PHI3 = 'phi3';
    const GEMMA2 = 'gemma2';

    /**
     * @return string[]
     */
    public static function all(): array
    {
        return [
            self::LLAMA3_1,
            self::LLAMA3,
            self::MISTRAL,
            self::MIXTRAL,
            self::CODE_LLAMA,
            self::PHI3,
            self::GEMMA2,
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