<?php

namespace App\Mcp\Session;

/**
 * State of one MCP session: its id, the selected config slug and timestamps.
 */
class McpSession
{
    private string $id;

    private ?string $selectedSlug;

    private int $createdAt;

    private int $lastSeen;

    public function __construct(string $id, ?string $selectedSlug = null, ?int $createdAt = null, ?int $lastSeen = null)
    {
        $now = time();
        $this->id = $id;
        $this->selectedSlug = $selectedSlug;
        $this->createdAt = $createdAt ?? $now;
        $this->lastSeen = $lastSeen ?? $now;
    }

    public function getId(): string
    {
        return $this->id;
    }

    public function getSelectedSlug(): ?string
    {
        return $this->selectedSlug;
    }

    public function setSelectedSlug(?string $selectedSlug): void
    {
        $this->selectedSlug = $selectedSlug;
    }

    public function getCreatedAt(): int
    {
        return $this->createdAt;
    }

    public function getLastSeen(): int
    {
        return $this->lastSeen;
    }

    public function touch(): void
    {
        $this->lastSeen = time();
    }

    public function toArray(): array
    {
        return [
            'id' => $this->id,
            'selectedSlug' => $this->selectedSlug,
            'createdAt' => $this->createdAt,
            'lastSeen' => $this->lastSeen,
        ];
    }

    public static function fromArray(array $data): self
    {
        $selectedSlug = $data['selectedSlug'] ?? null;

        return new self(
            (string) ($data['id'] ?? ''),
            is_string($selectedSlug) && $selectedSlug !== '' ? $selectedSlug : null,
            isset($data['createdAt']) ? (int) $data['createdAt'] : null,
            isset($data['lastSeen']) ? (int) $data['lastSeen'] : null
        );
    }
}
