<?php

namespace App\ViewModel;

class RequestViewModel
{
    /** @var string */
    private $advertisingMediumCode;

    /** @var array */
    private $errors;

    /** @var bool */
    private $forceReload;

    /** @var string */
    private $identifiers;

    /** @var string */
    private $productId;

    /** @var string */
    private $iFrameSrc;

    /** @var string */
    private $kind;

    /** @var array */
    private $kinds;

    /** @var string */
    private $language;

    /** @var array */
    private $languages;

    /** @var string */
    private $template;

    /** @var string */
    private $type;

    /** @var array */
    private $types;

    /** @var string */
    private $format;

    /** @var string */
    private $size;

    /**
     * @param string $advertisingMediumCode
     * @param array $errors
     * @param bool $forceReload
     * @param string $identifiers
     * @param string $productId
     * @param string $iFrameSrc
     * @param string $kind
     * @param array $kinds
     * @param string $language
     * @param array $languages
     * @param string $template
     * @param string $type
     * @param array $types
     */
    public function __construct(
        string $advertisingMediumCode,
        array $errors,
        bool $forceReload,
        string $identifiers,
        string $productId,
        string $iFrameSrc,
        string $kind,
        array $kinds,
        string $language,
        array $languages,
        string $template,
        string $type,
        array $types,
        string $format,
        string $size
    ) {
        $this->advertisingMediumCode = $advertisingMediumCode;
        $this->errors = $errors;
        $this->forceReload = $forceReload;
        $this->identifiers = $identifiers;
        $this->productId = $productId;
        $this->iFrameSrc = $iFrameSrc;
        $this->kind = $kind;
        $this->kinds = $kinds;
        $this->language = $language;
        $this->languages = $languages;
        $this->template = $template;
        $this->type = $type;
        $this->types = $types;
        $this->size = $size;
        $this->format = $format;
    }

    /**
     * @return string
     */
    public function getAdvertisingMediumCode(): string
    {
        return $this->advertisingMediumCode;
    }

    /**
     * @return array
     */
    public function getErrors(): array
    {
        return $this->errors;
    }

    /**
     * @return bool
     */
    public function forceReload(): bool
    {
        return $this->forceReload;
    }

    /**
     * @return string
     */
    public function getIdentifiers(): string
    {
        return $this->identifiers;
    }

    /**
     * @return string
     */
    public function getProductId(): string
    {
        return $this->productId;
    }

    /**
     * @return string
     */
    public function getIFrameSrc(): string
    {
        return $this->iFrameSrc;
    }

    /**
     * @return string
     */
    public function getKind(): string
    {
        return $this->kind;
    }

    /**
     * @return array
     */
    public function getKinds(): array
    {
        return $this->kinds;
    }

    /**
     * @return string
     */
    public function getLanguage(): string
    {
        return $this->language;
    }

    /**
     * @return array
     */
    public function getLanguages(): array
    {
        return $this->languages;
    }

    /**
     * @return string
     */
    public function getTemplate(): string
    {
        return $this->template;
    }

    /**
     * @return string
     */
    public function getType(): string
    {
        return $this->type;
    }

    /**
     * @return array
     */
    public function getTypes(): array
    {
        return $this->types;
    }

    /**
     * @return string
     */
    public function getFormat(): string
    {
        return $this->format;
    }

    /**
     * @return string
     */
    public function getSize(): string
    {
        return $this->size;
    }
}
