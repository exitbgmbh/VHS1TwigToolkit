<?php

namespace App\Mcp\Tool\Data;

use App\Mcp\Tool\ToolContext;
use App\Mcp\Tool\ToolResult;

/**
 * Returns the text modules as a template sees them (merged with mapping.textModuleMapping).
 */
class GetTextModulesTool extends AbstractDataTool
{
    private const MAX_LISTED_KEYS = 500;

    public function getName(): string
    {
        return 'get_text_modules';
    }

    public function getDescription(): string
    {
        return 'Returns the text modules {key: value} as templates see them via exitbTm (already merged with '
            . 'mapping.textModuleMapping), sorted by key. Narrow with "prefix". The cache is per '
            . 'advertisingMediumCode and language; "template" only matters when the modules are (re)loaded '
            . 'from VHS1, so use forceReload to load them for another template. Language codes: list_languages.';
    }

    public function getInputSchema(): array
    {
        return [
            'type' => 'object',
            'properties' => [
                'config' => self::configProperty(),
                'advertisingMediumCode' => [
                    'type' => 'string',
                    'description' => 'Advertising medium code (default "", e.g. order.advertisingMedium.code of the context).',
                ],
                'language' => [
                    'type' => 'string',
                    'description' => 'Language isoCode from list_languages (default "").',
                ],
                'template' => [
                    'type' => 'string',
                    'description' => 'Template name used when loading from VHS1, e.g. "invoice.html" (default "").',
                ],
                'prefix' => [
                    'type' => 'string',
                    'description' => 'Only return keys starting with this prefix.',
                ],
                'forceReload' => self::forceReloadProperty(),
            ],
            'additionalProperties' => false,
        ];
    }

    public function call(array $arguments, ToolContext $context): ToolResult
    {
        $slug = $context->resolveSlug($arguments);

        $advertisingMediumCode = (string) ($arguments['advertisingMediumCode'] ?? '');
        $language = (string) ($arguments['language'] ?? '');
        $template = (string) ($arguments['template'] ?? '');
        $prefix = (string) ($arguments['prefix'] ?? '');
        $forceReload = (bool) ($arguments['forceReload'] ?? false);

        $container = $context->getContainer($slug);
        $jwt = $container->get('security_service')->getJwt($forceReload);

        try {
            $textModules = $container->get('text_modules_service')
                ->getTextModules($template, $advertisingMediumCode, $language, $jwt, $forceReload);
        } catch (\TypeError $e) {
            throw self::requestFailed(sprintf(
                'text modules request failed for advertisingMediumCode "%s" language "%s": unexpected VHS1 answer',
                $advertisingMediumCode,
                $language
            ), $e);
        }

        ksort($textModules, SORT_STRING);
        if ($prefix !== '') {
            $textModules = array_filter(
                $textModules,
                static fn($key): bool => str_starts_with((string) $key, $prefix),
                ARRAY_FILTER_USE_KEY
            );
        }

        $result = (object) $textModules;
        $bytes = self::jsonBytes($result);
        if ($bytes > self::MAX_DATA_BYTES) {
            $result = [
                'tooLarge' => true,
                'bytes' => $bytes,
                'hint' => 'use prefix',
                'keys' => array_slice(array_map('strval', array_keys($textModules)), 0, self::MAX_LISTED_KEYS),
            ];
        }

        return ToolResult::ok([
            'config' => $slug,
            'advertisingMediumCode' => $advertisingMediumCode,
            'language' => $language,
            'count' => count($textModules),
            'textModules' => $result,
        ]);
    }
}
