<?php

declare(strict_types=1);

namespace PhpLLP\Support;

class PhpVersion
{
    /** @var int */
    private static $major = 0;

    /** @var int */
    private static $minor = 0;

    /** @var bool */
    private static $initialized = false;

    private static function init(): void
    {
        if (self::$initialized) {
            return;
        }

        $version = PHP_VERSION;
        $parts = explode('.', $version);
        self::$major = (int)($parts[0] ?? 0);
        self::$minor = (int)($parts[1] ?? 0);
        self::$initialized = true;
    }

    public static function getMajor(): int
    {
        self::init();
        return self::$major;
    }

    public static function getMinor(): int
    {
        self::init();
        return self::$minor;
    }

    public static function is74(): bool
    {
        self::init();
        return self::$major === 7 && self::$minor === 4;
    }

    public static function is80Plus(): bool
    {
        self::init();
        return self::$major >= 8;
    }

    public static function is81Plus(): bool
    {
        self::init();
        return self::$major > 8 || (self::$major === 8 && self::$minor >= 1);
    }

    public static function is82Plus(): bool
    {
        self::init();
        return self::$major > 8 || (self::$major === 8 && self::$minor >= 2);
    }

    public static function supports(string $feature): bool
    {
        switch ($feature) {
            case 'str_contains':
                return function_exists('str_contains');
            case 'str_starts_with':
                return function_exists('str_starts_with');
            case 'str_ends_with':
                return function_exists('str_ends_with');
            case 'get_debug_type':
                return function_exists('get_debug_type');
            case 'enum':
                return self::is81Plus();
            case 'readonly':
                return self::is81Plus();
            case 'fiber':
                return class_exists('Fiber');
            case 'readonly_class':
                return self::is82Plus();
            default:
                return false;
        }
    }

    public static function strContains(string $haystack, string $needle): bool
    {
        if ($needle === '') {
            return true;
        }

        if (function_exists('str_contains')) {
            return str_contains($haystack, $needle);
        }

        return strpos($haystack, $needle) !== false;
    }

    public static function strStartsWith(string $haystack, string $needle): bool
    {
        if ($needle === '') {
            return true;
        }

        if (function_exists('str_starts_with')) {
            return str_starts_with($haystack, $needle);
        }

        return strpos($haystack, $needle) === 0;
    }

    public static function strEndsWith(string $haystack, string $needle): bool
    {
        if ($needle === '') {
            return true;
        }

        if (function_exists('str_ends_with')) {
            return str_ends_with($haystack, $needle);
        }

        return substr($haystack, -strlen($needle)) === $needle;
    }

    public static function getDebugType($value): string
    {
        if (function_exists('get_debug_type')) {
            return get_debug_type($value);
        }

        if (is_object($value)) {
            return get_class($value);
        }
        if (is_array($value)) {
            return 'array';
        }
        if (is_string($value)) {
            return 'string';
        }
        if (is_int($value)) {
            return 'int';
        }
        if (is_float($value)) {
            return 'float';
        }
        if (is_bool($value)) {
            return $value ? 'true' : 'false';
        }
        if ($value === null) {
            return 'null';
        }

        return gettype($value);
    }

    public static function compare(string $version, string $operator = '>='): bool
    {
        return version_compare(PHP_VERSION, $version, $operator);
    }
}