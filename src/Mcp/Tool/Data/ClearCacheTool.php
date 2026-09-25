<?php

namespace App\Mcp\Tool\Data;

use App\Mcp\Tool\ToolContext;
use App\Mcp\Tool\ToolResult;

/**
 * Clears the toolkit cache of the selected config only (its cache namespace).
 */
class ClearCacheTool extends AbstractDataTool
{
    public function getName(): string
    {
        return 'clear_cache';
    }

    public function getDescription(): string
    {
        return 'Clears the toolkit cache of the selected config only; other configs keep their cache. '
            . 'Afterwards context, text modules, document types and the VHS1 login are loaded fresh on the next call.';
    }

    public function getInputSchema(): array
    {
        return [
            'type' => 'object',
            'properties' => [
                'config' => self::configProperty(),
            ],
            'additionalProperties' => false,
        ];
    }

    public function call(array $arguments, ToolContext $context): ToolResult
    {
        $slug = $context->resolveSlug($arguments);

        $cleared = $context->getContainer($slug)->get('cache_service')->clear();

        return ToolResult::ok([
            'config' => $slug,
            'cleared' => $cleared,
        ]);
    }
}
