<?php

namespace App\Mcp\Tool;

use App\Mcp\Tool\Config\ListConfigsTool;
use App\Mcp\Tool\Config\SelectConfigTool;
use App\Mcp\Tool\Data\ClearCacheTool;
use App\Mcp\Tool\Data\GetContextTool;
use App\Mcp\Tool\Data\GetTextModulesTool;
use App\Mcp\Tool\Data\ListDocumentTypesTool;
use App\Mcp\Tool\Data\ListLanguagesTool;

/**
 * Holds the MCP tools by name.
 */
class ToolRegistry
{
    /** @var array<string, ToolInterface> */
    private array $tools = [];

    public static function createDefault(): self
    {
        $registry = new self();
        $registry->register(new ListConfigsTool());
        $registry->register(new SelectConfigTool());
        $registry->register(new ListDocumentTypesTool());
        $registry->register(new ListLanguagesTool());
        $registry->register(new GetContextTool());
        $registry->register(new GetTextModulesTool());
        $registry->register(new ClearCacheTool());

        return $registry;
    }

    public function register(ToolInterface $tool): void
    {
        $this->tools[$tool->getName()] = $tool;
    }

    public function get(string $name): ?ToolInterface
    {
        return $this->tools[$name] ?? null;
    }

    /**
     * @return ToolInterface[]
     */
    public function all(): array
    {
        return array_values($this->tools);
    }
}
