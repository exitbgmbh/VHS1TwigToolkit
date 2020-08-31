<?php

namespace App\Service;

use App\Model\TypesModel;
use Exception;
use Psr\Cache\InvalidArgumentException;
use Symfony\Component\HttpFoundation\Response;

class TypesService
{
    /** @var int */
    public const TEMPLATE_TYPE_DOCUMENT = 1;

    /** @var int */
    public const TEMPLATE_TYPE_EMAIL = 2;

    /** @var int */
    public const TEMPLATE_TYPE_SNIPPET = 3;

    /** @var int */
    public const VHS_MIN_BUILD_TO_SUPPORT_TYPES_API = 1930;

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

    /**
     * @param CacheService $_cacheService
     * @param ConfigService $_configService
     * @param HttpService $_httpService
     * @param JsonService $_jsonService
     * @param SecurityService $_securityService
     */
    public function __construct(
        CacheService $_cacheService,
        ConfigService $_configService,
        HttpService $_httpService,
        JsonService $_jsonService,
        SecurityService $_securityService
    ) {
        $this->_cacheService = $_cacheService;
        $this->_configService = $_configService;
        $this->_httpService = $_httpService;
        $this->_jsonService = $_jsonService;
        $this->_securityService = $_securityService;
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

        $buildNumber = $this->_getBuildVersion($forceReload);
        if ($buildNumber < self::VHS_MIN_BUILD_TO_SUPPORT_TYPES_API) {
            return new TypesModel(
                [
                    'pdf' => 'PDF',
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

                $availableTypes[] = [
                    'name' => $templateType['name'],
                    'renderer' => $templateType['renderer'],
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
            if ($availableType === 'pdf') {
                $availableKinds['pdf'] = 'PDF';
            }

            if ($availableType === 'email') {
                $availableKinds['email'] = 'E-Mail';
            }

            if ($availableType === 'snippet') {
                $availableKinds['snippet'] = 'Snippet';
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
            return 'pdf';
        }

        if (self::TEMPLATE_TYPE_EMAIL === $templateCategory) {
            return 'email';
        }

        if (self::TEMPLATE_TYPE_SNIPPET === $templateCategory) {
            return 'snippet';
        }

        throw new Exception(sprintf('unknown template category "%s"', $templateCategory));
    }

    /**
     * @param bool $forceReload
     * @return int
     * @throws InvalidArgumentException
     * @throws Exception
     */
    private function _getBuildVersion(bool $forceReload): int
    {
        $url = $this->_configService->getRestEndpoint();
        $vhsBuildNumberCacheKey = $this->_cacheService->getVhsBuildNumberCacheKey($url);
        if ($this->_cacheService->has($vhsBuildNumberCacheKey) && !$forceReload) {
            return $this->_cacheService->get($vhsBuildNumberCacheKey)->get();
        }

        $releaseInfosEndpointUrl = $this->_configService->getVhsReleaseVersionEndpointUrl();
        $jwt = $this->_securityService->getJwt();
        $response = $this->_httpService->get($releaseInfosEndpointUrl, $jwt);
        $response = $this->_jsonService->parseJson($response);

        if ($response['httpCode'] !== Response::HTTP_OK) {
            $buildNumber = self::VHS_MIN_BUILD_TO_SUPPORT_TYPES_API - 1;
        } else {
            $buildNumber = (int)$response['response']['build'];
        }

        $this->_cacheService->set($vhsBuildNumberCacheKey, $buildNumber);

        return $buildNumber;
    }

    /**
     * @return string[]
     */
    private function _getStaticPdfTypes(): array
    {
        return [
            'pdf' => [
                [
                    'name' => 'Rechnung',
                    'renderer' => 'Invoice',
                ],
                [
                    'name' => 'Rechnung (Email-Anhang)',
                    'renderer' => 'Invoice',
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
