<?php

namespace App\Mcp\Tool;

/**
 * Result of a tool call as MCP text content.
 */
class ToolResult
{
    private const JSON_FLAGS = JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT;

    /** @var string[] */
    private array $texts;

    private bool $isError;

    /**
     * @param string[] $texts
     */
    private function __construct(array $texts, bool $isError)
    {
        $this->texts = array_values($texts);
        $this->isError = $isError;
    }

    public static function ok(array|string $data): self
    {
        return new self([self::_toText($data)], false);
    }

    public static function error(string $message): self
    {
        return new self([$message], true);
    }

    public function isError(): bool
    {
        return $this->isError;
    }

    /**
     * @return string[]
     */
    public function getTexts(): array
    {
        return $this->texts;
    }

    /**
     * @param string[] $texts
     */
    public function withTexts(array $texts): self
    {
        return new self(array_map('strval', $texts), $this->isError);
    }

    public function toArray(): array
    {
        $content = [];
        foreach ($this->texts as $text) {
            $content[] = ['type' => 'text', 'text' => $text];
        }

        return [
            'content' => $content,
            'isError' => $this->isError,
        ];
    }

    private static function _toText(array|string $data): string
    {
        if (is_string($data)) {
            return $data;
        }

        $json = json_encode($data, self::JSON_FLAGS | JSON_INVALID_UTF8_SUBSTITUTE);

        return $json === false ? '{}' : $json;
    }
}
