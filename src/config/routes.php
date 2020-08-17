<?php

use Symfony\Component\Routing;

require_once __DIR__ . '/../controller/AppController.php';

$routes = new Routing\RouteCollection();
$routes->add(
    'template_generation',
    new Routing\Route(
        '/{type}/{template}/{identifiers}/{advertisingMediumCode}',
        [
            '_controller' => [ new AppController(), 'generate' ],
            'advertisingMediumCode' => '',
        ]
    )
);

return $routes;
