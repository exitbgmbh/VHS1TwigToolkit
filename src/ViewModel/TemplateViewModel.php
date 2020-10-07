<?php

namespace App\ViewModel;

class TemplateViewModel
{
    /** @var string */
    private $templateName;

    /** @var array */
    private $context;

    /** @var array */
    private $mapping;

    /**
     * @param string $templateName
     * @param array $context
     * @param array $mapping
     */
    public function __construct(string $templateName, array $context, array $mapping)
    {
        $this->templateName = $templateName;
        $this->context = $context;
        $this->mapping = $mapping;
    }

    /**
     * @return string
     */
    public function getTemplateName(): string
    {
        return $this->templateName;
    }

    /**
     * @return array
     */
    public function getContext(): array
    {
        return $this->context;
    }

    /**
     * @return array
     */
    public function getMapping(): array
    {
        return $this->mapping;
    }
}
