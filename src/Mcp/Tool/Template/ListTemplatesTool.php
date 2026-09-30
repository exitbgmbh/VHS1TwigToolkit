<?php

namespace App\Mcp\Tool\Template;

use App\Mcp\Tool\ToolContext;
use App\Mcp\Tool\ToolResult;
use App\Service\TemplateStore;

/**
 * Lists the templates in slip/ and email/ of all template sets.
 */
class ListTemplatesTool extends AbstractTemplateTool
{
    public function getName(): string
    {
        return 'list_templates';
    }

    public function getDescription(): string
    {
        return 'Lists the PDF (slip/) and email (email/) templates of all template sets below src/Templates, '
            . 'recursively. Per entry, "name" is the template name for render_template and "path" is the '
            . 'relative path for read_template and write_template. Filter with "set" and "kind". '
            . 'Needs no config.';
    }

    public function getInputSchema(): array
    {
        return [
            'type' => 'object',
            'properties' => [
                'set' => [
                    'type' => 'string',
                    'description' => 'Only templates of this template set (level 1 directory, e.g. "shopcartel").',
                ],
                'kind' => [
                    'type' => 'string',
                    'enum' => ['pdf', 'email'],
                    'description' => 'Only PDF templates (slip/) or only email templates (email/).',
                ],
            ],
            'additionalProperties' => false,
        ];
    }

    public function call(array $arguments, ToolContext $context): ToolResult
    {
        $set = isset($arguments['set']) ? (string) $arguments['set'] : null;
        $kind = isset($arguments['kind']) ? (string) $arguments['kind'] : null;

        return ToolResult::ok(self::run(
            new TemplateStore(),
            static fn (TemplateStore $store): array => $store->listTemplates($set, $kind)
        ));
    }
}
