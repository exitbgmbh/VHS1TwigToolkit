<?php

namespace App\Service;

use Exception;
use Psr\Cache\InvalidArgumentException;
use Symfony\Component\HttpFoundation\Response;

class VhsBuildService
{
    /** @var int */
    public const VHS_MAX_BUILD_UNSUPPORTED_TYPES_API = 1930;

    /** @var int */
    public const VHS_MAX_BUILD_UNSUPPORTED_LANGUAGES_API = 1931;

    /** @var CacheService */
    private $_cacheService;

    /** @var ConfigService */
    private $_configService;

    /** @var HttpService */
    private $_httpService;

    /** @var JsonService */
    private $_jsonService;

    /** @var SecurityService */
    private $_securityService;

    /**
     * @param CacheService $_cacheService
     * @param ConfigService $_configService
     * @param HttpService $_httpService
     * @param JsonService $_jsonService
     * @param SecurityService $_securityService
     */
    public function __construct(
        CacheService $_cacheService,
        ConfigService $_configService,
        HttpService $_httpService,
        JsonService $_jsonService,
        SecurityService $_securityService
    ) {
        $this->_cacheService = $_cacheService;
        $this->_configService = $_configService;
        $this->_httpService = $_httpService;
        $this->_jsonService = $_jsonService;
        $this->_securityService = $_securityService;
    }

    /**
     * @param bool $forceReload
     * @return int
     * @throws Exception
     * @throws InvalidArgumentException
     */
    public function getBuildVersion(bool $forceReload): int
    {
        $url = $this->_configService->getRestEndpoint();
        $vhsBuildNumberCacheKey = $this->_cacheService->getVhsBuildNumberCacheKey($url);
        if (!$forceReload && $this->_cacheService->has($vhsBuildNumberCacheKey)) {
            return $this->_cacheService->get($vhsBuildNumberCacheKey)->get();
        }

        $releaseInfosEndpointUrl = $this->_configService->getVhsReleaseVersionEndpointUrl();
        $jwt = $this->_securityService->getJwt($forceReload);
        $response = $this->_httpService->get($releaseInfosEndpointUrl, $jwt);
        $response = $this->_jsonService->parseJson($response);

        if ($response['httpCode'] !== Response::HTTP_OK) {
            $buildNumber = self::VHS_MAX_BUILD_UNSUPPORTED_TYPES_API;
        } else {
            $buildNumber = (int)$response['response']['build'];
        }

        $this->_cacheService->set($vhsBuildNumberCacheKey, $buildNumber);

        return $buildNumber;
    }
}
