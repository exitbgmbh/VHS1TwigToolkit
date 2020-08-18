<?php

namespace App\Twig\Loader;

use Twig\Loader\LoaderInterface as TwigLoaderInterface;
use Twig\Source as TwigSource;

class TextModuleLoader implements TwigLoaderInterface
{
    /** @var array */
    private $mapping = [];

    /**
     * @param array $mapping
     */
    public function __construct(array $mapping)
    {
        $this->mapping = $mapping;
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
