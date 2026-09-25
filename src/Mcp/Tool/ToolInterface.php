<?php

namespace App\Mcp\Tool;

/**
 * An MCP tool. Tools have no constructor dependencies; they get everything via ToolContext.
 */
interface ToolInterface
{
    public function getName(): string;

    public function getDescription(): string;

    /**
     * @return array JSON schema of the arguments as PHP array
     */
    public function getInputSchema(): array;

    /**
     * @throws ToolException for expected errors, message goes to the agent (redacted)
     */
    public function call(array $arguments, ToolContext $context): ToolResult;
}
