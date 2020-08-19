<?php

namespace App\Controller;

use App\Service\ContextService;
use App\Service\PdfService;
use App\Service\SecurityService;
use App\Service\TextModulesService;
use App\Service\TwigService;
use Exception;
use Mpdf\MpdfException;
use Psr\Cache\InvalidArgumentException;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Twig\Error\LoaderError;
use Twig\Error\RuntimeError;
use Twig\Error\SyntaxError;

class AppController
{
    /** @var ContextService */
    private $_contextService;

    /** @var PdfService */
    private $_pdfService;

    /** @var SecurityService */
    private $_securityService;

    /** @var TextModulesService */
    private $_textModulesService;

    /** @var TwigService */
    private $_twigService;

    /**
     * @param ContextService $contextService
     * @param PdfService $pdfService
     * @param SecurityService $securityService
     * @param TextModulesService $textModulesService
     * @param TwigService $twigService
     */
    public function __construct(
        ContextService $contextService,
        PdfService $pdfService,
        SecurityService $securityService,
        TextModulesService $textModulesService,
        TwigService $twigService
    ) {
        $this->_contextService = $contextService;
        $this->_pdfService = $pdfService;
        $this->_securityService = $securityService;
        $this->_textModulesService = $textModulesService;
        $this->_twigService = $twigService;
    }

    /**
     * @param Request $request
     * @return Response
     * @throws Exception
     * @throws MpdfException
     * @throws InvalidArgumentException
     * @throws LoaderError
     * @throws RuntimeError
     * @throws SyntaxError
     */
    public function generate(Request $request): Response
    {
        $type = $request->attributes->get('type');
        $templateName = $request->attributes->get('template');
        $identifiers = $request->attributes->get('identifiers');
        $advertisingMediumCode = $request->attributes->get('advertisingMediumCode');
        $forceReload = $request->get('forceReload', false) === 'true';

        $jwt = $this->_securityService->getJwt();
        $context = $this->_contextService->getContext($type, $identifiers, $jwt, $forceReload);
        $textModulesMapping = $this->_textModulesService->getTextModules($advertisingMediumCode, $jwt, $forceReload);
        $renderedTemplate = $this->_twigService->renderTemplate($templateName, $context, $textModulesMapping);
        $pdf = $this->_pdfService->renderPdf($renderedTemplate);

        return new Response(
            $pdf,
            Response::HTTP_OK,
            [
                'Content-Type' => 'application/pdf'
            ]
        );
    }
}
