<?php

namespace App\Service;

use Exception;

class ConfigService
{
    /** @var string */
    private $_configName;

    /** @var array|null */
    private $_availableConfigs = null;

    /** @var JsonService */
    private $_jsonService;

    /**
     * @param JsonService $jsonService
     * @param string $configName
     */
    public function __construct(JsonService $jsonService, string $configName = '')
    {
        $this->_jsonService = $jsonService;
        $this->_configName = $configName;
    }

    /**
     * @return string
     */
    public function getConfigName(): string
    {
        return $this->_configName;
    }

    /**
     * Returns sorted list of config slugs found in src/Config/config.*.json
     * e.g. ['fcm', 'hsn', 'stm']
     *
     * @return array
     */
    public function getAvailableConfigs(): array
    {
        if ($this->_availableConfigs !== null) {
            return $this->_availableConfigs;
        }

        $pattern = __DIR__ . '/../Config/config.*.json';
        $files = glob($pattern);
        if (empty($files)) {
            $this->_availableConfigs = [];
            return $this->_availableConfigs;
        }

        $slugs = [];
        foreach ($files as $file) {
            $basename = basename($file, '.json'); // e.g. "config.stm"
            $slug = substr($basename, strlen('config.')); // e.g. "stm"
            $slugs[] = $slug;
        }

        $excluded = ['local'];
        $slugs = array_filter($slugs, static function (string $s) use ($excluded): bool {
            return !in_array($s, $excluded, true);
        });
        $slugs = array_values($slugs); // re-index after filter
        sort($slugs);

        $this->_availableConfigs = $slugs;
        return $this->_availableConfigs;
    }

    /**
     * @return array
     * @throws Exception
     */
    public function getConfig(): array
    {
        $filename = !empty($this->_configName)
            ? sprintf('config.%s.json', $this->_configName)
            : 'config.json';

        $configPath = __DIR__ . '/../Config/' . $filename;
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
     * @param string $kind
     * @param string $type
     * @param string $identifiers
     * @param string $additionalIdentifier
     * @return string
     * @throws Exception
     */
    public function getContextEndpointUrl(string $kind, string $type, string $identifiers, string $additionalIdentifier): string
    {
        $endpoint = '%s/v1/document/readTemplateContext/%s?type=%s';
        if ($kind === TypesService::TEMPLATE_TYPE_EMAIL_NAME) {
            $endpoint = '%s/v1/email/readContext/%s?type=%s';
        }

        if (!empty($additionalIdentifier)) {
            $endpoint .= '&additionalIdentifier=' . $additionalIdentifier;
        }

        return sprintf(
            $endpoint,
            $this->getRestEndpoint(),
            $identifiers,
            $type
        );
    }

    /**
     * @param string $advertisingMediumCode
     * @param string|null $templateName
     * @param string|null $language
     * @return string
     * @throws Exception
     */
    public function getTemplateTextModulesEndpointUrl(
        string $advertisingMediumCode,
        string $templateName = null,
        string $language = null
    ): string {
        $url = sprintf(
            '%s/v1/masterData/searchTemplateTextModules/%s',
            $this->getRestEndpoint(),
            $advertisingMediumCode
        );

        $query = [];
        if (!empty($templateName)) {
            $query['template'] = $templateName;
        }

        if (!empty($language)) {
            $query['language'] = $language;
        }

        if (!empty($query)) {
            $url .= '?' . http_build_query($query);
        }

        return $url;
    }

    /**
     * @return string
     * @throws Exception
     */
    public function getVhsReleaseVersionEndpointUrl(): string
    {
        return sprintf(
            '%s/v1/system/readReleaseVersion',
            $this->getRestEndpoint()
        );
    }

    /**
     * @return string
     * @throws Exception
     */
    public function getTypesEndpointUrl(): string
    {
        return sprintf(
            '%s/v1/masterData/searchTemplateCategories',
            $this->getRestEndpoint()
        );
    }

    /**
     * @return string
     * @throws Exception
     */
    public function getLanguagesEndpointUrl(): string
    {
        return sprintf(
            '%s/generic/generic/search/Language',
            $this->getRestEndpoint()
        );
    }

    /**
     * @param string $advertisingMediumCode
     * @param string $language
     * @return array
     * @throws Exception
     */
    public function getTranslatedTextModules(string $advertisingMediumCode, string $language): array
    {
        $textModules = [];

        $config = $this->getConfig();
        if (!empty($language)) {
            if ($this->_keyExists([ 'mapping', 'textModuleMapping', $advertisingMediumCode . '-' . $language ], $config)) {
                $textModules = $config['mapping']['textModuleMapping'][$advertisingMediumCode . '-' . $language];
            }
        } elseif ($this->_keyExists([ 'mapping', 'textModuleMapping', $advertisingMediumCode ], $config)) {
            $textModules = $config['mapping']['textModuleMapping'][$advertisingMediumCode];
        }

        if ($this->_keyExists([ 'mapping', 'textModuleMapping', 'defaults-' . $language ], $config)) {
            $textModules = array_merge($config['mapping']['textModuleMapping']['defaults-' . $language], $textModules);
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
