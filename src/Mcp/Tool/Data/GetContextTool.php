<?php

namespace App\Mcp\Tool\Data;

use App\Mcp\Tool\ToolContext;
use App\Mcp\Tool\ToolResult;
use App\Service\CacheService;
use App\Service\TypesService;

/**
 * Returns the document context exactly as a template sees it (mapped with mapping.defaults).
 */
class GetContextTool extends AbstractDataTool
{
    public function getName(): string
    {
        return 'get_context';
    }

    public function getDescription(): string
    {
        return 'Returns the context data of a document as the template sees it (already merged with '
            . 'mapping.defaults). Start with mode="keys" to see the structure, then narrow with "path" '
            . '(dot notation, list indexes as numbers, e.g. "order.items.0"). Large results are replaced by '
            . 'a structure summary. Results are cached per kind, type and identifiers; use forceReload for fresh data. '
            . 'Objects of the form {date, timezone_type, timezone} are DateTime objects in the template: use them '
            . 'directly with the date filter (e.g. "invoice.date|date(\'d.m.Y\')"), not their "date" key.';
    }

    public function getInputSchema(): array
    {
        return [
            'type' => 'object',
            'properties' => [
                'config' => self::configProperty(),
                'kind' => [
                    'type' => 'string',
                    'enum' => ['pdf', 'email'],
                    'description' => 'Document kind.',
                ],
                'type' => [
                    'type' => 'string',
                    'description' => 'Document type from list_document_types, e.g. "invoice" (a "###" suffix is allowed).',
                ],
                'identifiers' => [
                    'type' => 'string',
                    'description' => 'Document identifier(s), e.g. the invoice number.',
                ],
                'productId' => [
                    'type' => 'string',
                    'description' => 'Optional product id for product-related documents (default "").',
                ],
                'forceReload' => self::forceReloadProperty(),
                'path' => [
                    'type' => 'string',
                    'description' => 'Dot path into the context, e.g. "invoice.items". Default: whole context.',
                ],
                'mode' => [
                    'type' => 'string',
                    'enum' => ['full', 'keys'],
                    'description' => '"full" returns values, "keys" returns only the structure (default "full").',
                ],
                'maxDepth' => [
                    'type' => 'integer',
                    'minimum' => 1,
                    'maximum' => 10,
                    'description' => 'Depth of the structure for mode="keys" (default 4).',
                ],
            ],
            'required' => ['kind', 'type', 'identifiers'],
            'additionalProperties' => false,
        ];
    }

    public function call(array $arguments, ToolContext $context): ToolResult
    {
        $slug = $context->resolveSlug($arguments);

        $kind = (string) $arguments['kind'];
        $typeArgument = (string) $arguments['type'];
        $identifiers = (string) $arguments['identifiers'];
        $productId = (string) ($arguments['productId'] ?? '');
        $forceReload = (bool) ($arguments['forceReload'] ?? false);
        $path = (string) ($arguments['path'] ?? '');
        $mode = (string) ($arguments['mode'] ?? 'full');
        $maxDepth = (int) ($arguments['maxDepth'] ?? 4);

        $container = $context->getContainer($slug);
        /** @var TypesService $typesService */
        $typesService = $container->get('types_service');
        /** @var CacheService $cacheService */
        $cacheService = $container->get('cache_service');
        $type = $typesService->getRealType($typeArgument);

        $jwt = $container->get('security_service')->getJwt($forceReload);

        $failure = sprintf('context request failed for type "%s" identifiers "%s"', $type, $identifiers);
        try {
            $data = $container->get('context_service')
                ->getContext($kind, $type, $identifiers, $productId, $jwt, $forceReload);
        } catch (\TypeError $e) {
            throw self::requestFailed($failure . ': unexpected VHS1 answer', $e);
        }

        // context_service caches the raw VHS1 answer; an empty one means VHS1 knows no such document
        $rawKey = $cacheService->getContextCacheKey($kind, $type, $identifiers, $productId);
        $raw = $cacheService->has($rawKey) ? $cacheService->get($rawKey)->get() : null;
        if (empty($raw)) {
            throw self::requestFailed(
                $failure . ': VHS1 returned an empty context (unknown identifiers or wrong type?)'
            );
        }

        $data = ArrayInspector::getByPath($data, $path);

        if ($mode === 'keys') {
            $data = ArrayInspector::describe($data, $maxDepth);
        } else {
            $bytes = self::jsonBytes($data);
            if ($bytes > self::MAX_DATA_BYTES) {
                $data = [
                    'tooLarge' => true,
                    'bytes' => $bytes,
                    'hint' => 'use path or mode=keys',
                    'structure' => ArrayInspector::describe($data, 2),
                ];
            }
        }

        return ToolResult::ok([
            'config' => $slug,
            'kind' => $kind,
            'type' => $typeArgument,
            'identifiers' => $identifiers,
            'path' => $path,
            'mode' => $mode,
            'data' => $data,
        ]);
    }
}
