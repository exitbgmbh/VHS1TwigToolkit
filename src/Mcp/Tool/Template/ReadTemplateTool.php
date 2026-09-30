<?php

namespace App\Mcp\Tool\Template;

use App\Mcp\Tool\ToolContext;
use App\Mcp\Tool\ToolResult;
use App\Service\TemplateStore;

/**
 * Reads a template file below src/Templates.
 */
class ReadTemplateTool extends AbstractTemplateTool
{
    public function getName(): string
    {
        return 'read_template';
    }

    public function getDescription(): string
    {
        return 'Reads a file below src/Templates by its relative path (e.g. "shopcartel/slip/default_invoice.html", '
            . 'see list_templates). Allowed extensions: ' . implode(', ', TemplateStore::ALLOWED_EXTENSIONS)
            . '. Returns content, bytes, lines, modified and sha256; pass sha256 as expectedSha256 to '
            . 'write_template. Note: content may be redacted if it contains token-like strings. Needs no config.';
    }

    public function getInputSchema(): array
    {
        return [
            'type' => 'object',
            'properties' => [
                'path' => [
                    'type' => 'string',
                    'description' => 'Path relative to src/Templates, at least "<set>/<file>".',
                ],
            ],
            'required' => ['path'],
            'additionalProperties' => false,
        ];
    }

    public function call(array $arguments, ToolContext $context): ToolResult
    {
        $path = (string) $arguments['path'];

        return ToolResult::ok(self::run(
            new TemplateStore(),
            static fn (TemplateStore $store): array => $store->read($path)
        ));
    }
}
