<?php

namespace App\Service;

use Exception;
use Psr\Cache\InvalidArgumentException;

class LanguageService
{
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

    /** @var VhsBuildService */
    private $_vhsBuildService;

    /**
     * @param CacheService $_cacheService
     * @param ConfigService $_configService
     * @param HttpService $_httpService
     * @param JsonService $_jsonService
     * @param SecurityService $_securityService
     * @param VhsBuildService $vhsBuildService
     */
    public function __construct(
        CacheService $_cacheService,
        ConfigService $_configService,
        HttpService $_httpService,
        JsonService $_jsonService,
        SecurityService $_securityService,
        VhsBuildService $vhsBuildService
    ) {
        $this->_cacheService = $_cacheService;
        $this->_configService = $_configService;
        $this->_httpService = $_httpService;
        $this->_jsonService = $_jsonService;
        $this->_securityService = $_securityService;
        $this->_vhsBuildService = $vhsBuildService;
    }

    /**
     * @param bool $forceReload
     * @return array
     * @throws Exception
     * @throws InvalidArgumentException
     */
    public function getLanguages(bool $forceReload): array
    {
        $languagesCacheKey = $this->_cacheService->getLanguagesCacheKey();
        if (!$forceReload && $this->_cacheService->has($languagesCacheKey)) {
            return $this->_cacheService->get($languagesCacheKey)->get();
        }

        $buildNumber = $this->_vhsBuildService->getBuildVersion($forceReload);
        if ($buildNumber <= VhsBuildService::VHS_MAX_BUILD_UNSUPPORTED_LANGUAGES_API) {
            $this->_cacheService->delete($languagesCacheKey);

            return [];
        }

        $languagesEndpointUrl = $this->_configService->getLanguagesEndpointUrl();
        $languages = $this->_httpService->getLanguages($languagesEndpointUrl, $this->_securityService->getJwt($forceReload));
        $languages = $this->_jsonService->parseJson($languages);
        $languages = $languages['response']['results'];

        $this->_cacheService->set($languagesCacheKey, $languages);

        return $languages;
    }
}
