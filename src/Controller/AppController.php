<?php

namespace App\Controller;

use App\Service\ConfigService;
use App\Service\HttpService;
use App\Service\JsonService;
use App\Service\MapperService;
use App\Service\PdfService;
use App\Service\TwigService;
use Exception;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

class AppController
{
    /** @var ConfigService */
    private $_configService;

    /** @var HttpService */
    private $_httpService;

    /** @var JsonService */
    private $_jsonService;

    /** @var MapperService */
    private $_mapperService;

    /** @var PdfService */
    private $_pdfService;

    /** @var TwigService */
    private $_twigService;

    /**
     * @param ConfigService $configService
     * @param HttpService $httpService
     * @param JsonService $jsonService
     * @param MapperService $mapperService
     * @param PdfService $pdfService
     * @param TwigService $twigService
     */
    public function __construct(
        ConfigService $configService,
        HttpService $httpService,
        JsonService $jsonService,
        MapperService $mapperService,
        PdfService $pdfService,
        TwigService $twigService
    ) {
        $this->_configService = $configService;
        $this->_httpService = $httpService;
        $this->_jsonService = $jsonService;
        $this->_mapperService = $mapperService;
        $this->_pdfService = $pdfService;
        $this->_twigService = $twigService;
    }

    /**
     * @param Request $request
     * @return Response
     * @throws Exception
     */
    public function generate(Request $request): Response
    {
        $type = $request->attributes->get('type');
        $templateName = $request->attributes->get('template');
        $identifiers = $request->attributes->get('identifiers');
        $advertisingMediumCode = $request->attributes->get('advertisingMediumCode');

        $contextUrl = $this->_configService->getContextUrl($type, $identifiers);
        $context = $this->_httpService->get($contextUrl);
        $context = $this->_jsonService->parseJson($context);
        $context = $this->_mapperService->map($context, $this->_configService->getMappingContext());
        $renderedTemplate = $this->_twigService->renderTemplate($templateName, $context, $advertisingMediumCode);
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
