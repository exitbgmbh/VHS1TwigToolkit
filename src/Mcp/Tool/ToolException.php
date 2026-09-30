<?php

namespace App\Mcp\Tool;

/**
 * Expected tool error. The message is passed to the agent unchanged (only redacted),
 * so it must never contain paths or config contents.
 */
class ToolException extends \RuntimeException
{
}
