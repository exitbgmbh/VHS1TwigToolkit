<?php

namespace App\Controller;

use App\Service\CacheService;
use App\Service\TypesService;
use Psr\Cache\InvalidArgumentException;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;

class ApiController
{
    /** @var CacheService */
    private $_cacheService;

    /** @var TypesService */
    private $_typeService;

    /**
     * @param CacheService $cacheService
     * @param TypesService $typesService
     */
    public function __construct(CacheService $cacheService, TypesService $typesService)
    {
        $this->_cacheService = $cacheService;
        $this->_typeService = $typesService;
    }

    /**
     * @param Request $request
     * @return JsonResponse
     */
    public function hasContext(Request $request): JsonResponse
    {
        $contextCacheKey = $this->_cacheService->getContextCacheKey(
            $request->query->get('kind', ''),
            $this->_typeService->getRealType($request->query->get('type', '')),
            $request->query->get('identifiers', '')
        );

        return new JsonResponse([
            'hasContext' => $this->_cacheService->has($contextCacheKey),
        ]);
    }

    /**
     * @param Request $request
     * @return JsonResponse
     * @throws InvalidArgumentException
     */
    public function getContext(Request $request): JsonResponse
    {
        $contextCacheKey = $this->_cacheService->getContextCacheKey(
            $request->query->get('kind', ''),
            $this->_typeService->getRealType($request->query->get('type', '')),
            $request->query->get('identifiers', '')
        );

        $context = [];
        if ($this->_cacheService->has($contextCacheKey)) {
            $context = $this->_cacheService->get($contextCacheKey)->get();
        }

        return new JsonResponse($context);
    }

    /**
     * @param Request $request
     * @return JsonResponse
     */
    public function hasTextModules(Request $request): JsonResponse
    {
        $textModulesContextKey = $this->_cacheService->getTextModulesCacheKey(
            $request->query->get('advertisingMediumCode',''),
            $request->query->get('language', '')
        );

        return new JsonResponse([
            'hasTextModules' => $this->_cacheService->has($textModulesContextKey),
        ]);
    }

    /**
     * @param Request $request
     * @return JsonResponse
     * @throws InvalidArgumentException
     */
    public function getTextModules(Request $request): JsonResponse
    {
        $textModulesContextKey = $this->_cacheService->getTextModulesCacheKey(
            $request->query->get('advertisingMediumCode',''),
            $request->query->get('language', '')
        );

        $textModules = [];
        if ($this->_cacheService->has($textModulesContextKey)) {
            $textModules = $this->_cacheService->get($textModulesContextKey)->get();
        }

        return new JsonResponse($textModules);
    }
}
