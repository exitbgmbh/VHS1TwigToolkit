<?php

namespace App\Factory;

use App\Service\LanguageService;
use App\Service\TypesService;
use App\Service\ValidatorService;
use App\ViewModel\RequestViewModel;
use Psr\Cache\InvalidArgumentException;
use Symfony\Component\HttpFoundation\Request;

class ViewModelFactory
{
    /** @var LanguageService */
    private $_languageService;

    /** @var TypesService */
    private $_typesService;

    /** @var ValidatorService */
    private $_validatorService;

    /**
     * @param LanguageService $languageService
     * @param TypesService $typesService
     * @param ValidatorService $validatorService
     */
    public function __construct(
        LanguageService $languageService,
        TypesService $typesService,
        ValidatorService $validatorService
    ) {
        $this->_languageService = $languageService;
        $this->_typesService = $typesService;
        $this->_validatorService = $validatorService;
    }

    /**
     * @param Request $request
     * @return RequestViewModel
     * @throws InvalidArgumentException
     */
    public function createRequestViewModel(Request $request): RequestViewModel
    {
        $advertisingMediumCode = $request->get('advertisingMediumCode', '');
        $forceReload = $request->get('forceReload', false) === 'true';
        $types = $this->_typesService->getTypes($forceReload);
        $kinds = $types->getKinds();
        $types = $types->getTypes();
        $kind = $request->get('kind', '');
        $type = $request->get('type', '');
        $template = $request->get('template', '');
        $identifiers = $request->get('identifiers', '');
        $productId = $request->get('productId', '');
        $language = $request->get('language', '');
        $languages = $this->_languageService->getLanguages($forceReload);
        $realType = $this->_typesService->getRealType($type);

        $iFrameSrc = '';
        $errors = [];
        if ($request->isMethod('POST')) {
            $errors = $this->_validatorService->validateGenerationRequest($request->request->all());
            if (empty($errors)) {
                $iFrameSrc = $this->_generateIframeUrl(
                    $kind,
                    $realType,
                    $template,
                    $identifiers,
                    $productId,
                    $advertisingMediumCode,
                    $forceReload,
                    $language
                );
            }
        }

        return new RequestViewModel(
            $advertisingMediumCode,
            $errors,
            $forceReload,
            $identifiers,
            $productId,
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
     * @param string $productId
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
        string $productId,
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

        if (!empty($productId)) {
            $query['productId'] = $productId;
        }

        if (!empty($query)) {
            $url .= '?' . http_build_query($query);
        }

        return $url;
    }
}
