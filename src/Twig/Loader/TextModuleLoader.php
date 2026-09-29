<?php

namespace App\Twig\Loader;

use Twig\Error\LoaderError as TwigLoaderError;
use Twig\Loader\LoaderInterface as TwigLoaderInterface;
use Twig\Source as TwigSource;

class TextModuleLoader implements TwigLoaderInterface
{
    /** File-like names (template files, not text module keys) that strict mode refuses. */
    private const FILE_NAME_PATTERN = '/\\.(html?|twig|txt|css|json|svg)$/i';

    /** @var array */
    private $mapping = [];

    /** @var bool */
    private $strict;

    /**
     * @param string $key
     * @param string $value
     */
    public function addMapping(string $key, string $value)
    {
        $this->mapping[$key] = $value;
    }

    /**
     * @param array $mapping
     * @param bool $strict if true, unknown file-like names (e.g. "missing.html") are not served as
     *                     text module placeholders, so a missing template or include raises a LoaderError
     */
    public function __construct(array $mapping, bool $strict = false)
    {
        $this->mapping = $mapping;
        $this->strict = $strict;
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

        if ($this->_isRefused($name)) {
            throw new TwigLoaderError(sprintf('Template "%s" is not defined.', $name));
        }

        return new TwigSource('<span style="background-color: red;">' . $name . '</span>', $name);
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
        return !$this->_isRefused($name);
    }

    private function _isRefused(string $name): bool
    {
        return $this->strict
            && !array_key_exists($name, $this->mapping)
            && preg_match(self::FILE_NAME_PATTERN, $name) === 1;
    }
}
