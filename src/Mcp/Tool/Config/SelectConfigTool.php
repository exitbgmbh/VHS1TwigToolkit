<?php

namespace App\Mcp\Tool\Config;

use App\Mcp\Tool\ToolContext;
use App\Mcp\Tool\ToolException;
use App\Mcp\Tool\ToolInterface;
use App\Mcp\Tool\ToolResult;

/**
 * Selects a config slug for the MCP session. Reads no config contents and builds no container.
 */
class SelectConfigTool implements ToolInterface
{
    public function getName(): string
    {
        return 'select_config';
    }

    public function getDescription(): string
    {
        return 'Selects the config (VHS1 instance) for this MCP session by slug, see list_configs. '
            . 'Later tool calls use it unless they pass "config". Does not change the toolkit UI.';
    }

    public function getInputSchema(): array
    {
        return [
            'type' => 'object',
            'properties' => [
                'config' => [
                    'type' => 'string',
                    'description' => 'Config slug from list_configs, e.g. "default".',
                ],
            ],
            'required' => ['config'],
            'additionalProperties' => false,
        ];
    }

    public function call(array $arguments, ToolContext $context): ToolResult
    {
        $slug = (string) $arguments['config'];
        if (!$context->getRegistry()->exists($slug)) {
            throw new ToolException(sprintf('Unknown config "%s"', $slug));
        }

        $context->getSession()->setSelectedSlug($slug);

        return ToolResult::ok(['selected' => $slug]);
    }
}
