<?php

namespace App\Mcp\Tool\Template;

use App\Mcp\Tool\ToolContext;
use App\Mcp\Tool\ToolResult;
use App\Service\TemplateStore;

/**
 * Writes (replaces or creates) a template file below src/Templates.
 */
class WriteTemplateTool extends AbstractTemplateTool
{
    public function getName(): string
    {
        return 'write_template';
    }

    public function getDescription(): string
    {
        return 'Writes a file below src/Templates by its relative path. The file content is replaced completely. '
            . 'New files can only be created in existing directories; no directories are created and nothing '
            . 'is deleted. Allowed extensions: ' . implode(', ', TemplateStore::ALLOWED_EXTENSIONS)
            . '. Content must be UTF-8, at most ' . TemplateStore::MAX_BYTES . ' bytes. Recommended: pass '
            . 'expectedSha256 from read_template so concurrent changes are not overwritten. Needs no config.';
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
                'content' => [
                    'type' => 'string',
                    'description' => 'Complete new file content.',
                ],
                'expectedSha256' => [
                    'type' => 'string',
                    'description' => 'sha256 from read_template; the write fails if the file changed since.',
                ],
            ],
            'required' => ['path', 'content'],
            'additionalProperties' => false,
        ];
    }

    public function call(array $arguments, ToolContext $context): ToolResult
    {
        $path = (string) $arguments['path'];
        $content = (string) $arguments['content'];
        $expected = isset($arguments['expectedSha256']) ? (string) $arguments['expectedSha256'] : null;

        return ToolResult::ok(self::run(
            new TemplateStore(),
            static fn (TemplateStore $store): array => $store->write($path, $content, $expected)
        ));
    }
}
