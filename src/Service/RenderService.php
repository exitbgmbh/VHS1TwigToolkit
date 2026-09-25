<?php

namespace App\Service;

use App\Model\RenderOutput;
use App\Model\RenderRequest;
use Mpdf\MpdfException;
use Psr\Cache\InvalidArgumentException;
use Twig\Error\LoaderError;
use Twig\Error\RuntimeError;
use Twig\Error\SyntaxError;

/**
 * Renders a template without an HTTP request: JWT, context, text modules, Twig and (for PDFs) mPDF.
 * All exceptions are passed through unchanged.
 */
class RenderService
{
    /** @var SecurityService */
    private $_securityService;

    /** @var ContextService */
    private $_contextService;

    /** @var TextModulesService */
    private $_textModulesService;

    /** @var TwigService */
    private $_twigService;

    /** @var PdfService */
    private $_pdfService;

    public function __construct(
        SecurityService $securityService,
        ContextService $contextService,
        TextModulesService $textModulesService,
        TwigService $twigService,
        PdfService $pdfService
    ) {
        $this->_securityService = $securityService;
        $this->_contextService = $contextService;
        $this->_textModulesService = $textModulesService;
        $this->_twigService = $twigService;
        $this->_pdfService = $pdfService;
    }

    /**
     * @return array{context: array, textModules: array}
     * @throws InvalidArgumentException
     */
    public function loadRenderData(RenderRequest $r): array
    {
        $jwt = $this->_securityService->getJwt($r->isForceReload());

        $context = $this->_contextService->getContext(
            $r->getKind(),
            $r->getType(),
            $r->getIdentifiers(),
            $r->getProductId(),
            $jwt,
            $r->isForceReload()
        );

        $textModules = $this->_textModulesService->getTextModules(
            $r->getTemplate(),
            $r->getAdvertisingMediumCode(),
            $r->getLanguage(),
            $jwt,
            $r->isForceReload()
        );

        return [
            'context' => $context,
            'textModules' => $textModules,
        ];
    }

    /**
     * @throws InvalidArgumentException
     * @throws LoaderError
     * @throws RuntimeError
     * @throws SyntaxError
     */
    public function renderHtml(RenderRequest $r): string
    {
        $data = $this->loadRenderData($r);

        return $this->_twigService->renderTemplate(
            $r->getTemplate(),
            $data['context'],
            $data['textModules'],
            $r->getKind()
        );
    }

    /**
     * @throws MpdfException
     */
    public function renderPdfFromHtml(string $html, RenderRequest $r): string
    {
        return $this->_pdfService->renderPdf($html, $r->getSize(), $r->getFormat());
    }

    /**
     * @throws InvalidArgumentException
     * @throws LoaderError
     * @throws MpdfException
     * @throws RuntimeError
     * @throws SyntaxError
     */
    public function render(RenderRequest $r): RenderOutput
    {
        $html = $this->renderHtml($r);
        $pdf = $r->isPdf() ? $this->renderPdfFromHtml($html, $r) : null;

        return new RenderOutput($html, $pdf);
    }
}
