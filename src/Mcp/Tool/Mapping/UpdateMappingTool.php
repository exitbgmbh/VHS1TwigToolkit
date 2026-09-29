<?php

namespace App\Mcp\Tool\Mapping;

use App\Mcp\Tool\Data\AbstractDataTool;
use App\Mcp\Tool\ToolContext;
use App\Mcp\Tool\ToolResult;
use App\Service\MappingStore;

/**
 * Changes mapping.defaults or mapping.textModuleMapping of a config via JSON Merge Patch.
 */
class UpdateMappingTool extends AbstractDataTool
{
    public function getName(): string
    {
        return 'update_mapping';
    }

    public function getDescription(): string
    {
        return 'Changes one mapping section of the config via JSON Merge Patch (RFC 7396): objects are merged '
            . 'recursively, null deletes a key, any other value replaces it. Example patch for section '
            . '"textModuleMapping": {"defaults": {"tm.phoneNumber": "030 123"}}. Pass expectedSha256 from '
            . 'get_mapping to avoid overwriting concurrent changes. The change takes effect immediately for '
            . 'get_context, get_text_modules and render_template (no clear_cache needed). A backup is written '
            . 'before every change. Credentials are never touched. Redacted placeholders are rejected.';
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
                    'description' => 'Mapping section to patch.',
                ],
                'patch' => [
                    'type' => 'object',
                    'description' => 'JSON Merge Patch applied to mapping.<section>.',
                ],
                'expectedSha256' => [
                    'type' => 'string',
                    'description' => 'sha256 from get_mapping; the call fails if the mapping changed since.',
                ],
            ],
            'required' => ['section', 'patch'],
            'additionalProperties' => false,
        ];
    }

    public function call(array $arguments, ToolContext $context): ToolResult
    {
        $slug = $context->resolveSlug($arguments);
        $section = (string) $arguments['section'];
        $expectedSha256 = isset($arguments['expectedSha256']) ? (string) $arguments['expectedSha256'] : null;

        $result = (new MappingStore($context->getRegistry()))
            ->patch($slug, $section, $arguments['patch'], $expectedSha256);

        return ToolResult::ok([
            'config' => $slug,
            'section' => $section,
            'changed' => $result['changed'],
            'sha256Before' => $result['sha256Before'],
            'sha256' => $result['sha256'],
            'backup' => $result['backup'],
            $section => $result['section'],
        ]);
    }
}
