<?php

namespace App\Controller;

use App\Factory\TemplateFactory;
use App\Service\PdfService;
use App\Service\TwigService;
use App\Service\TypesService;
use Mpdf\MpdfException;
use Psr\Cache\InvalidArgumentException;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Twig\Error\LoaderError;
use Twig\Error\RuntimeError;
use Twig\Error\SyntaxError;

class AppController
{
    /** @var PdfService */
    private $_pdfService;

    /** @var TemplateFactory */
    private $_templateFactory;

    /** @var TwigService */
    private $_twigService;

    /**
     * @param PdfService $pdfService
     * @param TemplateFactory $frontendFactory
     * @param TwigService $twigService
     */
    public function __construct(
        PdfService $pdfService,
        TemplateFactory $frontendFactory,
        TwigService $twigService
    ) {
        $this->_pdfService = $pdfService;
        $this->_templateFactory = $frontendFactory;
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
        $viewModel = $this->_templateFactory->create($request);
        $response = $this->_twigService->renderTemplate(
            $viewModel->getTemplateName(),
            $viewModel->getContext(),
            $viewModel->getMapping(),
            $viewModel->getKind()
        );

        return new Response($response);
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
        $viewModel = $this->_templateFactory->createContent($request);
        $response = $this->_twigService->renderTemplate(
            $viewModel->getTemplateName(),
            $viewModel->getContext(),
            $viewModel->getMapping(),
            $viewModel->getKind()
        );

        if ($request->attributes->get('kind') === TypesService::TEMPLATE_TYPE_DOCUMENT_NAME) {
            $pdf = $this->_pdfService->renderPdf($response);

            return new Response(
                $pdf,
                Response::HTTP_OK,
                [
                    'Content-Type' => 'application/pdf',
                ]
            );
        }

        return new Response($response);
    }
}
