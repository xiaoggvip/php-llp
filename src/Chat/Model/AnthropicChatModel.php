<?php

declare(strict_types=1);

namespace PhpLLP\Chat\Model;

class AnthropicChatModel
{
    const CLAUDE_3_5_SONNET = 'claude-3-5-sonnet-20241022';
    const CLAUDE_3_5_HAIKU = 'claude-3-5-haiku-20241022';
    const CLAUDE_3_OPUS = 'claude-3-opus-20240229';
    const CLAUDE_3_SONNET = 'claude-3-sonnet-20240229';
    const CLAUDE_3_HAIKU = 'claude-3-haiku-20240307';

    /**
     * @return string[]
     */
    public static function all(): array
    {
        return [
            self::CLAUDE_3_5_SONNET,
            self::CLAUDE_3_5_HAIKU,
            self::CLAUDE_3_OPUS,
            self::CLAUDE_3_SONNET,
            self::CLAUDE_3_HAIKU,
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