<?php

namespace App\Service;

use Psr\Cache\CacheItemInterface;
use Symfony\Component\Cache\Adapter\FilesystemAdapter;
use Psr\Cache\InvalidArgumentException;

class CacheService
{
    /** @var string */
    private const JWT_CACHE_KEY = 'jwt';

    /** @var string */
    private const LANGUAGES_CACHE_KEY = 'languages';

    /** @var string */
    private const TEXT_MODULES_CACHE_KEY = 'text-modules';

    /** @var string */
    private const TYPES_CACHE_KEY = 'types';

    /** @var string */
    private const VHS_BUILD_NUMBER_CACHE_KEY = 'vhs-build-number';

    /** @var FilesystemAdapter */
    private $_cacheAdapter;

    /**
     * @param FilesystemAdapter $cacheAdapter
     */
    public function __construct(FilesystemAdapter $cacheAdapter)
    {
        $this->_cacheAdapter = $cacheAdapter;
    }

    /**
     * @param string $key
     * @param mixed $value
     * @param int $expiresAfterInSeconds
     * @throws InvalidArgumentException
     */
    public function set(string $key, $value, int $expiresAfterInSeconds = 0)
    {
        $item = $this->get($key);
        $item->set($value);

        if ($expiresAfterInSeconds > 0) {
            $item->expiresAfter($expiresAfterInSeconds);
        }

        $this->_cacheAdapter->save($item);
    }

    /**
     * @param string $key
     * @return CacheItemInterface
     * @throws InvalidArgumentException
     */
    public function get(string $key): CacheItemInterface
    {
        return $this->_cacheAdapter->getItem($key);
    }

    /**
     * @param string $key
     * @return bool
     */
    public function has(string $key): bool
    {
        try {
            $item = $this->get($key);
            if (empty($item)) {
                return false;
            }
        } catch (InvalidArgumentException $exception) {
            return false;
        }

        return $item->isHit();
    }

    /**
     * @param string $key
     * @throws InvalidArgumentException
     */
    public function delete(string $key)
    {
        $this->_cacheAdapter->deleteItem($key);
    }

    /**
     * @param string $kind
     * @param string $type
     * @param string $identifiers
     * @return string
     */
    public function getContextCacheKey(string $kind, string $type, string $identifiers): string
    {
        return sprintf('%s-%s-%s', $kind, $type, $identifiers);
    }

    /**
     * @param string $advertisingMediumCode
     * @param string $language
     * @return string
     */
    public function getTextModulesCacheKey(string $advertisingMediumCode, string $language): string
    {
        if (empty($advertisingMediumCode)) {
            $advertisingMediumCode = 'default';
        }

        if (empty($language)) {
            $language = 'default';
        }

        return sprintf('%s-%s-%s', self::TEXT_MODULES_CACHE_KEY, $advertisingMediumCode, $language);
    }

    /**
     * @return string
     */
    public function getJwtCacheKey(): string
    {
        return self::JWT_CACHE_KEY;
    }

    /**
     * @param string $url
     * @return string
     */
    public function getVhsBuildNumberCacheKey(string $url): string
    {
        return sprintf('%s-%s', self::VHS_BUILD_NUMBER_CACHE_KEY, $this->_removeReservedCharacters($url));
    }

    /**
     * @param string $url
     * @return string
     */
    public function getTypesCacheKey(string $url): string
    {
        return sprintf(
            '%s-%s',
            $this->_removeReservedCharacters($url),
            self::TYPES_CACHE_KEY
        );
    }

    /**
     * @return string
     */
    public function getLanguagesCacheKey(): string
    {
        return self::LANGUAGES_CACHE_KEY;
    }

    /**
     * @param string $key
     * @return string
     */
    private function _removeReservedCharacters(string $key): string
    {
        return str_replace([ '"', '{', '}', '/', '\\', '@', ':' ], '', $key);
    }
}
