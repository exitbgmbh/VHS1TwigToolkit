<?php

namespace App\Mcp\Tool\Data;

use App\Mcp\Tool\ToolContext;
use App\Mcp\Tool\ToolResult;
use App\Service\TypesService;

/**
 * Lists the document kinds and types of the selected config via types_service.
 */
class ListDocumentTypesTool extends AbstractDataTool
{
    public function getName(): string
    {
        return 'list_document_types';
    }

    public function getDescription(): string
    {
        return 'Lists document kinds (pdf, email, ...) and the document types per kind of the selected config. '
            . 'Each type has "name" (label), "renderer" (may carry a "###<name>" suffix to keep duplicates apart) '
            . 'and "type" (renderer without suffix). Pass "renderer" or "type" as "type" to get_context.';
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

        /** @var TypesService $typesService */
        $typesService = $context->getContainer($slug)->get('types_service');

        try {
            $typesModel = $typesService->getTypes($forceReload);
        } catch (\TypeError $e) {
            throw self::requestFailed('document types request failed: unexpected VHS1 answer', $e);
        }

        $types = [];
        foreach ($typesModel->getTypes() as $kind => $entries) {
            $types[$kind] = [];
            foreach ($entries as $entry) {
                $renderer = (string) ($entry['renderer'] ?? '');
                $types[$kind][] = [
                    'name' => $entry['name'] ?? null,
                    'renderer' => $renderer,
                    'type' => $typesService->getRealType($renderer),
                ];
            }
        }

        return ToolResult::ok([
            'config' => $slug,
            'kinds' => (object) $typesModel->getKinds(),
            'types' => (object) $types,
        ]);
    }
}
