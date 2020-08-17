<?php

use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

require_once __DIR__ . '/../service/ConfigService.php';
require_once __DIR__ . '/../service/HttpService.php';
require_once __DIR__ . '/../service/JsonService.php';
require_once __DIR__ . '/../service/MapperService.php';
require_once __DIR__ . '/../service/PdfService.php';
require_once __DIR__ . '/../service/TwigService.php';
require_once __DIR__ . '/../twig/loader/TextModuleLoader.php';

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

    public function __construct()
    {
        $this->_configService = new ConfigService();
        $this->_httpService = new HttpService();
        $this->_jsonService = new JsonService();
        $this->_mapperService = new MapperService();
        $this->_pdfService = new PdfService();
        $this->_twigService = new TwigService();
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
