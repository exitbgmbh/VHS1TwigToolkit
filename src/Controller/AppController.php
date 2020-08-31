<?php

namespace App\Controller;

use App\Factory\FrontendFactory;
use App\Service\ContextService;
use App\Service\PdfService;
use App\Service\SecurityService;
use App\Service\TextModulesService;
use App\Service\TwigService;
use App\Service\ValidatorService;
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

    /** @var FrontendFactory */
    private $_frontendFactory;

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
     * @param FrontendFactory $frontendFactory
     * @param PdfService $pdfService
     * @param SecurityService $securityService
     * @param TextModulesService $textModulesService
     * @param TwigService $twigService
     */
    public function __construct(
        ContextService $contextService,
        FrontendFactory $frontendFactory,
        PdfService $pdfService,
        SecurityService $securityService,
        TextModulesService $textModulesService,
        TwigService $twigService
    ) {
        $this->_contextService = $contextService;
        $this->_frontendFactory = $frontendFactory;
        $this->_pdfService = $pdfService;
        $this->_securityService = $securityService;
        $this->_textModulesService = $textModulesService;
        $this->_twigService = $twigService;
    }

    /**
     * @param Request $request
     * @return Response
     * @throws InvalidArgumentException
     * @throws LoaderError
     * @throws RuntimeError
     * @throws SyntaxError
     */
    public function index(Request $request): Response
    {
        $viewModel = $this->_frontendFactory->create($request);

        $context = $this->_twigService->renderTemplate(
            'index.html.twig',
            [
                'errors' => implode(',', $viewModel->getErrors()),
                'kinds' => $viewModel->getKinds(),
                'type' => $viewModel->getType(),
                'types' => json_encode($viewModel->getTypes()),
                'iframeSrc' => $viewModel->getIFrameSrc(),
                'template' => $viewModel->getTemplate(),
                'identifiers' => $viewModel->getIdentifiers(),
                'advertisingMediumCode' => $viewModel->getAdvertisingMediumCode(),
                'forceReload' => $viewModel->forceReload(),
             ],
            []
        );

        return new Response($context);
    }

    /**
     * @param Request $request
     * @return Response
     * @throws InvalidArgumentException
     * @throws LoaderError
     * @throws MpdfException
     * @throws RuntimeError
     * @throws SyntaxError
     */
    public function generate(Request $request): Response
    {
        $renderedTemplate = $this->_renderTemplate($request);
        $pdf = $this->_pdfService->renderPdf($renderedTemplate);

        return new Response(
            $pdf,
            Response::HTTP_OK,
            [
                'Content-Type' => 'application/pdf'
            ]
        );
    }

    /**
     * @param Request $request
     * @return string
     * @throws InvalidArgumentException
     * @throws LoaderError
     * @throws RuntimeError
     * @throws SyntaxError
     */
    private function _renderTemplate(Request $request)
    {
        $type = $request->attributes->get('type');
        $templateName = $request->attributes->get('template');
        $identifiers = $request->attributes->get('identifiers');
        $advertisingMediumCode = $request->attributes->get('advertisingMediumCode');
        $forceReload = $request->get('forceReload', false) === 'true';

        $jwt = $this->_securityService->getJwt();
        $context = $this->_contextService->getContext($type, $identifiers, $jwt, $forceReload);

        $textModulesMapping = $this->_textModulesService->getTextModules($advertisingMediumCode, $jwt, $forceReload);

        return $this->_twigService->renderTemplate($templateName, $context, $textModulesMapping);
    }
}
