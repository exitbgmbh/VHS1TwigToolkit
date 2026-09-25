<?php

namespace App\Factory;

use App\ViewModel\TemplateViewModel;
use Psr\Cache\InvalidArgumentException;
use Symfony\Component\HttpFoundation\Request;

class TemplateFactory
{
    /** @var ViewModelFactory */
    private $_viewModelFactory;

    /**
     * @param ViewModelFactory $viewModelFactory
     */
    public function __construct(ViewModelFactory $viewModelFactory)
    {
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
                'size' => $requestViewModel->getSize(),
                'format' => $requestViewModel->getFormat(),
                'types' => json_encode($requestViewModel->getTypes()),
                'iframeSrc' => $requestViewModel->getIFrameSrc(),
                'template' => $requestViewModel->getTemplate(),
                'identifiers' => $requestViewModel->getIdentifiers(),
                'productId' => $requestViewModel->getProductId(),
                'advertisingMediumCode' => $requestViewModel->getAdvertisingMediumCode(),
                'forceReload'           => $requestViewModel->forceReload(),
                'year'                  => date('Y'),
                'activeConfig'          => $requestViewModel->getConfig(),
                'availableConfigs'      => $requestViewModel->getAvailableConfigs(),
                'defaultConfigUrl'      => $requestViewModel->getDefaultConfigUrl(),
                'templateSet'           => $requestViewModel->getTemplateSet(),
                'templateSets'          => $requestViewModel->getTemplateSets(),
            ],
            [],
            $requestViewModel->getKind()
        );
    }
}
