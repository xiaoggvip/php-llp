<?php

declare(strict_types=1);

namespace PhpLLP\Support;

class Arr
{
    /**
     * @param array $array
     * @param string $key
     * @param mixed $default
     * @return mixed
     */
    public static function get(array $array, string $key, $default = null)
    {
        if (array_key_exists($key, $array)) {
            return $array[$key];
        }

        foreach (explode('.', $key) as $segment) {
            if (!is_array($array) || !array_key_exists($segment, $array)) {
                return $default;
            }
            $array = $array[$segment];
        }

        return $array;
    }

    /**
     * @param array $array
     * @param string $key
     * @param mixed $value
     * @return array
     */
    public static function set(array $array, string $key, $value): array
    {
        $keys = explode('.', $key);
        $current = &$array;

        foreach ($keys as $segment) {
            if (!is_array($current)) {
                $current = [];
            }
            $current = &$current[$segment];
        }

        $current = $value;
        return $array;
    }

    /**
     * @param array $array
     * @param string $key
     * @return bool
     */
    public static function has(array $array, string $key): bool
    {
        if (array_key_exists($key, $array)) {
            return true;
        }

        foreach (explode('.', $key) as $segment) {
            if (!is_array($array) || !array_key_exists($segment, $array)) {
                return false;
            }
            $array = $array[$segment];
        }

        return true;
    }

    /**
     * @param array $array
     * @param string $key
     * @return array
     */
    public static function forget(array $array, string $key): array
    {
        $keys = explode('.', $key);
        $current = &$array;

        while (count($keys) > 1) {
            $segment = array_shift($keys);
            if (!is_array($current) || !array_key_exists($segment, $current)) {
                return $array;
            }
            $current = &$current[$segment];
        }

        unset($current[array_shift($keys)]);
        return $array;
    }

    /**
     * @param array $array
     * @param string|null $key
     * @return array
     */
    public static function flatten(array $array, ?string $key = null): array
    {
        $result = [];

        foreach ($array as $item) {
            if (is_array($item)) {
                $result = array_merge($result, self::flatten($item));
            } else {
                $result[] = $item;
            }
        }

        return $result;
    }

    /**
     * @param array $array
     * @param string $value
     * @param bool $strict
     * @return bool
     */
    public static function contains(array $array, $value, bool $strict = true): bool
    {
        return in_array($value, $array, $strict);
    }

    /**
     * @param array $array
     * @param string|null $column
     * @return array
     */
    public static function pluck(array $array, string $column): array
    {
        return array_map(function ($item) use ($column) {
            if (is_array($item)) {
                return self::get($item, $column);
            }
            if (is_object($item)) {
                $getter = 'get' . str_replace(' ', '', ucwords(str_replace(['_', '-'], ' ', $column)));
                if (method_exists($item, $getter)) {
                    return $item->$getter();
                }
                if (isset($item->$column)) {
                    return $item->$column;
                }
            }
            return null;
        }, $array);
    }

    /**
     * @param array $array
     * @return bool
     */
    public static function isEmpty(array $array): bool
    {
        return count($array) === 0;
    }

    /**
     * @param array $array
     * @return int
     */
    public static function count(array $array): int
    {
        return count($array);
    }

    /**
     * @param array $array
     * @param string $key
     * @param int $direction SORT_ASC or SORT_DESC
     * @return array
     */
    public static function sortBy(array $array, string $key, int $direction = SORT_ASC): array
    {
        usort($array, function ($a, $b) use ($key, $direction) {
            $va = is_array($a) ? self::get($a, $key) : ($a->$key ?? null);
            $vb = is_array($b) ? self::get($b, $key) : ($b->$key ?? null);

            if ($va == $vb) {
                return 0;
            }

            $cmp = $va < $vb ? -1 : 1;
            return $direction === SORT_DESC ? -$cmp : $cmp;
        });

        return $array;
    }

    /**
     * @param array $array
     * @param callable $callback
     * @return array
     */
    public static function map(array $array, callable $callback): array
    {
        return array_map($callback, $array);
    }

    /**
     * @param array $array
     * @param callable $callback
     * @return array
     */
    public static function filter(array $array, callable $callback): array
    {
        return array_filter($array, $callback);
    }

    /**
     * @param array $array
     * @param mixed $initial
     * @param callable $callback
     * @return mixed
     */
    public static function reduce(array $array, callable $callback, $initial = null)
    {
        return array_reduce($array, $callback, $initial);
    }

    /**
     * @param array ...$arrays
     * @return array
     */
    public static function merge(array ...$arrays): array
    {
        return array_merge(...$arrays);
    }
}