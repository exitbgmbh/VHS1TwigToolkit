<?php

namespace App\Service;

use App\Mcp\Tool\ToolException;

/**
 * File access to the templates below src/Templates for the MCP template tools.
 *
 * Only files below an allowed root are readable or writable: src/Templates itself and the
 * targets of directory symlinks on level 1 (src/Templates/<a>) and level 2 (src/Templates/<a>/<b>).
 * File symlinks and deeper directory symlinks are no roots; their targets must lie below a root.
 *
 * Absolute paths are for internal use only. Everything returned to the agent (results and
 * exception messages) contains relative template paths only.
 */
class TemplateStore
{
    public const ALLOWED_EXTENSIONS = ['html', 'twig', 'css', 'txt', 'json', 'svg'];

    public const MAX_BYTES = 1048576;

    private const MAX_PATH_LENGTH = 500;

    private const LIST_MAX_DEPTH = 6;

    private const LIST_MAX_ENTRIES = 3000;

    /** Sub directory per render kind. */
    private const KIND_DIRS = ['slip' => 'pdf', 'email' => 'email'];

    private string $rootDir;

    /** Lexically normalised root dir without realpath (e.g. /var/www/html/src/Templates). */
    private string $logicalRootDir;

    /** @var array<string, string>|null realpath => relative prefix */
    private ?array $roots = null;

    public function __construct(?string $rootDir = null)
    {
        $this->rootDir = rtrim($rootDir ?? __DIR__ . '/../Templates', '/');
        $this->logicalRootDir = self::_normalizeLexically($this->rootDir);
    }

    /**
     * Allowed roots as realpath => relative prefix ('' for src/Templates itself).
     *
     * @return array<string, string>
     */
    public function getRoots(): array
    {
        if ($this->roots !== null) {
            return $this->roots;
        }

        $roots = [];
        $rootReal = realpath($this->rootDir);
        if ($rootReal === false || !is_dir($rootReal)) {
            return $this->roots = [];
        }
        $roots[$rootReal] = '';

        foreach (self::_scanDir($this->rootDir) as $a) {
            $levelOne = $this->rootDir . '/' . $a;
            if (!is_dir($levelOne)) {
                continue;
            }
            if (is_link($levelOne)) {
                self::_addRoot($roots, $levelOne, $a);
            }
            foreach (self::_scanDir($levelOne) as $b) {
                $levelTwo = $levelOne . '/' . $b;
                if (is_link($levelTwo) && is_dir($levelTwo)) {
                    self::_addRoot($roots, $levelTwo, $a . '/' . $b);
                }
            }
        }

        return $this->roots = $roots;
    }

    /**
     * Resolves an existing template file. Returns the absolute resolved path for internal use only.
     *
     * @throws ToolException
     */
    public function resolveExisting(string $path): string
    {
        $this->_validatePath($path);

        $full = $this->rootDir . '/' . $path;
        if (!file_exists($full)) {
            throw new ToolException(sprintf('path "%s" not found', $path));
        }

        return $this->_resolveExistingFull($path, $full);
    }

    /**
     * Resolves a path for writing. For new files the parent directory must exist below an allowed root.
     *
     * @return array{absolute: string, exists: bool}
     * @throws ToolException
     */
    public function resolveForWrite(string $path): array
    {
        $this->_validatePath($path);

        $full = $this->rootDir . '/' . $path;
        if (file_exists($full)) {
            return ['absolute' => $this->_resolveExistingFull($path, $full), 'exists' => true];
        }
        if (is_link($full)) {
            throw new ToolException(sprintf('path "%s" is a broken symlink', $path));
        }

        $parent = dirname($full);
        if (!is_dir($parent)) {
            throw new ToolException(sprintf('directory for "%s" does not exist', $path));
        }
        $parentReal = realpath($parent);
        if ($parentReal === false || !$this->_isBelowRoot($parentReal, true)) {
            throw new ToolException(sprintf('path "%s" is outside src/Templates', $path));
        }

        $absolute = $parentReal . '/' . basename($full);
        if (is_link($absolute) || file_exists($absolute)) {
            throw new ToolException(sprintf('path "%s" cannot be created', $path));
        }

        return ['absolute' => $absolute, 'exists' => false];
    }

    /**
     * Maps an absolute path to the relative template path, or null if it is not below a root.
     */
    public function toRelativePath(string $absolutePath): ?string
    {
        $candidates = $this->getRoots();
        $candidates[$this->logicalRootDir] = '';
        $candidates[$this->rootDir] = '';

        $bestRoot = null;
        foreach (array_keys($candidates) as $root) {
            $root = (string) $root;
            if ($absolutePath !== $root && !str_starts_with($absolutePath, rtrim($root, '/') . '/')) {
                continue;
            }
            if ($bestRoot === null || strlen($root) > strlen($bestRoot)) {
                $bestRoot = $root;
            }
        }
        if ($bestRoot === null) {
            return null;
        }

        $prefix = $candidates[$bestRoot];
        $rest = ltrim(substr($absolutePath, strlen(rtrim($bestRoot, '/'))), '/');
        if ($prefix === '') {
            return $rest;
        }

        return $rest === '' ? $prefix : $prefix . '/' . $rest;
    }

    /**
     * Replaces absolute server and host paths in a free text. Never throws.
     */
    public function sanitizePaths(string $text): string
    {
        try {
            $candidates = $this->getRoots();
            $candidates[$this->logicalRootDir] = '';
            $candidates[$this->rootDir] = '';

            $keys = array_map('strval', array_keys($candidates));
            usort($keys, static fn (string $x, string $y): int => strlen($y) <=> strlen($x));

            foreach ($keys as $root) {
                $root = rtrim($root, '/');
                if ($root === '') {
                    continue;
                }
                $prefix = $candidates[$root] ?? $candidates[$root . '/'] ?? '';
                $pattern = '#' . preg_quote($root, '#') . '(/|(?![\w.-]))#';
                $replaced = preg_replace_callback(
                    $pattern,
                    static function (array $m) use ($prefix): string {
                        if ($prefix === '') {
                            return 'templates:';
                        }

                        return 'templates:' . $prefix . $m[1];
                    },
                    $text
                );
                if ($replaced !== null) {
                    $text = $replaced;
                }
            }

            $text = str_replace('/var/www/html/', 'app:', $text);
            $replaced = preg_replace('#/home/\S+#', '[path]', $text);

            return $replaced ?? $text;
        } catch (\Throwable) {
            return 'error message withheld (path sanitizing failed)';
        }
    }

    /**
     * Lists templates in slip/ and email/ of the root and of every level 1 set.
     *
     * @throws ToolException
     */
    public function listTemplates(?string $set = null, ?string $kind = null): array
    {
        if ($kind !== null && !in_array($kind, self::KIND_DIRS, true)) {
            throw new ToolException(sprintf('unknown kind "%s"; available: pdf, email', $kind));
        }

        $sets = $this->_listSets();
        if ($set !== null && !in_array($set, $sets, true)) {
            throw new ToolException(sprintf(
                'unknown template set "%s"; available: %s',
                $set,
                implode(', ', $sets)
            ));
        }

        $bases = [];
        if ($set === null) {
            $bases[] = null;
            foreach ($sets as $name) {
                $bases[] = $name;
            }
        } else {
            $bases[] = $set;
        }

        $templates = [];
        $truncated = false;
        foreach ($bases as $base) {
            foreach (self::KIND_DIRS as $dir => $dirKind) {
                if ($kind !== null && $kind !== $dirKind) {
                    continue;
                }
                $relDir = $base === null ? $dir : $base . '/' . $dir;
                $this->_walk($relDir, '', 1, $base, $dirKind, $templates, $truncated);
            }
        }

        usort($templates, static fn (array $x, array $y): int => strcmp($x['path'], $y['path']));

        return [
            'sets' => $sets,
            'count' => count($templates),
            'truncated' => $truncated,
            'templates' => $templates,
        ];
    }

    /**
     * @return array{path: string, bytes: int, sha256: string, lines: int, modified: string, content: string}
     * @throws ToolException
     */
    public function read(string $path): array
    {
        $absolute = $this->resolveExisting($path);

        $bytes = @filesize($absolute);
        if ($bytes === false) {
            throw new ToolException(sprintf('path "%s" is not readable', $path));
        }
        if ($bytes > self::MAX_BYTES) {
            throw new ToolException(sprintf(
                'file "%s" has %d bytes; the maximum is %d bytes',
                $path,
                $bytes,
                self::MAX_BYTES
            ));
        }

        $content = @file_get_contents($absolute);
        if ($content === false) {
            throw new ToolException(sprintf('path "%s" is not readable', $path));
        }

        return [
            'path' => $path,
            'bytes' => strlen($content),
            'sha256' => hash('sha256', $content),
            'lines' => self::_countLines($content),
            'modified' => self::_modified($absolute),
            'content' => $content,
        ];
    }

    /**
     * Replaces the file content completely. Symlinks stay intact because the resolved path is written.
     *
     * @return array{path: string, bytes: int, sha256: string, created: bool}
     * @throws ToolException
     */
    public function write(string $path, string $content, ?string $expectedSha256 = null): array
    {
        if (strlen($content) > self::MAX_BYTES) {
            throw new ToolException(sprintf('content has %d bytes; the maximum is %d bytes', strlen($content), self::MAX_BYTES));
        }
        if (!mb_check_encoding($content, 'UTF-8')) {
            throw new ToolException('content is not valid UTF-8');
        }

        $target = $this->resolveForWrite($path);
        $absolute = $target['absolute'];

        if ($expectedSha256 !== null) {
            if (!$target['exists']) {
                throw new ToolException(sprintf('expectedSha256 given but "%s" does not exist', $path));
            }
            $actual = @hash_file('sha256', $absolute);
            if ($actual === false || !hash_equals($actual, strtolower(trim($expectedSha256)))) {
                throw new ToolException(sprintf(
                    'file changed since read: expected sha256 %s, actual %s',
                    $expectedSha256,
                    $actual === false ? 'unknown' : $actual
                ));
            }
        }

        if (@file_put_contents($absolute, $content, LOCK_EX) === false) {
            throw new ToolException(sprintf('write failed for "%s" (permission denied?)', $path));
        }
        if (!$target['exists']) {
            @chmod($absolute, 0664);
        }

        return [
            'path' => $path,
            'bytes' => strlen($content),
            'sha256' => hash('sha256', $content),
            'created' => !$target['exists'],
        ];
    }

    /**
     * Syntax rules for the relative path. Messages never echo absolute paths.
     *
     * @throws ToolException
     */
    private function _validatePath(string $path): void
    {
        if ($path === '') {
            throw new ToolException('path must not be empty');
        }
        if (strlen($path) > self::MAX_PATH_LENGTH) {
            throw new ToolException(sprintf('path is longer than %d characters', self::MAX_PATH_LENGTH));
        }
        if (str_contains($path, "\0")) {
            throw new ToolException('path must not contain a null byte');
        }
        if (str_contains($path, '\\')) {
            throw new ToolException('path must not contain a backslash');
        }
        if (str_starts_with($path, '/')) {
            throw new ToolException('path must be relative to src/Templates and must not start with "/"');
        }

        $segments = explode('/', $path);
        foreach ($segments as $segment) {
            if ($segment === '' || $segment === '.' || $segment === '..') {
                throw new ToolException(sprintf('path "%s" is outside src/Templates', $path));
            }
            if (str_starts_with($segment, '.')) {
                throw new ToolException(sprintf('path "%s" contains a hidden segment', $path));
            }
        }
        if (count($segments) < 2) {
            throw new ToolException(sprintf(
                'path "%s" is outside src/Templates template sets (needs at least "<set>/<file>")',
                $path
            ));
        }

        self::_checkExtension(end($segments));
    }

    /**
     * @throws ToolException
     */
    private function _resolveExistingFull(string $path, string $full): string
    {
        $real = realpath($full);
        if ($real === false || !$this->_isBelowRoot($real, false)) {
            throw new ToolException(sprintf('path "%s" is outside src/Templates', $path));
        }
        if (!is_file($real)) {
            throw new ToolException(sprintf('path "%s" is not a regular file', $path));
        }
        self::_checkExtension(basename($real));

        return $real;
    }

    private function _isBelowRoot(string $real, bool $allowRootItself): bool
    {
        foreach (array_keys($this->getRoots()) as $root) {
            $root = (string) $root;
            if ($allowRootItself && $real === $root) {
                return true;
            }
            if (str_starts_with($real, rtrim($root, '/') . '/')) {
                return true;
            }
        }

        return false;
    }

    /**
     * @throws ToolException
     */
    private static function _checkExtension(string $fileName): void
    {
        $dot = strrpos($fileName, '.');
        $extension = $dot === false ? '' : strtolower(substr($fileName, $dot + 1));
        if ($dot === false || $extension === '') {
            throw new ToolException(sprintf(
                'file without extension not allowed (allowed: %s)',
                implode(', ', self::ALLOWED_EXTENSIONS)
            ));
        }
        if (!in_array($extension, self::ALLOWED_EXTENSIONS, true)) {
            throw new ToolException(sprintf(
                'extension ".%s" not allowed (allowed: %s)',
                $extension,
                implode(', ', self::ALLOWED_EXTENSIONS)
            ));
        }
    }

    /**
     * Level 1 directories (or directory symlinks) except the root kind dirs slip/ and email/.
     *
     * @return string[]
     */
    private function _listSets(): array
    {
        $sets = [];
        foreach (self::_scanDir($this->rootDir) as $name) {
            if (isset(self::KIND_DIRS[$name])) {
                continue;
            }
            if (is_dir($this->rootDir . '/' . $name)) {
                $sets[] = $name;
            }
        }

        return $sets;
    }

    /**
     * @param array<int, array> $templates
     */
    private function _walk(
        string $relDir,
        string $subPath,
        int $depth,
        ?string $set,
        string $kind,
        array &$templates,
        bool &$truncated
    ): void {
        if ($truncated || $depth > self::LIST_MAX_DEPTH) {
            return;
        }
        $absDir = $this->rootDir . '/' . $relDir;
        if (!is_dir($absDir)) {
            return;
        }
        $dirReal = realpath($absDir);
        if ($dirReal === false || !$this->_isBelowRoot($dirReal, true)) {
            return;
        }

        foreach (self::_scanDir($absDir) as $entry) {
            if ($truncated) {
                return;
            }
            $relPath = $relDir . '/' . $entry;
            $absPath = $absDir . '/' . $entry;
            $entrySub = $subPath === '' ? $entry : $subPath . '/' . $entry;

            if (is_dir($absPath)) {
                $this->_walk($relPath, $entrySub, $depth + 1, $set, $kind, $templates, $truncated);
                continue;
            }

            $real = realpath($absPath);
            if ($real === false || !is_file($real) || !$this->_isBelowRoot($real, false)) {
                continue;
            }
            try {
                self::_checkExtension($entry);
                self::_checkExtension(basename($real));
            } catch (ToolException) {
                continue;
            }

            if (count($templates) >= self::LIST_MAX_ENTRIES) {
                $truncated = true;

                return;
            }

            $bytes = @filesize($real);
            $templates[] = [
                'set' => $set,
                'kind' => $kind,
                'path' => $relPath,
                'name' => $set === null ? $entrySub : $set . '|' . $entrySub,
                'bytes' => $bytes === false ? 0 : $bytes,
                'modified' => self::_modified($real),
            ];
        }
    }

    /**
     * Sorted directory entries without dot entries.
     *
     * @return string[]
     */
    private static function _scanDir(string $dir): array
    {
        $entries = @scandir($dir);
        if ($entries === false) {
            return [];
        }

        return array_values(array_filter(
            $entries,
            static fn (string $entry): bool => $entry !== '' && $entry[0] !== '.'
        ));
    }

    /**
     * @param array<string, string> $roots
     */
    private static function _addRoot(array &$roots, string $link, string $prefix): void
    {
        $real = realpath($link);
        if ($real === false || !is_dir($real) || isset($roots[$real])) {
            return;
        }
        $roots[$real] = $prefix;
    }

    private static function _normalizeLexically(string $path): string
    {
        $parts = [];
        foreach (explode('/', $path) as $part) {
            if ($part === '' || $part === '.') {
                continue;
            }
            if ($part === '..') {
                array_pop($parts);
                continue;
            }
            $parts[] = $part;
        }

        return '/' . implode('/', $parts);
    }

    private static function _countLines(string $content): int
    {
        if ($content === '') {
            return 0;
        }

        return substr_count($content, "\n") + (str_ends_with($content, "\n") ? 0 : 1);
    }

    private static function _modified(string $absolute): string
    {
        $mtime = @filemtime($absolute);

        return date('c', $mtime === false ? 0 : $mtime);
    }
}
