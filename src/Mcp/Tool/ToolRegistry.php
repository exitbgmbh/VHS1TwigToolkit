<?php

namespace App\Mcp\Tool;

use App\Mcp\Tool\Config\ListConfigsTool;
use App\Mcp\Tool\Config\SelectConfigTool;

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
