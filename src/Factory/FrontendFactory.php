<?php

namespace App\Factory;

use App\Service\HttpService;
use App\Service\LanguageService;
use App\Service\TypesService;
use App\Service\ValidatorService;
use App\ViewModel\IndexViewModel;
use Psr\Cache\InvalidArgumentException;
use Symfony\Component\HttpFoundation\Request;

class FrontendFactory
{
    /** @var HttpService */
    private $_httpService;

    /** @var LanguageService */
    private $_languageService;

    /** @var TypesService */
    private $_typesService;

    /** @var ValidatorService */
    private $_validationService;

    /**
     * @param HttpService $httpService
     * @param LanguageService $languageService
     * @param TypesService $typesService
     * @param ValidatorService $validatorService
     */
    public function __construct(
        HttpService $httpService,
        LanguageService $languageService,
        TypesService $typesService,
        ValidatorService $validatorService
    ) {
        $this->_httpService = $httpService;
        $this->_languageService = $languageService;
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
        $language = $request->request->get('language', '');
        $languages = $this->_languageService->getLanguages($forceReload, $advertisingMediumCode, $template);

        $realType = $type;
        if (false !== ($pos = strpos($type, '###'))) {
            $realType = substr($type, 0, $pos);
        };

        $iFrameSrc = '';
        $errors = [];
        if ($request->isMethod('POST')) {
            $errors = $this->_validationService->validateGenerationRequest($request->request->all());
            if (empty($errors)) {
                $iFrameSrc = $this->_generateIframeUrl(
                    $kind,
                    $realType,
                    $template,
                    $identifiers,
                    $advertisingMediumCode,
                    $forceReload,
                    $language
                );
            }
        }

        return new IndexViewModel(
            $advertisingMediumCode,
            $errors,
            $forceReload,
            $identifiers,
            $iFrameSrc,
            $kind,
            $kinds,
            $language,
            $languages,
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
     * @param string $language
     * @return string
     */
    private function _generateIframeUrl(
        string $kind,
        string $type,
        string $template,
        string $identifiers,
        string $advertisingMediumCode,
        bool $forceReload,
        string $language
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

        $query = [];
        if ($forceReload) {
            $query['forceReload'] = 'true';
        }

        if (!empty($language)) {
            $query['language'] = $language;
        }

        if (!empty($query)) {
            $url .= '?' . http_build_query($query);
        }

        return $url;
    }
}
