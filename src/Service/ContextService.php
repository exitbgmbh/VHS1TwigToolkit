<?php

namespace App\Service;

use Exception;
use Psr\Cache\InvalidArgumentException;

class ContextService
{
    /** @var CacheService */
    private $_cacheService;

    /** @var ConfigService */
    private $_configService;

    /** @var JsonService */
    private $_jsonService;

    /** @var HttpService */
    private $_httpService;

    /** @var MapperService */
    private $_mapperService;

    /**
     * @param CacheService $cacheService
     * @param ConfigService $configService
     * @param HttpService $httpService
     * @param JsonService $jsonService
     * @param MapperService $mapperService
     */
    public function __construct(
        CacheService $cacheService,
        ConfigService $configService,
        HttpService $httpService,
        JsonService $jsonService,
        MapperService $mapperService
    ) {
        $this->_cacheService = $cacheService;
        $this->_configService = $configService;
        $this->_httpService = $httpService;
        $this->_jsonService = $jsonService;
        $this->_mapperService = $mapperService;
    }

    /**
     * @param string $type
     * @param string $identifiers
     * @param string $jwt
     * @param bool $forceReload
     * @return array
     * @throws Exception|InvalidArgumentException
     */
    public function getContext(string $type, string $identifiers, string $jwt, bool $forceReload): array
    {
        $contextCacheKey = $this->_cacheService->getContextCacheKey($type, $identifiers);
        if ($this->_cacheService->has($contextCacheKey) && !$forceReload) {
            $context = $this->_cacheService->get($contextCacheKey)->get();
        } else {
            $contextEndpointUrl = $this->_configService->getDocumentContextEndpointUrl($type, $identifiers);
            $context = $this->_httpService->getContext($contextEndpointUrl, $jwt);
            $context = $this->_jsonService->parseJson($context);
            $context = $context['response'];

            $this->_cacheService->set($contextCacheKey, $context);
        }

        return $this->_mapperService->map($context, $this->_configService->getMappingContext());
    }

    /**
     * @param string $type
     * @param string $identifier
     * @param string $jwt
     * @param bool $forceReload
     * @return array
     * @throws Exception|InvalidArgumentException
     */
    public function getEmailContext(string $type, string $identifier, string $jwt, bool $forceReload): array
    {
        $emailContextCacheKey = $this->_cacheService->getEmailContextCacheKey($type, $identifier);
        if ($this->_cacheService->has($emailContextCacheKey) && !$forceReload) {
            $context = $this->_cacheService->get($emailContextCacheKey)->get();
        } else {
            $emailContextEndpointUrl = $this->_configService->getEmailContextEndpointUrl($type, $identifier);
            $context = $this->_httpService->getEmailContext($emailContextEndpointUrl, $jwt);
            $context = $this->_jsonService->parseJson($context);
            $context = $context['response'];

            $this->_cacheService->set($emailContextCacheKey, $context);
        }

        return $this->_mapperService->map($context, $this->_configService->getMappingContext());
    }
}
