<?php

namespace App\Mcp\Tool\Data;

use App\Mcp\Tool\ToolException;

/**
 * Helpers to navigate into nested arrays and to describe their structure without values.
 */
class ArrayInspector
{
    private const MAX_LISTED_KEYS = 50;

    /**
     * Returns the value at a dot-separated path. An empty path returns $data.
     *
     * @throws ToolException if a segment does not exist
     */
    public static function getByPath(array $data, string $path): mixed
    {
        if ($path === '') {
            return $data;
        }

        $current = $data;
        foreach (explode('.', $path) as $segment) {
            // numeric string keys are stored as int keys by PHP, so array_key_exists covers both
            if (!is_array($current) || !array_key_exists($segment, $current)) {
                $keys = is_array($current)
                    ? array_slice(array_map('strval', array_keys($current)), 0, self::MAX_LISTED_KEYS)
                    : [];

                throw new ToolException(sprintf(
                    'path "%s" not found at segment "%s"; available keys: %s',
                    $path,
                    $segment,
                    $keys === [] ? '(none)' : implode(', ', $keys)
                ));
            }

            $current = $current[$segment];
        }

        return $current;
    }

    /**
     * Describes the structure of a value: types instead of values, lists by count and first item.
     * Arrays at depth $maxDepth are summarized as "object(<n> keys)" or "list(<n>)".
     */
    public static function describe(mixed $value, int $maxDepth): mixed
    {
        if (is_array($value)) {
            $isList = array_is_list($value);
            if ($maxDepth <= 0) {
                return $isList
                    ? sprintf('list(%d)', count($value))
                    : sprintf('object(%d keys)', count($value));
            }

            if ($isList) {
                return [
                    '_list' => count($value),
                    '_item' => $value === [] ? null : self::describe($value[0], $maxDepth - 1),
                ];
            }

            $result = [];
            foreach ($value as $key => $item) {
                $result[$key] = self::describe($item, $maxDepth - 1);
            }

            return $result;
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
            return 'bool';
        }
        if ($value === null) {
            return 'null';
        }

        return get_debug_type($value);
    }
}
