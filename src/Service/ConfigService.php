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
    public function getContextUrl(string $type, string $identifiers): string
    {
        $config = $this->getConfig();

        return sprintf(
            '%s/pdf-document.php?type=%s&identifiers=%s&client=%s&user=%s&password=%s&showContext=true',
            $config['url'],
            $type,
            $identifiers,
            $config['client'],
            $config['user'],
            $config['password']
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
}
