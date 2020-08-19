<?php

namespace App\Service;

use Exception;
use Psr\Cache\InvalidArgumentException;

class SecurityService
{
    /** @var CacheService */
    private $_cacheService;

    /** @var ConfigService */
    private $_configService;

    /** @var HttpService */
    private $_httpService;

    /** @var JsonService */
    private $_jsonService;

    /**
     * @param CacheService $cacheService
     * @param ConfigService $configService
     * @param HttpService $httpService
     * @param JsonService $jsonService
     */
    public function __construct(
        CacheService $cacheService,
        ConfigService $configService,
        HttpService $httpService,
        JsonService $jsonService
    ) {
        $this->_cacheService = $cacheService;
        $this->_configService = $configService;
        $this->_httpService = $httpService;
        $this->_jsonService = $jsonService;
    }

    /**
     * @return string
     * @throws Exception
     * @throws InvalidArgumentException
     */
    public function getJwt(): string
    {
        $jwtCacheKey = $this->_cacheService->getJwtCacheKey();
        if ($this->_cacheService->has($jwtCacheKey)) {
            return $this->_cacheService->get($jwtCacheKey)->get();
        }

        $options = [
            'client' => $this->_configService->getClient(),
            'apiKey' => $this->_configService->getApiKey(),
        ];

        $response = $this->_httpService->post(
            $this->_configService->getAuthenticationUrl(),
            $options
        );

        $response = $this->_jsonService->parseJson($response);
        $jwt = $response['response']['jwt'];

        $this->_cacheService->set($jwtCacheKey, $jwt, 28800);

        return $jwt;
    }
}
