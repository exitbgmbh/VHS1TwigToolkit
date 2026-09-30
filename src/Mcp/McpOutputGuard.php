<?php

namespace App\Mcp;

use Psr\Container\ContainerInterface;

/**
 * Leak protection for MCP output: removes config credentials and JWTs from texts. Never throws.
 */
class McpOutputGuard
{
    private const JWT_PATTERN = '/eyJ[A-Za-z0-9_-]+\.[A-Za-z0-9_-]+\.[A-Za-z0-9_-]*/';

    private const BEARER_PATTERN = '/Bearer\s+\S+/i';

    private const JWT_PLACEHOLDER = '[redacted:jwt]';

    /**
     * @param ContainerInterface[] $containers containers whose config secrets must be removed
     */
    public static function redact(string $text, array $containers): string
    {
        foreach ($containers as $container) {
            try {
                $text = $container->get('secret_redactor')->redact($text);
            } catch (\Throwable $e) {
                // ignore, the generic replacements below still run
            }
        }

        $redacted = preg_replace(self::JWT_PATTERN, self::JWT_PLACEHOLDER, $text);
        if (is_string($redacted)) {
            $text = $redacted;
        }

        $redacted = preg_replace(self::BEARER_PATTERN, self::JWT_PLACEHOLDER, $text);

        return is_string($redacted) ? $redacted : $text;
    }
}
