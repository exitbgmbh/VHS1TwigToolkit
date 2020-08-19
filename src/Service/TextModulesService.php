<?php

namespace App\Service;

use Exception;
use Psr\Cache\InvalidArgumentException;

class TextModulesService
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
     * @param ConfigService $_configService
     * @param HttpService $_httpService
     * @param JsonService $_jsonService
     */
    public function __construct(
        CacheService $cacheService,
        ConfigService $_configService,
        HttpService $_httpService,
        JsonService $_jsonService
    ) {
        $this->_cacheService = $cacheService;
        $this->_configService = $_configService;
        $this->_httpService = $_httpService;
        $this->_jsonService = $_jsonService;
    }

    /**
     * @param string $advertisingMediumCode
     * @param string $jwt
     * @param bool $forceReload
     * @return array
     * @throws Exception|InvalidArgumentException
     */
    public function getTextModules(string $advertisingMediumCode, string $jwt, bool $forceReload): array
    {
        $textModulesCacheKey = $this->_cacheService->getTextModulesCacheKey($advertisingMediumCode);
        if ($this->_cacheService->has($textModulesCacheKey) && !$forceReload) {
            $textModules = $this->_cacheService->get($textModulesCacheKey)->get();
        } else {
            $textModulesEndpointUrl = $this->_configService->getTemplateTextModulesEndpointUrl($advertisingMediumCode);
            $textModules = $this->_httpService->getTemplateTextModules($textModulesEndpointUrl, $jwt);
            $textModules = $this->_jsonService->parseJson($textModules);
            $textModules = $textModules['response'];

            $transformedTextModules = [];
            foreach ($textModules as $textModule) {
                $transformedTextModules[$textModule['key']] = $textModule['value'];
            }

            $textModules = $transformedTextModules;

            $this->_cacheService->set($textModulesCacheKey, $textModules);
        }

        $textModuleReplacements = $this->_configService->getTranslatedTextModules($advertisingMediumCode);

        return array_merge($textModules, $textModuleReplacements);
    }
}
