<?php

declare(strict_types=1);

namespace PhpLLP\Chat;

class ChatRole
{
    const SYSTEM = 'system';
    const USER = 'user';
    const ASSISTANT = 'assistant';
    const TOOL = 'tool';

    /**
     * @return string[]
     */
    public static function all(): array
    {
        return [self::SYSTEM, self::USER, self::ASSISTANT, self::TOOL];
    }

    /**
     * @param string $role
     * @return bool
     */
    public static function isValid(string $role): bool
    {
        return in_array($role, self::all(), true);
    }

    /**
     * @param string $role
     * @return string
     */
    public static function normalize(string $role): string
    {
        return self::isValid($role) ? $role : self::USER;
    }
}