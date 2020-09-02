<?php

namespace App\ViewModel;

class IndexViewModel
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
    private $kind;

    /** @var array */
    private $kinds;

    /** @var string */
    private $iFrameSrc;

    /** @var string */
    private $template;

    /** @var string */
    private $type;

    /** @var array */
    private $types;

    /**
     * @param string $advertisingMediumCode
     * @param array $errors
     * @param bool $forceReload
     * @param string $identifiers
     * @param string $kind
     * @param array $kinds
     * @param string $iFrameSrc
     * @param string $template
     * @param string $type
     * @param array $types
     */
    public function __construct(
        string $advertisingMediumCode,
        array $errors,
        bool $forceReload,
        string $identifiers,
        string $kind,
        array $kinds,
        string $iFrameSrc,
        string $template,
        string $type,
        array $types
    ) {
        $this->advertisingMediumCode = $advertisingMediumCode;
        $this->errors = $errors;
        $this->forceReload = $forceReload;
        $this->identifiers = $identifiers;
        $this->kind = $kind;
        $this->kinds = $kinds;
        $this->iFrameSrc = $iFrameSrc;
        $this->template = $template;
        $this->type = $type;
        $this->types = $types;
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
    public function getIFrameSrc(): string
    {
        return $this->iFrameSrc;
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
}
