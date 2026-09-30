<?php

namespace App\Mcp\Tool\Template;

use App\Mcp\Tool\ToolException;
use App\Mcp\Tool\ToolInterface;
use App\Service\TemplateStore;

/**
 * Shared error handling of the template tools: no absolute paths in any message.
 */
abstract class AbstractTemplateTool implements ToolInterface
{
    /**
     * Runs a store operation. Expected errors keep their message, foreign errors become a
     * ToolException; both are passed through TemplateStore::sanitizePaths.
     *
     * @throws ToolException
     */
    protected static function run(TemplateStore $store, callable $operation): array
    {
        try {
            return $operation($store);
        } catch (ToolException $e) {
            throw new ToolException($store->sanitizePaths($e->getMessage()), 0, $e);
        } catch (\Throwable $e) {
            throw new ToolException($store->sanitizePaths($e->getMessage()), 0, $e);
        }
    }
}
