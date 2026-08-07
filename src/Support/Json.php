<?php

declare(strict_types=1);

namespace PhpLLP\Support;

use PhpLLP\Exception\LLPException;

class Json
{
    /**
     * @param mixed $value
     * @return string
     * @throws LLPException
     */
    public static function encode($value): string
    {
        $options = 0;
        if (defined('JSON_UNESCAPED_UNICODE')) {
            $options |= JSON_UNESCAPED_UNICODE;
        }
        if (defined('JSON_UNESCAPED_SLASHES')) {
            $options |= JSON_UNESCAPED_SLASHES;
        }

        $result = json_encode($value, $options);

        if ($result === false) {
            throw new LLPException('JSON encode failed: ' . self::getError());
        }

        return $result;
    }

    /**
     * @param string $json
     * @param bool $assoc
     * @return mixed
     * @throws LLPException
     */
    public static function decode(string $json, bool $assoc = true)
    {
        $result = json_decode($json, $assoc);

        if ($result === null && json_last_error() !== JSON_ERROR_NONE) {
            throw new LLPException('JSON decode failed: ' . self::getError());
        }

        return $result;
    }

    /**
     * @param string $json
     * @return bool
     */
    public static function isValid(string $json): bool
    {
        json_decode($json);
        return json_last_error() === JSON_ERROR_NONE;
    }

    private static function getError(): string
    {
        if (function_exists('json_last_error_msg')) {
            return json_last_error_msg();
        }

        $error = json_last_error();
        switch ($error) {
            case JSON_ERROR_NONE:
                return 'No error';
            case JSON_ERROR_DEPTH:
                return 'Maximum stack depth exceeded';
            case JSON_ERROR_STATE_MISMATCH:
                return 'Underflow or the modes mismatch';
            case JSON_ERROR_CTRL_CHAR:
                return 'Unexpected control character found';
            case JSON_ERROR_SYNTAX:
                return 'Syntax error, malformed JSON';
            case JSON_ERROR_UTF8:
                return 'Malformed UTF-8 characters';
            default:
                return 'Unknown error';
        }
    }
}