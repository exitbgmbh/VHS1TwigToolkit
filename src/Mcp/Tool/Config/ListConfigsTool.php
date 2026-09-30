<?php

namespace App\Mcp\Tool\Config;

use App\Mcp\Tool\ToolContext;
use App\Mcp\Tool\ToolInterface;
use App\Mcp\Tool\ToolResult;

/**
 * Lists the available config slugs and the slug selected in the session.
 * Reads no config contents and builds no container.
 */
class ListConfigsTool implements ToolInterface
{
    public function getName(): string
    {
        return 'list_configs';
    }

    public function getDescription(): string
    {
        return 'Lists the available toolkit configs (VHS1 instances) by slug and shows the slug selected '
            . 'in this session. The slug "default" stands for the standard config. Data, template and '
            . 'render tools need a selected config: call select_config or pass "config" per call.';
    }

    public function getInputSchema(): array
    {
        return [
            'type' => 'object',
            'properties' => new \stdClass(),
            'additionalProperties' => false,
        ];
    }

    public function call(array $arguments, ToolContext $context): ToolResult
    {
        return ToolResult::ok([
            'configs' => $context->getRegistry()->listSlugs(),
            'selected' => $context->getSession()->getSelectedSlug(),
        ]);
    }
}
