<?php

namespace App\Mcp\Tool\Data;

use App\Mcp\Tool\ToolContext;
use App\Mcp\Tool\ToolResult;

/**
 * Lists the languages of the selected config via language_service, reduced to code and name.
 */
class ListLanguagesTool extends AbstractDataTool
{
    public function getName(): string
    {
        return 'list_languages';
    }

    public function getDescription(): string
    {
        return 'Lists the languages of the selected config as {"isoCode", "name"}. Use "isoCode" as "language" '
            . 'for get_text_modules. The list is empty on old VHS1 builds without language API; then use "".';
    }

    public function getInputSchema(): array
    {
        return [
            'type' => 'object',
            'properties' => [
                'config' => self::configProperty(),
                'forceReload' => self::forceReloadProperty(),
            ],
            'additionalProperties' => false,
        ];
    }

    public function call(array $arguments, ToolContext $context): ToolResult
    {
        $slug = $context->resolveSlug($arguments);
        $forceReload = (bool) ($arguments['forceReload'] ?? false);

        try {
            $raw = $context->getContainer($slug)->get('language_service')->getLanguages($forceReload);
        } catch (\TypeError $e) {
            throw self::requestFailed('languages request failed: unexpected VHS1 answer', $e);
        }

        // the raw entries are large and carry user records (creator, modifier); keep code and name only
        $languages = [];
        foreach ($raw as $entry) {
            if (!is_array($entry)) {
                continue;
            }
            $languages[] = [
                'isoCode' => $entry['isoCode'] ?? null,
                'name' => $entry['name'] ?? null,
            ];
        }

        return ToolResult::ok([
            'config' => $slug,
            'languages' => $languages,
        ]);
    }
}
