<?php

namespace App\Factory;

use App\Service\HttpService;
use App\Service\TypesService;
use App\Service\ValidatorService;
use App\ViewModel\IndexViewModel;
use Psr\Cache\InvalidArgumentException;
use Symfony\Component\HttpFoundation\Request;

class FrontendFactory
{
    /** @var HttpService */
    private $_httpService;

    /** @var TypesService */
    private $_typesService;

    /** @var ValidatorService */
    private $_validationService;

    /**
     * @param HttpService $httpService
     * @param TypesService $typesService
     * @param ValidatorService $validatorService
     */
    public function __construct(HttpService $httpService, TypesService $typesService, ValidatorService $validatorService)
    {
        $this->_httpService = $httpService;
        $this->_typesService = $typesService;
        $this->_validationService = $validatorService;
    }

    /**
     * @param Request $request
     * @return IndexViewModel
     * @throws InvalidArgumentException
     */
    public function create(Request $request): IndexViewModel
    {
        $advertisingMediumCode = $request->request->get('advertisingMediumCode', '');
        $forceReload = $request->request->get('forceReload', false) === 'true';
        $types = $this->_typesService->getTypes($forceReload);
        $kinds = $types->getKinds();
        $types = $types->getTypes();
        $kind = $request->request->get('kind', '');
        $type = $request->request->get('type', '');
        $template = $request->request->get('template', '');
        $identifiers = $request->request->get('identifiers', '');

        $iFrameSrc = '';
        $errors = [];
        if ($request->isMethod('POST')) {
            $errors = $this->_validationService->validateGenerationRequest($request->request->all());
            if (empty($errors)) {
                $iFrameSrc = $this->_generateIframeUrl(
                    $kind,
                    $type,
                    $template,
                    $identifiers,
                    $advertisingMediumCode,
                    $forceReload
                );
            }
        }

        return new IndexViewModel(
            $advertisingMediumCode,
            $errors,
            $forceReload,
            $identifiers,
            $kind,
            $kinds,
            $iFrameSrc,
            $template,
            $type,
            $types
        );
    }

    /**
     * @param string $kind
     * @param string $type
     * @param string $template
     * @param string $identifiers
     * @param string $advertisingMediumCode
     * @param bool $forceReload
     * @return string
     */
    private function _generateIframeUrl(
        string $kind,
        string $type,
        string $template,
        string $identifiers,
        string $advertisingMediumCode,
        bool $forceReload
    ): string {
        $url = sprintf(
            '/%s/%s/%s/%s',
            $kind,
            $type,
            $template,
            $identifiers
        );

        if (!empty($advertisingMediumCode)) {
            $url .= '/' . $advertisingMediumCode;
        }

        if ($forceReload) {
            $url .= '?forceReload=true';
        }

        return $url;
    }
}
