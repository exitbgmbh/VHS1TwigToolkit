<?php

use Symfony\Component\Routing;

$routes = new Routing\RouteCollection();
$routes->add(
    'index',
    new Routing\Route(
        '/',
        [
            # container is defined and initialized in index.php, respectively container.php
            '_controller' => [ $container->get('app_controller'), 'index' ],
        ]
    )
);

$routes->add(
    'generate',
    new Routing\Route(
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

return $routes;
