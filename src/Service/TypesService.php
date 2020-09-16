<?php

namespace App\Service;

use App\Model\TypesModel;
use Exception;
use Psr\Cache\InvalidArgumentException;

class TypesService
{
    /** @var string */
    public const TYPE_SEPARATOR = '###';

    /** @var int */
    public const TEMPLATE_TYPE_DOCUMENT = 1;

    /** @var string */
    public const TEMPLATE_TYPE_DOCUMENT_NAME = 'pdf';

    /** @var int */
    public const TEMPLATE_TYPE_EMAIL = 2;

    /** @var string */
    public const TEMPLATE_TYPE_EMAIL_NAME = 'email';

    /** @var int */
    public const TEMPLATE_TYPE_SNIPPET = 3;

    /** @var string */
    public const TEMPLATE_TYPE_SNIPPET_NAME = 'snippet';

    /** @var CacheService */
    private $_cacheService;

    /** @var ConfigService */
    private $_configService;

    /** @var HttpService */
    private $_httpService;

    /** @var JsonService */
    private $_jsonService;

    /** @var SecurityService */
    private $_securityService;

    /** @var VhsBuildService */
    private $_vhsBuildService;

    /**
     * @param CacheService $cacheService
     * @param ConfigService $configService
     * @param HttpService $httpService
     * @param JsonService $jsonService
     * @param SecurityService $securityService
     * @param VhsBuildService $vhsBuildService
     */
    public function __construct(
        CacheService $cacheService,
        ConfigService $configService,
        HttpService $httpService,
        JsonService $jsonService,
        SecurityService $securityService,
        VhsBuildService $vhsBuildService
    ) {
        $this->_cacheService = $cacheService;
        $this->_configService = $configService;
        $this->_httpService = $httpService;
        $this->_jsonService = $jsonService;
        $this->_securityService = $securityService;
        $this->_vhsBuildService = $vhsBuildService;
    }

    /**
     * @param bool $forceReload
     * @return TypesModel
     * @throws InvalidArgumentException
     * @throws Exception
     */
    public function getTypes(bool $forceReload): TypesModel
    {
        $typesCacheKey = $this->_cacheService->getTypesCacheKey($this->_configService->getRestEndpoint());
        $types = [];
        $typesAreInCache = $this->_cacheService->has($typesCacheKey);
        if ($typesAreInCache && !$forceReload) {
            $types = $this->_cacheService->get($typesCacheKey)->get();
        }

        $buildNumber = $this->_vhsBuildService->getBuildVersion($forceReload);
        if ($buildNumber <= VhsBuildService::VHS_MAX_BUILD_UNSUPPORTED_TYPES_API) {
            return new TypesModel(
                [
                    self::TEMPLATE_TYPE_DOCUMENT_NAME => 'PDF',
                ],
                $this->_getStaticPdfTypes(),
            );
        }

        if ($forceReload || !$typesAreInCache) {
            $typesEndpointUrl = $this->_configService->getTypesEndpointUrl();
            $response = $this->_httpService->getTypes($typesEndpointUrl, $this->_securityService->getJwt());
            $response = $this->_jsonService->parseJson($response);
            $response = $response['response'];

            $types = $response;

            $this->_cacheService->set($typesCacheKey, $types);
        }

        $types = $this->_parseTypes($types);
        $kinds = $this->_getKindsFromAvailableTypes($types);

        return new TypesModel(
            $kinds,
            $types
        );
    }

    /**
     * @param string $type
     * @return string
     */
    public function getRealType(string $type): string
    {
        $realType = $type;
        if (false !== ($pos = strpos($type, self::TYPE_SEPARATOR))) {
            $realType = substr($type, 0, $pos);
        };

        return $realType;
    }

    /**
     * @param array $types
     * @return array
     * @throws Exception
     */
    private function _parseTypes(array $types): array
    {
        $result = [];
        foreach ($types as $templateCategory => $templateTypes) {
            $availableTypes = [];
            foreach ($templateTypes as $templateTypeKey => $templateType) {
                if (empty($templateType['renderer'])) {
                    continue;
                }

                $renderer = $templateType['renderer'];
                $name = $templateType['name'];
                if (in_array($renderer, array_column($availableTypes, 'renderer'))) {
                    $renderer .= self::TYPE_SEPARATOR . $name;
                }

                $availableTypes[] = [
                    'name' => $name,
                    'renderer' => $renderer,
                ];
            }

            if (!empty($availableTypes)) {
                $key = $this->_getTextForTemplateCategory($templateCategory);
                $result[$key] = $availableTypes;
            }
        }

        return $result;
    }

    /**
     * @param array $availableTypes
     * @return array
     */
    private function _getKindsFromAvailableTypes(array $availableTypes): array
    {
        $keys = array_keys($availableTypes);
        $availableKinds = [];
        foreach ($keys as $availableType) {
            if ($availableType === self::TEMPLATE_TYPE_DOCUMENT_NAME) {
                $availableKinds[self::TEMPLATE_TYPE_DOCUMENT_NAME] = 'PDF';
            }

            if ($availableType === self::TEMPLATE_TYPE_EMAIL_NAME) {
                $availableKinds[self::TEMPLATE_TYPE_EMAIL_NAME] = 'E-Mail';
            }

            if ($availableType === self::TEMPLATE_TYPE_SNIPPET_NAME) {
                $availableKinds[self::TEMPLATE_TYPE_SNIPPET_NAME] = 'Snippet';
            }
        }

        return $availableKinds;
    }

    /**
     * @param int $templateCategory
     * @return string
     * @throws Exception
     */
    private function _getTextForTemplateCategory(int $templateCategory): string
    {
        if (self::TEMPLATE_TYPE_DOCUMENT === $templateCategory) {
            return self::TEMPLATE_TYPE_DOCUMENT_NAME;
        }

        if (self::TEMPLATE_TYPE_EMAIL === $templateCategory) {
            return self::TEMPLATE_TYPE_EMAIL_NAME;
        }

        if (self::TEMPLATE_TYPE_SNIPPET === $templateCategory) {
            return self::TEMPLATE_TYPE_SNIPPET_NAME;
        }

        throw new Exception(sprintf('unknown template category "%s"', $templateCategory));
    }

    /**
     * @return string[]
     */
    private function _getStaticPdfTypes(): array
    {
        return [
            self::TEMPLATE_TYPE_DOCUMENT_NAME => [
                [
                    'name' => 'Rechnung',
                    'renderer' => 'Invoice###Rechnung',
                ],
                [
                    'name' => 'Rechnung (Email-Anhang)',
                    'renderer' => 'Invoice###Rechnung (Email-Anhang)',
                ],
                [
                    'name' => 'Lieferschein',
                    'renderer' => 'Delivery',
                ],
                [
                    'name' => 'Retourenschein',
                    'renderer' => 'Return',
                ],
                [
                    'name' => 'Angebot',
                    'renderer' => 'Offer',
                ],
                [
                    'name' => 'Auftragsbestätigung',
                    'renderer' => 'OrderConfirmation',
                ],
                [
                    'name' => 'Pick-Box Label',
                    'renderer' => 'PickBox',
                ],
                [
                    'name' => 'Pick-Liste',
                    'renderer' => 'picklist',
                ],
                [
                    'name' => 'Kassenabschluss',
                    'renderer' => 'PosReport',
                ],
                [
                    'name' => 'Produkt Label',
                    'renderer' => 'ProductLabel',
                ],
                [
                    'name' => 'Inventurbericht',
                    'renderer' => 'StockInventory',
                ],
                [
                    'name' => 'Lagernachfüllauftrag',
                    'renderer' => 'StockRelocation',
                ],
                [
                    'name' => 'Lieferantenbestellung',
                    'renderer' => 'SupplierOrder',
                ],
                [
                    'name' => 'Lieferantenbegleitdokument',
                    'renderer' => 'SupplyNote',
                ],
                [
                    'name' => 'Lager-Fach Label',
                    'renderer' => 'TrayLabel',
                ],
                [
                    'name' => 'Benutzer Login-Card',
                    'renderer' => 'UserCard',
                ],
            ]
        ];
    }
}
