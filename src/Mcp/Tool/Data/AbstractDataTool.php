<?php

namespace App\Mcp\Tool\Data;

use App\Mcp\Tool\ToolException;
use App\Mcp\Tool\ToolInterface;

/**
 * Shared schema parts and error helpers of the data tools.
 */
abstract class AbstractDataTool implements ToolInterface
{
    /** Max JSON size of returned data before the size guard kicks in. */
    protected const MAX_DATA_BYTES = 100000;

    protected const JSON_FLAGS = JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_INVALID_UTF8_SUBSTITUTE;

    protected static function configProperty(): array
    {
        return [
            'type' => 'string',
            'description' => 'Config slug for this call only; defaults to the slug selected via select_config.',
        ];
    }

    protected static function forceReloadProperty(): array
    {
        return [
            'type' => 'boolean',
            'description' => 'Bypass the toolkit cache and fetch fresh data from VHS1 (default false).',
        ];
    }

    protected static function jsonBytes(mixed $data): int
    {
        $json = json_encode($data, self::JSON_FLAGS);

        return $json === false ? 0 : strlen($json);
    }

    /**
     * Builds an understandable error for an empty or unexpected VHS1 answer.
     * The original message is appended; McpServer redacts the whole text.
     */
    protected static function requestFailed(string $what, ?\Throwable $cause = null): ToolException
    {
        $message = $what;
        if ($cause !== null) {
            $message .= sprintf(
                ' (caused by: %s: %s)',
                (new \ReflectionClass($cause))->getShortName(),
                $cause->getMessage()
            );
        }

        return new ToolException($message, 0, $cause);
    }
}
