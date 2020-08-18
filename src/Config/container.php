<?php

use App\Controller\AppController;
use App\Service\ConfigService;
use App\Service\HttpService;
use App\Service\JsonService;
use App\Service\MapperService;
use App\Service\PdfService;
use App\Service\TwigService;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Reference;

$containerBuilder = new ContainerBuilder();

$containerBuilder->register('json_service', JsonService::class);
$containerBuilder->register('http_service', HttpService::class);
$containerBuilder->register('mapper_service', MapperService::class);
$containerBuilder->register('pdf_service', PdfService::class);

$containerBuilder->register('config_service', ConfigService::class)
    ->addArgument(new Reference('json_service'));

$containerBuilder->register('twig_service', TwigService::class)
    ->addArgument(new Reference('config_service'));

$containerBuilder->register('app_controller', AppController::class)
    ->setPublic(true)
    ->setArguments([
       new Reference('config_service'),
       new Reference('http_service'),
       new Reference('json_service'),
       new Reference('mapper_service'),
       new Reference('pdf_service'),
       new Reference('twig_service'),
    ]);

$containerBuilder->compile();

return $containerBuilder;
