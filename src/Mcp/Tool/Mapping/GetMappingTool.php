<?php

namespace App\Mcp\Tool\Mapping;

use App\Mcp\Tool\Data\AbstractDataTool;
use App\Mcp\Tool\ToolContext;
use App\Mcp\Tool\ToolResult;
use App\Service\MappingStore;

/**
 * Returns mapping.defaults and/or mapping.textModuleMapping of a config (raw values).
 */
class GetMappingTool extends AbstractDataTool
{
    public function getName(): string
    {
        return 'get_mapping';
    }

    public function getDescription(): string
    {
        return 'Returns the mapping of the config. "defaults" is merged recursively over the document context '
            . '(top-level keys like "invoice" or "system"). "textModuleMapping" overrides text modules per group '
            . '("defaults", "defaults-<lang>", "<advertisingMediumCode>", "<advertisingMediumCode>-<lang>"), each '
            . 'group being {key: string}. Without "section" both sections are returned. Pass the returned sha256 '
            . 'as expectedSha256 to update_mapping.';
    }

    public function getInputSchema(): array
    {
        return [
            'type' => 'object',
            'properties' => [
                'config' => self::configProperty(),
                'section' => [
                    'type' => 'string',
                    'enum' => MappingStore::SECTIONS,
                    'description' => 'Only return this section (default: both).',
                ],
            ],
            'additionalProperties' => false,
        ];
    }

    public function call(array $arguments, ToolContext $context): ToolResult
    {
        $slug = $context->resolveSlug($arguments);
        $mapping = (new MappingStore($context->getRegistry()))->read($slug);

        $result = [
            'config' => $slug,
            'sha256' => $mapping['sha256'],
        ];
        foreach (MappingStore::SECTIONS as $section) {
            if (!isset($arguments['section']) || $arguments['section'] === $section) {
                $result[$section] = $mapping[$section];
            }
        }

        return ToolResult::ok($result);
    }
}
