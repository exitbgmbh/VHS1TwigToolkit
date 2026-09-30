<?php

namespace App\Service;

use App\Mcp\Tool\ToolException;

/**
 * Reads and patches mapping.defaults / mapping.textModuleMapping of a config by slug.
 * Everything outside mapping.<section> stays byte-for-byte equal in value (credentials included).
 * Error messages name the slug only, never a path or a config value.
 */
class MappingStore
{
    public const SECTIONS = ['defaults', 'textModuleMapping'];
    public const MAX_PATCH_BYTES = 100000;
    public const MAX_FILE_BYTES = 1048576;
    public const MAX_BACKUPS_PER_SLUG = 20;

    private const HASH_FLAGS = JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE;
    private const WRITE_FLAGS = JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR;
    private const REDACTED_MARKER = '[redacted:';

    private ConfigRegistry $registry;

    private string $backupDir;

    public function __construct(ConfigRegistry $registry, ?string $backupDir = null)
    {
        $this->registry = $registry;
        $this->backupDir = rtrim($backupDir ?? $registry->getConfigDir() . '/.mcp-backups', '/');
    }

    /**
     * @return array{sha256: string, defaults: object, textModuleMapping: object}
     * @throws ToolException
     */
    public function read(string $slug): array
    {
        [, $root] = $this->_load($slug);
        [$defaults, $textModuleMapping] = self::_sections($root);

        return [
            'sha256' => self::_hash($defaults, $textModuleMapping),
            'defaults' => $defaults,
            'textModuleMapping' => $textModuleMapping,
        ];
    }

    /**
     * Applies a JSON Merge Patch (RFC 7396) to mapping.<section>.
     *
     * @return array{changed: bool, sha256Before: string, sha256: string, backup: bool, section: object}
     * @throws ToolException
     */
    public function patch(string $slug, string $section, array $patch, ?string $expectedSha256 = null): array
    {
        if (!in_array($section, self::SECTIONS, true)) {
            throw new ToolException(sprintf(
                'unknown section "%s"; allowed: %s',
                $section,
                implode(', ', self::SECTIONS)
            ));
        }
        if (!self::_isObjectArray($patch)) {
            throw new ToolException('patch must be a JSON object');
        }
        $patchJson = json_encode($patch, self::HASH_FLAGS);
        if ($patchJson === false) {
            throw new ToolException('patch must be a JSON object');
        }
        if (strlen($patchJson) > self::MAX_PATCH_BYTES) {
            throw new ToolException(sprintf('patch is too large (max %d bytes)', self::MAX_PATCH_BYTES));
        }
        if (self::_containsRedacted($patch)) {
            throw new ToolException('patch contains a redacted placeholder; do not write back redacted values');
        }

        [$raw, $root] = $this->_load($slug);
        [$defaults, $textModuleMapping] = self::_sections($root);
        $sha256Before = self::_hash($defaults, $textModuleMapping);

        if ($expectedSha256 !== null && $expectedSha256 !== $sha256Before) {
            throw new ToolException(sprintf(
                'mapping changed since read: expected sha256 %s, actual %s',
                $expectedSha256,
                $sha256Before
            ));
        }

        try {
            $current = $section === 'defaults' ? $defaults : $textModuleMapping;
            $merged = self::_mergePatch(clone $current, $patch);
        } catch (ToolException $e) {
            throw $e;
        } catch (\Throwable) {
            throw new ToolException('internal check failed, nothing written');
        }

        if ($section === 'textModuleMapping') {
            foreach (array_keys($patch) as $group) {
                $group = (string) $group;
                if (!property_exists($merged, $group)) {
                    continue;
                }
                if (!self::_isObjectOfStrings($merged->{$group})) {
                    throw new ToolException(sprintf(
                        'textModuleMapping group "%s" must be an object of strings',
                        $group
                    ));
                }
            }
        }

        $newDefaults = $section === 'defaults' ? $merged : $defaults;
        $newTextModuleMapping = $section === 'textModuleMapping' ? $merged : $textModuleMapping;
        $sha256 = self::_hash($newDefaults, $newTextModuleMapping);

        if ($sha256 === $sha256Before) {
            return [
                'changed' => false,
                'sha256Before' => $sha256Before,
                'sha256' => $sha256,
                'backup' => false,
                'section' => $current,
            ];
        }

        $json = $this->_buildFile($slug, $raw, $root, $section, $merged);
        $target = $this->_resolveTarget($slug);
        $this->_backup($slug, $raw);
        $this->_write($slug, $target, $json);

        return [
            'changed' => true,
            'sha256Before' => $sha256Before,
            'sha256' => $sha256,
            'backup' => true,
            'section' => $merged,
        ];
    }

    /**
     * @return array{0: string, 1: \stdClass} raw bytes and object-faithful decoded root
     * @throws ToolException
     */
    private function _load(string $slug): array
    {
        if (!$this->registry->exists($slug)) {
            throw new ToolException(sprintf('Unknown config "%s"', $slug));
        }

        try {
            $raw = @file_get_contents($this->registry->getConfigPath($slug));
        } catch (\Throwable) {
            $raw = false;
        }
        if (!is_string($raw)) {
            throw new ToolException(sprintf('config "%s" could not be read', $slug));
        }

        try {
            $root = json_decode($raw, false, 512, JSON_THROW_ON_ERROR);
        } catch (\Throwable) {
            $root = null;
        }
        if (!$root instanceof \stdClass) {
            throw new ToolException(sprintf('config "%s" is not valid JSON', $slug));
        }

        return [$raw, $root];
    }

    /**
     * @return array{0: \stdClass, 1: \stdClass}
     */
    private static function _sections(\stdClass $root): array
    {
        $mapping = $root->mapping ?? null;
        $sections = [];
        foreach (self::SECTIONS as $section) {
            $value = $mapping instanceof \stdClass ? ($mapping->{$section} ?? null) : null;
            $sections[] = $value instanceof \stdClass ? $value : new \stdClass();
        }

        return $sections;
    }

    private static function _hash(\stdClass $defaults, \stdClass $textModuleMapping): string
    {
        $json = json_encode(['defaults' => $defaults, 'textModuleMapping' => $textModuleMapping], self::HASH_FLAGS);

        return hash('sha256', (string) $json);
    }

    /**
     * PHP array that stands for a JSON object: empty or not a list.
     */
    private static function _isObjectArray(mixed $value): bool
    {
        return is_array($value) && ($value === [] || !array_is_list($value));
    }

    private static function _containsRedacted(array $data): bool
    {
        foreach ($data as $key => $value) {
            if (str_contains((string) $key, self::REDACTED_MARKER)) {
                return true;
            }
            if (is_string($value) && str_contains($value, self::REDACTED_MARKER)) {
                return true;
            }
            if (is_array($value) && self::_containsRedacted($value)) {
                return true;
            }
        }

        return false;
    }

    private static function _mergePatch(mixed $target, array $patch): \stdClass
    {
        if (!$target instanceof \stdClass) {
            $target = new \stdClass();
        }

        foreach ($patch as $key => $value) {
            $key = (string) $key;
            if ($value === null) {
                unset($target->{$key});
                continue;
            }
            if (self::_isObjectArray($value)) {
                $existing = property_exists($target, $key) ? $target->{$key} : null;
                $target->{$key} = self::_mergePatch(
                    $existing instanceof \stdClass ? clone $existing : null,
                    $value
                );
                continue;
            }
            $target->{$key} = self::_toJsonValue($value);
        }

        return $target;
    }

    /**
     * Replacement value: lists stay JSON arrays, associative arrays become objects.
     */
    private static function _toJsonValue(mixed $value): mixed
    {
        if (!is_array($value)) {
            return $value;
        }

        if (array_is_list($value)) {
            return array_map([self::class, '_toJsonValue'], $value);
        }

        $object = new \stdClass();
        foreach ($value as $key => $item) {
            $object->{(string) $key} = self::_toJsonValue($item);
        }

        return $object;
    }

    private static function _isObjectOfStrings(mixed $value): bool
    {
        if (!$value instanceof \stdClass) {
            return false;
        }
        foreach (get_object_vars($value) as $item) {
            if (!is_string($item)) {
                return false;
            }
        }

        return true;
    }

    /**
     * Builds the new file content and verifies that nothing outside mapping.<section> changed.
     *
     * @throws ToolException
     */
    private function _buildFile(string $slug, string $raw, \stdClass $root, string $section, \stdClass $merged): string
    {
        try {
            $before = json_decode($raw, true, 512, JSON_THROW_ON_ERROR);

            $newRoot = clone $root;
            $mapping = ($root->mapping ?? null) instanceof \stdClass ? clone $root->mapping : new \stdClass();
            $mapping->{$section} = $merged;
            $newRoot->mapping = $mapping;

            $json = json_encode($newRoot, self::WRITE_FLAGS);
            $json = self::_reindent($json, self::_indentUnit($raw));
            if (str_ends_with($raw, "\n")) {
                $json .= "\n";
            }

            $after = json_decode($json, true, 512, JSON_THROW_ON_ERROR);
        } catch (\Throwable) {
            throw new ToolException('internal check failed, nothing written');
        }

        if (!is_array($before) || !is_array($after) || !self::_isUntouched($before, $after, $section)) {
            throw new ToolException('internal check failed, nothing written');
        }

        if (strlen($json) > self::MAX_FILE_BYTES) {
            throw new ToolException(sprintf(
                'config "%s" would exceed %d bytes, nothing written',
                $slug,
                self::MAX_FILE_BYTES
            ));
        }

        return $json;
    }

    private static function _isUntouched(array $before, array $after, string $section): bool
    {
        foreach (array_unique(array_merge(array_keys($before), array_keys($after))) as $key) {
            if ($key === 'mapping') {
                continue;
            }
            if (!array_key_exists($key, $before) || !array_key_exists($key, $after)
                || $before[$key] !== $after[$key]
            ) {
                return false;
            }
        }

        $mappingBefore = is_array($before['mapping'] ?? null) ? $before['mapping'] : [];
        $mappingAfter = is_array($after['mapping'] ?? null) ? $after['mapping'] : [];
        if (array_key_exists('mapping', $before) && !is_array($before['mapping'])) {
            return false;
        }
        foreach (array_unique(array_merge(array_keys($mappingBefore), array_keys($mappingAfter))) as $key) {
            if ($key === $section) {
                continue;
            }
            if (!array_key_exists($key, $mappingBefore) || !array_key_exists($key, $mappingAfter)
                || $mappingBefore[$key] !== $mappingAfter[$key]
            ) {
                return false;
            }
        }

        return true;
    }

    /**
     * Leading whitespace of the first indented line, default two spaces.
     */
    private static function _indentUnit(string $raw): string
    {
        if (preg_match('/^([ \t]+)\S/m', $raw, $match)) {
            return $match[1];
        }

        return '  ';
    }

    private static function _reindent(string $json, string $unit): string
    {
        if ($unit === '    ') {
            return $json;
        }

        $result = preg_replace_callback(
            '/^((?:    )+)/m',
            static fn(array $m): string => str_repeat($unit, intdiv(strlen($m[1]), 4)),
            $json
        );
        if (!is_string($result)) {
            throw new \RuntimeException('reindent failed');
        }

        return $result;
    }

    /**
     * Real file behind the slug; must be a regular file directly in the config dir (symlinks resolved).
     *
     * @throws ToolException
     */
    private function _resolveTarget(string $slug): string
    {
        try {
            $target = realpath($this->registry->getConfigPath($slug));
            $configDir = realpath($this->registry->getConfigDir());
        } catch (\Throwable) {
            $target = false;
            $configDir = false;
        }

        if (!is_string($target) || !is_string($configDir) || !is_file($target) || is_link($target)
            || dirname($target) !== $configDir
        ) {
            throw new ToolException(sprintf('config "%s" is not writable here', $slug));
        }

        return $target;
    }

    /**
     * @throws ToolException
     */
    private function _backup(string $slug, string $raw): void
    {
        $failed = new ToolException(sprintf('backup failed for config "%s", nothing written', $slug));

        try {
            if (!is_dir($this->backupDir) && !@mkdir($this->backupDir, 0700, true) && !is_dir($this->backupDir)) {
                throw $failed;
            }
            @chmod($this->backupDir, 0700);

            $file = sprintf(
                '%s/%s-%s-%s.json',
                $this->backupDir,
                $slug,
                date('Ymd-His'),
                bin2hex(random_bytes(2))
            );
            $handle = @fopen($file, 'x');
            if ($handle === false) {
                throw $failed;
            }
            @chmod($file, 0600);
            $written = @fwrite($handle, $raw);
            @fclose($handle);
            if ($written !== strlen($raw)) {
                @unlink($file);
                throw $failed;
            }
        } catch (\Throwable) {
            throw $failed;
        }

        $this->_pruneBackups($slug);
    }

    private function _pruneBackups(string $slug): void
    {
        try {
            $pattern = '/^' . preg_quote($slug, '/') . '-\d{8}-\d{6}-[0-9a-f]{4}\.json$/';
            $files = [];
            foreach (@scandir($this->backupDir) ?: [] as $name) {
                if (preg_match($pattern, $name)) {
                    $files[] = $name;
                }
            }
            sort($files, SORT_STRING);

            foreach (array_slice($files, 0, max(0, count($files) - self::MAX_BACKUPS_PER_SLUG)) as $name) {
                @unlink($this->backupDir . '/' . $name);
            }
        } catch (\Throwable) {
            // pruning is best effort; the write itself is already safe
        }
    }

    /**
     * @throws ToolException
     */
    private function _write(string $slug, string $target, string $json): void
    {
        try {
            $result = @file_put_contents($target, $json, LOCK_EX);
        } catch (\Throwable) {
            $result = false;
        }

        if ($result === false) {
            throw new ToolException(sprintf('write failed for config "%s" (permission denied?)', $slug));
        }
    }
}
