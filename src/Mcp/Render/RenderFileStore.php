<?php

namespace App\Mcp\Render;

use App\Mcp\Tool\ToolException;

/**
 * Stores render output (PDF, large HTML) below <baseDir>/<session>/ and removes files older than the TTL.
 * The base dir lives in /tmp, which has the same path on the host.
 */
class RenderFileStore
{
    private const SESSION_PATTERN = '/^[a-f0-9]{32}$/';

    private const MAX_NAME_LENGTH = 80;

    private const MAX_COLLISIONS = 1000;

    private string $baseDir;

    private int $ttlSeconds;

    public function __construct(?string $baseDir = null, int $ttlSeconds = 86400)
    {
        $this->baseDir = rtrim($baseDir ?? sys_get_temp_dir() . '/vhs1-mcp-renders', '/');
        $this->ttlSeconds = $ttlSeconds;
    }

    public function getBaseDir(): string
    {
        return $this->baseDir;
    }

    /**
     * Writes the bytes to <baseDir>/<session>/<YYYYmmdd-HHMMSS>-<name>[-n].<extension>.
     *
     * @return string absolute path of the written file
     * @throws ToolException
     */
    public function store(string $sessionId, string $templateName, string $extension, string $bytes): string
    {
        if (preg_match(self::SESSION_PATTERN, $sessionId) !== 1) {
            throw new ToolException('invalid session id for render files');
        }
        if (preg_match('/^[a-z0-9]{1,10}$/', $extension) !== 1) {
            throw new ToolException('invalid file extension for render files');
        }

        $dir = $this->baseDir . '/' . $sessionId;
        $this->_ensureDir($this->baseDir);
        $this->_ensureDir($dir);

        $name = self::sanitizeName($templateName);
        $base = date('Ymd-His') . '-' . $name;

        for ($i = 0; $i < self::MAX_COLLISIONS; $i++) {
            $path = sprintf('%s/%s%s.%s', $dir, $base, $i === 0 ? '' : '-' . $i, $extension);
            $handle = @fopen($path, 'x');
            if ($handle === false) {
                if (file_exists($path)) {
                    continue;
                }
                throw new ToolException('render file could not be created');
            }

            $written = @fwrite($handle, $bytes);
            fclose($handle);
            if ($written !== strlen($bytes)) {
                @unlink($path);
                throw new ToolException('render file could not be written');
            }
            @chmod($path, 0666);

            return $path;
        }

        throw new ToolException('render file could not be created (too many files with the same name)');
    }

    /**
     * Removes files and empty session dirs older than the TTL. Never throws.
     */
    public function gc(): void
    {
        try {
            if (!is_dir($this->baseDir)) {
                return;
            }

            $limit = time() - $this->ttlSeconds;
            foreach (self::_scan($this->baseDir) as $session) {
                $dir = $this->baseDir . '/' . $session;
                if (is_link($dir) || !is_dir($dir)) {
                    continue;
                }

                foreach (self::_scan($dir) as $file) {
                    $path = $dir . '/' . $file;
                    if (!is_link($path) && !is_file($path)) {
                        continue;
                    }
                    $mtime = @lstat($path)['mtime'] ?? null;
                    if ($mtime !== null && $mtime < $limit) {
                        @unlink($path);
                    }
                }

                clearstatcache();
                $mtime = @filemtime($dir);
                if (self::_scan($dir) === [] && $mtime !== false && $mtime < $limit) {
                    @rmdir($dir);
                }
            }
        } catch (\Throwable) {
            // gc is best effort
        }
    }

    /**
     * Template name to file name: everything outside [A-Za-z0-9._-] becomes "_", max 80 chars.
     */
    public static function sanitizeName(string $templateName): string
    {
        $name = preg_replace('/[^A-Za-z0-9._-]/', '_', $templateName) ?? '';
        $name = ltrim(substr($name, 0, self::MAX_NAME_LENGTH), '.');

        return $name === '' ? 'render' : $name;
    }

    /**
     * @throws ToolException
     */
    private function _ensureDir(string $dir): void
    {
        if (is_dir($dir)) {
            return;
        }

        $umask = umask(0);
        try {
            if (!@mkdir($dir, 0777, true) && !is_dir($dir)) {
                throw new ToolException('render directory could not be created');
            }
        } finally {
            umask($umask);
        }
    }

    /**
     * @return string[]
     */
    private static function _scan(string $dir): array
    {
        $entries = @scandir($dir);
        if ($entries === false) {
            return [];
        }

        return array_values(array_filter($entries, static fn (string $e): bool => $e !== '.' && $e !== '..'));
    }
}
