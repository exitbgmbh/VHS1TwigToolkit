<?php

namespace App\Factory;

use App\Service\HttpService;
use App\Service\ValidatorService;
use App\ViewModel\IndexViewModel;
use Symfony\Component\HttpFoundation\Request;

class FrontendFactory
{
    #public const KIND_SLIP = 1;

    #public const KIND_EMAIL = 2;

    /** @var HttpService */
    private $_httpService;

    /** @var ValidatorService */
    private $_validationService;

    /**
     * @param HttpService $_httpService
     * @param ValidatorService $validatorService
     */
    public function __construct(HttpService $_httpService, ValidatorService $validatorService)
    {
        $this->_httpService = $_httpService;
        $this->_validationService = $validatorService;
    }

    /**
     * @param Request $request
     * @return IndexViewModel
     */
    public function create(Request $request): IndexViewModel
    {
        $advertisingMediumCode = $request->request->get('advertisingMediumCode', '');
        $forceReload = $request->request->get('forceReload', false) === 'on';
        $kinds = $this->_getKinds();
        $types = $this->_httpService->getEmailTypes(); # TODO switch by kind
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
     * @return string[]
     */
    private function _getKinds(): array
    {
        return [
            'email' => 'E-Mail',
            'pdf' => 'PDF',
        ];
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
