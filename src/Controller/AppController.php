<?php

namespace App\Controller;

use App\Factory\TemplateFactory;
use App\Model\RenderRequest;
use App\Service\RenderService;
use App\Service\TwigService;
use Mpdf\MpdfException;
use Psr\Cache\InvalidArgumentException;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Twig\Error\LoaderError;
use Twig\Error\RuntimeError;
use Twig\Error\SyntaxError;

class AppController
{
    /** @var TemplateFactory */
    private $_templateFactory;

    /** @var TwigService */
    private $_twigService;

    /** @var RenderService */
    private $_renderService;

    /**
     * @param TemplateFactory $templateFactory
     * @param TwigService $twigService
     * @param RenderService $renderService
     */
    public function __construct(
        TemplateFactory $templateFactory,
        TwigService $twigService,
        RenderService $renderService
    ) {
        $this->_templateFactory = $templateFactory;
        $this->_twigService = $twigService;
        $this->_renderService = $renderService;
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
        $renderRequest = RenderRequest::fromHttpRequest($request);
        $output = $this->_renderService->render($renderRequest);

        if ($renderRequest->isPdf()) {
            return new Response(
                $output->getPdf(),
                Response::HTTP_OK,
                [
                    'Content-Type' => 'application/pdf',
                ]
            );
        }

        return new Response($output->getHtml());
    }
}
