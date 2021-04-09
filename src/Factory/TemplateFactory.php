<?php

namespace App\Factory;

use App\Service\ContextService;
use App\Service\SecurityService;
use App\Service\TextModulesService;
use App\ViewModel\TemplateViewModel;
use Psr\Cache\InvalidArgumentException;
use Symfony\Component\HttpFoundation\Request;

class TemplateFactory
{
    /** @var ContextService */
    private $_contextService;

    /** @var SecurityService */
    private $_securityService;

    /** @var TextModulesService */
    private $_textModulesService;

    /** @var ViewModelFactory */
    private $_viewModelFactory;

    /**
     * @param ContextService $contextService
     * @param SecurityService $securityService
     * @param TextModulesService $textModulesService
     * @param ViewModelFactory $viewModelFactory
     */
    public function __construct(
        ContextService $contextService,
        SecurityService $securityService,
        TextModulesService $textModulesService,
        ViewModelFactory $viewModelFactory
    ) {
        $this->_contextService = $contextService;
        $this->_securityService = $securityService;
        $this->_textModulesService = $textModulesService;
        $this->_viewModelFactory = $viewModelFactory;
    }

    /**
     * @param Request $request
     * @return TemplateViewModel
     * @throws InvalidArgumentException
     */
    public function create(Request $request): TemplateViewModel
    {
        $requestViewModel = $this->_viewModelFactory->createRequestViewModel($request);

        return new TemplateViewModel(
            'index.html.twig',
            [
                'errors' => implode(',', $requestViewModel->getErrors()),
                'kind' => $requestViewModel->getKind(),
                'kinds' => $requestViewModel->getKinds(),
                'selectedLanguage' => $requestViewModel->getLanguage(),
                'languages' => $requestViewModel->getLanguages(),
                'type' => $requestViewModel->getType(),
                'types' => json_encode($requestViewModel->getTypes()),
                'iframeSrc' => $requestViewModel->getIFrameSrc(),
                'template' => $requestViewModel->getTemplate(),
                'identifiers' => $requestViewModel->getIdentifiers(),
                'productId' => $requestViewModel->getProductId(),
                'advertisingMediumCode' => $requestViewModel->getAdvertisingMediumCode(),
                'forceReload' => $requestViewModel->forceReload(),
                'year' => date('Y'),
            ],
            [],
            $requestViewModel->getKind()
        );
    }

    /**
     * @param Request $request
     * @return TemplateViewModel
     * @throws InvalidArgumentException
     */
    public function createContent(Request $request): TemplateViewModel
    {
        $requestViewModel = $this->_viewModelFactory->createRequestViewModel($request);
        $jwt = $this->_securityService->getJwt($requestViewModel->forceReload());

        $context = $this->_contextService->getContext(
            $requestViewModel->getKind(),
            $requestViewModel->getType(),
            $requestViewModel->getIdentifiers(),
            $requestViewModel->getProductId(),
            $jwt,
            $requestViewModel->forceReload()
        );

        $textModulesMapping = $this->_textModulesService->getTextModules(
            $requestViewModel->getTemplate(),
            $requestViewModel->getAdvertisingMediumCode(),
            $requestViewModel->getLanguage(),
            $jwt,
            $requestViewModel->forceReload()
        );

        return new TemplateViewModel(
            $requestViewModel->getTemplate(),
            $context,
            $textModulesMapping,
            $requestViewModel->getKind()
        );
    }
}
