<?php

declare(strict_types=1);

namespace PhpLLP\Support;

class Str
{
    public static function length(string $string): int
    {
        if (function_exists('mb_strlen')) {
            return mb_strlen($string, 'UTF-8');
        }
        return strlen($string);
    }

    public static function subStr(string $string, int $start, ?int $length = null): string
    {
        if (function_exists('mb_substr')) {
            return mb_substr($string, $start, $length, 'UTF-8');
        }
        return $length === null ? substr($string, $start) : substr($string, $start, $length);
    }

    public static function toLower(string $string): string
    {
        if (function_exists('mb_strtolower')) {
            return mb_strtolower($string, 'UTF-8');
        }
        return strtolower($string);
    }

    public static function toUpper(string $string): string
    {
        if (function_exists('mb_strtoupper')) {
            return mb_strtoupper($string, 'UTF-8');
        }
        return strtoupper($string);
    }

    public static function trim(string $string): string
    {
        return trim($string);
    }

    public static function lTrim(string $string): string
    {
        return ltrim($string);
    }

    public static function rTrim(string $string): string
    {
        return rtrim($string);
    }

    /**
     * @param string $haystack
     * @param string $needle
     * @return bool
     */
    public static function contains(string $haystack, string $needle): bool
    {
        return PhpVersion::strContains($haystack, $needle);
    }

    /**
     * @param string $haystack
     * @param string $needle
     * @return bool
     */
    public static function startsWith(string $haystack, string $needle): bool
    {
        return PhpVersion::strStartsWith($haystack, $needle);
    }

    /**
     * @param string $haystack
     * @param string $needle
     * @return bool
     */
    public static function endsWith(string $haystack, string $needle): bool
    {
        return PhpVersion::strEndsWith($haystack, $needle);
    }

    /**
     * @param string $string
     * @param string $search
     * @param string $replace
     * @return string
     */
    public static function replace(string $string, string $search, string $replace): string
    {
        return str_replace($search, $replace, $string);
    }

    /**
     * @param string $string
     * @param string $pattern
     * @param string $replacement
     * @return string
     */
    public static function replaceRegex(string $string, string $pattern, string $replacement): string
    {
        return (string)preg_replace($pattern, $replacement, $string);
    }

    /**
     * @param string $string
     * @param string $delimiter
     * @return string[]
     */
    public static function split(string $string, string $delimiter): array
    {
        return explode($delimiter, $string);
    }

    /**
     * @param string $string
     * @param int $limit
     * @return string[]
     */
    public static function words(string $string, int $limit = 0): array
    {
        $words = preg_split('/\s+/', trim($string));
        if ($limit > 0) {
            return array_slice($words, 0, $limit);
        }
        return $words;
    }

    /**
     * @param string $string
     * @return string
     */
    public static function snakeToCamel(string $string): string
    {
        return lcfirst(str_replace(' ', '', ucwords(str_replace(['_', '-'], ' ', $string))));
    }

    /**
     * @param string $string
     * @return string
     */
    public static function camelToSnake(string $string): string
    {
        return strtolower(preg_replace('/[A-Z]/', '_$0', lcfirst($string)));
    }

    /**
     * @param string $string
     * @return string
     */
    public static function slug(string $string): string
    {
        $string = self::toLower($string);
        $string = preg_replace('/[^a-z0-9\s-]/', '', $string);
        $string = preg_replace('/[\s-]+/', '-', $string);
        return trim($string, '-');
    }
}