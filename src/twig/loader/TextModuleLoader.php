<?php

use Twig\Loader\LoaderInterface as TwigLoaderInterface;
use Twig\Source as TwigSource;

class TextModuleLoader implements TwigLoaderInterface
{
    /** @var ConfigService */
    private $_configService;

    /** @var array */
    private $mapping = [];

    /**
     * @param string $advertisingMediumCode
     * @throws Exception
     */
    public function __construct(string $advertisingMediumCode)
    {
        $this->_configService = new ConfigService();

        $this->mapping = $this->_configService->getTranslatedTextModules($advertisingMediumCode);
    }

    /**
     * @param string $name
     * @return TwigSource
     */
    public function getSourceContext(string $name): TwigSource
    {
        if (array_key_exists($name, $this->mapping)) {
            if (empty($this->mapping[$name])) {
                $this->mapping[$name] = $name;
            }

            return new TwigSource($this->mapping[$name], $name);
        }

        return new TwigSource($name, $name);
    }

    /**
     * @param string $name
     * @return string
     */
    public function getCacheKey(string $name): string
    {
        return md5($name);
    }

    /**
     * @param string $name
     * @param int $time
     * @return bool
     */
    public function isFresh(string $name, int $time): bool
    {
        return false;
    }

    /**
     * @param string $name
     * @return bool
     */
    public function exists(string $name)
    {
        return true;
    }
}
