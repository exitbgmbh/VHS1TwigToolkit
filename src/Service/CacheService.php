<?php

namespace App\Service;

use Psr\Cache\CacheItemInterface;
use Symfony\Component\Cache\Adapter\FilesystemAdapter;
use Psr\Cache\InvalidArgumentException;

class CacheService
{
    /** @var string */
    private const CONTEXT_CACHE_KEY = 'context';

    /** @var string */
    private const JWT_CACHE_KEY = 'jwt';

    /** @var string */
    private const TEXT_MODULES_CACHE_KEY = 'text_modules';

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
     * @param string $type
     * @param string $identifiers
     * @return string
     */
    public function getContextCacheKey(string $type, string $identifiers): string
    {
        return sprintf('%s-%s-%s', self::CONTEXT_CACHE_KEY, $type, $identifiers);
    }

    /**
     * @param string $advertisingMediumCode
     * @return string
     */
    public function getTextModulesCacheKey(string $advertisingMediumCode): string
    {
        if (empty($advertisingMediumCode)) {
            $advertisingMediumCode = 'default';
        }

        return sprintf('%s-%s', self::TEXT_MODULES_CACHE_KEY, $advertisingMediumCode);
    }

    /**
     * @return string
     */
    public function getJwtCacheKey(): string
    {
        return self::JWT_CACHE_KEY;
    }
}
