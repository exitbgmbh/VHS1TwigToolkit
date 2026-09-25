<?php

namespace App\Mcp\Session;

/**
 * Stores MCP sessions as JSON files. Sessions expire after $ttlSeconds without use.
 */
class McpSessionStore
{
    private const ID_PATTERN = '/^[a-f0-9]{32}$/';

    private string $dir;

    private int $ttlSeconds;

    public function __construct(?string $dir = null, int $ttlSeconds = 86400)
    {
        $this->dir = rtrim($dir ?? sys_get_temp_dir() . '/vhs1-mcp-sessions', '/');
        $this->ttlSeconds = $ttlSeconds;
    }

    public function create(): McpSession
    {
        $this->gc();

        $session = new McpSession(bin2hex(random_bytes(16)));
        $this->save($session);

        return $session;
    }

    public function load(string $id): ?McpSession
    {
        if (!preg_match(self::ID_PATTERN, $id)) {
            return null;
        }

        $file = $this->_path($id);
        if (!is_file($file)) {
            return null;
        }

        $raw = @file_get_contents($file);
        $data = is_string($raw) ? json_decode($raw, true) : null;
        if (!is_array($data)) {
            return null;
        }

        $session = McpSession::fromArray($data);
        if ($session->getId() !== $id || $this->_isExpired($session->getLastSeen())) {
            @unlink($file);

            return null;
        }

        return $session;
    }

    public function save(McpSession $session): void
    {
        $this->_ensureDir();
        $session->touch();

        file_put_contents(
            $this->_path($session->getId()),
            json_encode($session->toArray(), JSON_UNESCAPED_SLASHES),
            LOCK_EX
        );
    }

    public function delete(string $id): bool
    {
        if (!preg_match(self::ID_PATTERN, $id)) {
            return false;
        }

        $file = $this->_path($id);

        return is_file($file) && @unlink($file);
    }

    public function gc(): void
    {
        foreach (glob($this->dir . '/*.json') ?: [] as $file) {
            $raw = @file_get_contents($file);
            $data = is_string($raw) ? json_decode($raw, true) : null;
            $lastSeen = is_array($data) && isset($data['lastSeen']) ? (int) $data['lastSeen'] : (int) @filemtime($file);

            if ($this->_isExpired($lastSeen)) {
                @unlink($file);
            }
        }
    }

    private function _isExpired(int $lastSeen): bool
    {
        return $lastSeen < time() - $this->ttlSeconds;
    }

    private function _path(string $id): string
    {
        return $this->dir . '/' . $id . '.json';
    }

    private function _ensureDir(): void
    {
        if (!is_dir($this->dir)) {
            @mkdir($this->dir, 0777, true);
            @chmod($this->dir, 0777);
        }
    }
}
