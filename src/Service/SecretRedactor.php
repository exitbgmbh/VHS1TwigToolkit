<?php

namespace App\Service;

/**
 * Removes the credentials of the active config (url, host, client, apiKey) and JWTs from texts
 * such as error messages and warnings. Never throws.
 */
class SecretRedactor
{
    private const MIN_LENGTH = 3;

    private const JWT_PATTERN = '/eyJ[A-Za-z0-9_-]+\.[A-Za-z0-9_-]+\.[A-Za-z0-9_-]*/';

    private const BEARER_PATTERN = '/Bearer\s+\S+/i';

    private const JWT_PLACEHOLDER = '[redacted:jwt]';

    /** @var ConfigService */
    private $_configService;

    /** @var CacheService */
    private $_cacheService;

    public function __construct(ConfigService $configService, CacheService $cacheService)
    {
        $this->_configService = $configService;
        $this->_cacheService = $cacheService;
    }

    public function redact(string $text): string
    {
        $replacements = $this->_collectReplacements();
        if (!empty($replacements)) {
            // strtr replaces longer keys first and never re-replaces inserted placeholders
            $text = strtr($text, $replacements);
        }

        $text = (string) preg_replace(self::JWT_PATTERN, self::JWT_PLACEHOLDER, $text);

        return (string) preg_replace(self::BEARER_PATTERN, self::JWT_PLACEHOLDER, $text);
    }

    /**
     * @return array<string, string> secret value => placeholder
     */
    private function _collectReplacements(): array
    {
        $replacements = [];

        try {
            $url = $this->_configService->getRestEndpoint();
            $this->_add($replacements, $url, '[redacted:url]');

            $host = parse_url($url, PHP_URL_HOST);
            if (is_string($host)) {
                $this->_add($replacements, $host, '[redacted:url]');
            }

            $withoutScheme = preg_replace('#^[A-Za-z][A-Za-z0-9+.-]*://#', '', $url);
            if (is_string($withoutScheme)) {
                $this->_add($replacements, $withoutScheme, '[redacted:url]');
            }
        } catch (\Throwable $e) {
            // config not readable: generic patterns still apply
        }

        try {
            $client = $this->_configService->getClient();
            // a client equal to the config slug is public anyway; redacting it would hide the slug itself
            if ($client !== $this->_configService->getConfigName()) {
                $this->_add($replacements, $client, '[redacted:client]');
            }
        } catch (\Throwable $e) {
        }

        try {
            $this->_add($replacements, $this->_configService->getApiKey(), '[redacted:apiKey]');
        } catch (\Throwable $e) {
        }

        try {
            $jwtCacheKey = $this->_cacheService->getJwtCacheKey();
            if ($this->_cacheService->has($jwtCacheKey)) {
                $jwt = $this->_cacheService->get($jwtCacheKey)->get();
                if (is_string($jwt)) {
                    $this->_add($replacements, $jwt, self::JWT_PLACEHOLDER);
                }
            }
        } catch (\Throwable $e) {
        }

        return $replacements;
    }

    /**
     * @param array<string, string> $replacements
     */
    private function _add(array &$replacements, $value, string $placeholder): void
    {
        if (!is_string($value) || strlen($value) < self::MIN_LENGTH) {
            return;
        }

        if (!array_key_exists($value, $replacements)) {
            $replacements[$value] = $placeholder;
        }
    }
}
