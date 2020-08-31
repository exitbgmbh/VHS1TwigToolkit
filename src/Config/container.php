<?php

use App\Controller\AppController;
use App\Factory\FrontendFactory;
use App\Service\CacheService;
use App\Service\ConfigService;
use App\Service\ContextService;
use App\Service\HttpService;
use App\Service\JsonService;
use App\Service\MapperService;
use App\Service\PdfService;
use App\Service\SecurityService;
use App\Service\TextModulesService;
use App\Service\TwigService;
use App\Service\TypesService;
use App\Service\ValidatorService;
use Symfony\Component\Cache\Adapter\FilesystemAdapter;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Reference;

$containerBuilder = new ContainerBuilder();

$containerBuilder->register('cache_adapter', FilesystemAdapter::class);

$containerBuilder->register('cache_service', CacheService::class)
    ->addArgument(new Reference('cache_adapter'));

$containerBuilder->register('http_service', HttpService::class);
$containerBuilder->register('json_service', JsonService::class);
$containerBuilder->register('mapper_service', MapperService::class);
$containerBuilder->register('pdf_service', PdfService::class);
$containerBuilder->register('validator_service', ValidatorService::class);

$containerBuilder->register('config_service', ConfigService::class)
    ->addArgument(new Reference('json_service'));

$containerBuilder->register('context_service', ContextService::class)
    ->setArguments([
        new Reference('cache_service'),
        new Reference('config_service'),
        new Reference('http_service'),
        new Reference('json_service'),
        new Reference('mapper_service'),
    ]);

$containerBuilder->register('frontend_factory', FrontendFactory::class)
    ->setArguments([
        new Reference('http_service'),
        new Reference('types_service'),
        new Reference('validator_service'),
    ]);

$containerBuilder->register('twig_service', TwigService::class)
    ->addArgument(new Reference('config_service'));

$containerBuilder->register('security_service', SecurityService::class)
    ->setArguments([
        new Reference('cache_service'),
        new Reference('config_service'),
        new Reference('http_service'),
        new Reference('json_service'),
    ]);

$containerBuilder->register('types_service', TypesService::class)
    ->setArguments([
        new Reference('cache_service'),
        new Reference('config_service'),
        new Reference('http_service'),
        new Reference('json_service'),
        new Reference('security_service'),
    ]);

$containerBuilder->register('text_modules_service', TextModulesService::class)
    ->setArguments([
        new Reference('cache_service'),
        new Reference('config_service'),
        new Reference('http_service'),
        new Reference('json_service'),
    ]);

$containerBuilder->register('app_controller', AppController::class)
    ->setPublic(true)
    ->setArguments([
        new Reference('context_service'),
        new Reference('frontend_factory'),
        new Reference('pdf_service'),
        new Reference('security_service'),
        new Reference('text_modules_service'),
        new Reference('twig_service')
    ]);

$containerBuilder->compile();

return $containerBuilder;
