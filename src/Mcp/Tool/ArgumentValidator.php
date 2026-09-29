<?php

namespace App\Mcp\Tool;

/**
 * Minimal JSON-schema check for tool arguments (flat objects only).
 */
class ArgumentValidator
{
    /**
     * @return string|null first error message or null if the arguments are valid
     */
    public static function validate(array $schema, array $arguments): ?string
    {
        $properties = $schema['properties'] ?? [];
        if (!is_array($properties)) {
            $properties = [];
        }

        foreach ($schema['required'] ?? [] as $name) {
            if (!array_key_exists($name, $arguments)) {
                return sprintf('missing required argument "%s"', $name);
            }
        }

        if (($schema['additionalProperties'] ?? true) === false) {
            foreach (array_keys($arguments) as $name) {
                if (!array_key_exists($name, $properties)) {
                    return sprintf('unknown argument "%s"', $name);
                }
            }
        }

        foreach ($arguments as $name => $value) {
            if (!isset($properties[$name]) || !is_array($properties[$name])) {
                continue;
            }

            $error = self::_validateValue((string) $name, $properties[$name], $value);
            if ($error !== null) {
                return $error;
            }
        }

        return null;
    }

    private static function _validateValue(string $name, array $propertySchema, mixed $value): ?string
    {
        $type = $propertySchema['type'] ?? null;

        if ($type === 'string' && !is_string($value)) {
            return sprintf('argument "%s" must be a string', $name);
        }
        if ($type === 'boolean' && !is_bool($value)) {
            return sprintf('argument "%s" must be a boolean', $name);
        }
        if ($type === 'integer' && !is_int($value)) {
            return sprintf('argument "%s" must be an integer', $name);
        }
        if ($type === 'object' && !(is_array($value) && ($value === [] || !array_is_list($value)))) {
            return sprintf('argument "%s" must be an object', $name);
        }

        if (isset($propertySchema['enum']) && is_array($propertySchema['enum'])
            && !in_array($value, $propertySchema['enum'], true)
        ) {
            return sprintf(
                'argument "%s" must be one of: %s',
                $name,
                implode(', ', array_map(static fn($v) => json_encode($v), $propertySchema['enum']))
            );
        }

        if ($type === 'integer') {
            if (isset($propertySchema['minimum']) && $value < $propertySchema['minimum']) {
                return sprintf('argument "%s" must be >= %d', $name, $propertySchema['minimum']);
            }
            if (isset($propertySchema['maximum']) && $value > $propertySchema['maximum']) {
                return sprintf('argument "%s" must be <= %d', $name, $propertySchema['maximum']);
            }
        }

        return null;
    }
}
