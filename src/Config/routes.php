<?php

use Symfony\Component\Routing\RouteCollection;
use Symfony\Component\Routing\Route;

$routes = new RouteCollection();
$routes->add(
    'index',
    new Route(
        '/',
        [
            # container is defined and initialized in index.php, respectively container.php
            '_controller' => [ $container->get('app_controller'), 'index' ],
        ]
    )
);

$routes->add(
    'generate',
    new Route(
        '/{kind}/{type}/{template}/{identifiers}/{advertisingMediumCode}',
        [
            # container is defined and initialized in index.php, respectively container.php
            '_controller' => [ $container->get('app_controller'), 'generate' ],
            'advertisingMediumCode' => '',
        ],
        [
            'kind' => 'email|pdf',
        ],
    )
);

$routes->add(
    'has_context',
    new Route(
        '/api/v1/cache/hasContext',
        [
            # container is defined and initialized in index.php, respectively container.php
            '_controller' => [ $container->get('api_controller'), 'hasContext' ],
        ]
    )
);

$routes->add(
    'get_context',
    new Route(
        '/api/v1/cache/getContext',
        [
            # container is defined and initialized in index.php, respectively container.php
            '_controller' => [ $container->get('api_controller'), 'getContext' ],
        ]
    )
);

$routes->add(
    'has_textModules',
    new Route(
        '/api/v1/cache/hasTextModules',
        [
            # container is defined and initialized in index.php, respectively container.php
            '_controller' => [ $container->get('api_controller'), 'hasTextModules' ],
        ]
    )
);

$routes->add(
    'get_textModules',
    new Route(
        '/api/v1/cache/getTextModules',
        [
            # container is defined and initialized in index.php, respectively container.php
            '_controller' => [ $container->get('api_controller'), 'getTextModules' ],
        ]
    )
);

return $routes;
