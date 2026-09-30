<?php

namespace App\Service;

use App\Exception\UnknownConfigException;

/**
 * Lists, validates and resolves configs by slug only.
 * Never reads config file contents.
 */
class ConfigRegistry
{
    public const DEFAULT_SLUG = 'default';

    private const SLUG_PATTERN = '/^[a-z0-9_-]+$/';
    private const EXCLUDED_SLUGS = ['local', self::DEFAULT_SLUG];

    private string $configDir;

    public function __construct(?string $configDir = null)
    {
        $this->configDir = rtrim($configDir ?? __DIR__ . '/../Config', '/');
    }

    /**
     * Directory of the config files, for server-side use only.
     * Never put this path into tool results or error messages.
     */
    public function getConfigDir(): string
    {
        return $this->configDir;
    }

    /**
     * @return string[] slugs, 'default' first (if config.json exists), then alphabetically
     */
    public function listSlugs(): array
    {
        $slugs = [];
        foreach (glob($this->configDir . '/config.*.json') ?: [] as $file) {
            $slug = substr(basename($file), strlen('config.'), -strlen('.json'));
            if (preg_match(self::SLUG_PATTERN, $slug) && !in_array($slug, self::EXCLUDED_SLUGS, true)) {
                $slugs[] = $slug;
            }
        }
        sort($slugs, SORT_STRING);

        if (is_file($this->configDir . '/config.json')) {
            array_unshift($slugs, self::DEFAULT_SLUG);
        }

        return $slugs;
    }

    public function exists(string $slug): bool
    {
        if ($slug === self::DEFAULT_SLUG) {
            return is_file($this->configDir . '/config.json');
        }

        if (!preg_match(self::SLUG_PATTERN, $slug) || in_array($slug, self::EXCLUDED_SLUGS, true)) {
            return false;
        }

        return is_file($this->configDir . '/config.' . $slug . '.json');
    }

    /**
     * Resolves a slug to the internal config name ('' for config.json).
     *
     * @throws UnknownConfigException
     */
    public function toConfigName(string $slug): string
    {
        $this->assertExists($slug);

        return $slug === self::DEFAULT_SLUG ? '' : $slug;
    }

    /**
     * Absolute path of the config file, for server-side write access only.
     * Never put this path into tool results or error messages.
     *
     * @throws UnknownConfigException
     */
    public function getConfigPath(string $slug): string
    {
        $configName = $this->toConfigName($slug);
        $fileName = $configName === '' ? 'config.json' : 'config.' . $configName . '.json';

        return $this->configDir . '/' . $fileName;
    }

    /**
     * @throws UnknownConfigException
     */
    private function assertExists(string $slug): void
    {
        if (!$this->exists($slug)) {
            throw new UnknownConfigException(sprintf('Unknown config "%s"', $slug));
        }
    }
}
