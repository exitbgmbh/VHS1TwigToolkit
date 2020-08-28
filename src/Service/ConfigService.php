<?php

namespace App\Service;

use Exception;

class ConfigService
{
    /** @var JsonService */
    private $_jsonService;

    /**
     * @param JsonService $jsonService
     */
    public function __construct(JsonService $jsonService)
    {
        $this->_jsonService = $jsonService;
    }

    /**
     * @return array
     * @throws Exception
     */
    public function getConfig(): array
    {
        $configPath = __DIR__ . '/../Config/config.json';
        if (!file_exists($configPath)) {
            throw new Exception(sprintf('config "%s" does not exist', $configPath));
        }

        return $this->_jsonService->parseJson(file_get_contents($configPath));
    }

    /**
     * @return string
     * @throws Exception
     */
    public function getAuthenticationUrl(): string
    {
        return sprintf('%s/v1/login/authenticate', $this->getRestEndpoint());
    }

    /**
     * @return string
     * @throws Exception
     */
    public function getRestEndpoint(): string
    {
        return $this->_getByKey('url', '');
    }

    /**
     * @return string
     * @throws Exception
     */
    public function getClient(): string
    {
        return $this->_getByKey('client', '');
    }

    /**
     * @return string
     * @throws Exception
     */
    public function getApiKey(): string
    {
        return $this->_getByKey('apiKey', '');
    }

    /**
     * @return array
     * @throws Exception
     */
    public function getMappingContext(): array
    {
        $config = $this->getConfig();
        if (!array_key_exists('mapping', $config)
            && !array_key_exists('defaults', $config['mapping'])) {
            return [];
        }

        return $config['mapping']['defaults'];
    }

    /**
     * @param string $type
     * @param string $identifiers
     * @return string
     * @throws Exception
     */
    public function getDocumentContextEndpointUrl(string $type, string $identifiers): string
    {
        return sprintf(
            '%s/v1/document/readTemplateContext/%s?type=%s',
            $this->getRestEndpoint(),
            $identifiers,
            $type
        );
    }

    /**
     * @param string $type
     * @param string $identifier
     * @return string
     * @throws Exception
     */
    public function getEmailContextEndpointUrl(string $type, string $identifier): string
    {
        return sprintf(
            '%s/v1/email/readContext/%s?type=%s',
            $this->getRestEndpoint(),
            $identifier,
            $type
        );
    }

    /**
     * @param string $advertisingMediumCode
     * @return string
     * @throws Exception
     */
    public function getTemplateTextModulesEndpointUrl(string $advertisingMediumCode): string
    {
        return sprintf(
            '%s/v1/document/searchTemplateTextModules/%s',
            $this->getRestEndpoint(),
            $advertisingMediumCode
        );
    }

    /**
     * @param string $advertisingMediumCode
     * @return array
     * @throws Exception
     */
    public function getTranslatedTextModules(string $advertisingMediumCode): array
    {
        $textModules = [];

        $config = $this->getConfig();
        if ($this->_keyExists([ 'mapping', 'textModuleMapping', $advertisingMediumCode ], $config)) {
            $textModules = $config['mapping']['textModuleMapping'][$advertisingMediumCode];
        }

        if ($this->_keyExists([ 'mapping', 'textModuleMapping', 'defaults' ], $config)) {
            $textModules = array_merge($config['mapping']['textModuleMapping']['defaults'], $textModules);
        }

        return $textModules;
    }

    /**
     * @param array $path
     * @param array $search
     * @return bool
     */
    private function _keyExists(array $path, array $search): bool
    {
        $current = $search;
        foreach ($path as $key => $subPath) {
            if (!array_key_exists($subPath, $current)) {
                return false;
            }

            $current = $current[$subPath];
        }

        return true;
    }

    /**
     * @param string $key
     * @param $default
     * @return mixed
     * @throws Exception
     */
    private function _getByKey(string $key, $default)
    {
        $config = $this->getConfig();
        if (!array_key_exists($key, $config)) {
            throw new Exception(sprintf('"%s" in config not configured', $key));
        }

        return $config[$key] ?? $default;
    }
}
