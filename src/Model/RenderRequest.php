<?php

namespace App\Model;

use App\Service\TypesService;
use Symfony\Component\HttpFoundation\Request;

/**
 * Immutable parameters for rendering a template (independent of HTTP).
 */
final class RenderRequest
{
    /** @var string */
    private $kind;

    /** @var string */
    private $type;

    /** @var string */
    private $template;

    /** @var string */
    private $identifiers;

    /** @var string */
    private $productId;

    /** @var string */
    private $advertisingMediumCode;

    /** @var string */
    private $language;

    /** @var string */
    private $format;

    /** @var string */
    private $size;

    /** @var bool */
    private $forceReload;

    public function __construct(
        string $kind,
        string $type,
        string $template,
        string $identifiers,
        string $productId = '',
        string $advertisingMediumCode = '',
        string $language = '',
        string $format = 'P',
        string $size = 'A4',
        bool $forceReload = false
    ) {
        $this->kind = $kind;
        $this->type = $type;
        $this->template = $template;
        $this->identifiers = $identifiers;
        $this->productId = $productId;
        $this->advertisingMediumCode = $advertisingMediumCode;
        $this->language = $language;
        $this->format = $format;
        $this->size = $size;
        $this->forceReload = $forceReload;
    }

    /**
     * Reads the parameters with the same names and defaults as ViewModelFactory::createRequestViewModel.
     */
    public static function fromHttpRequest(Request $request): self
    {
        return new self(
            (string) $request->get('kind', ''),
            (string) $request->get('type', ''),
            (string) $request->get('template', ''),
            (string) $request->get('identifiers', ''),
            (string) $request->get('productId', ''),
            (string) $request->get('advertisingMediumCode', ''),
            (string) $request->get('language', ''),
            (string) $request->get('format', 'P'),
            (string) $request->get('size', 'A4'),
            $request->get('forceReload', false) === 'true'
        );
    }

    public function getKind(): string
    {
        return $this->kind;
    }

    public function getType(): string
    {
        return $this->type;
    }

    public function getTemplate(): string
    {
        return $this->template;
    }

    public function getIdentifiers(): string
    {
        return $this->identifiers;
    }

    public function getProductId(): string
    {
        return $this->productId;
    }

    public function getAdvertisingMediumCode(): string
    {
        return $this->advertisingMediumCode;
    }

    public function getLanguage(): string
    {
        return $this->language;
    }

    public function getFormat(): string
    {
        return $this->format;
    }

    public function getSize(): string
    {
        return $this->size;
    }

    public function isForceReload(): bool
    {
        return $this->forceReload;
    }

    public function isPdf(): bool
    {
        return $this->kind === TypesService::TEMPLATE_TYPE_DOCUMENT_NAME;
    }
}
