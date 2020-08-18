<?php

use Symfony\Component\Routing;

$routes = new Routing\RouteCollection();
$routes->add(
    'template_generation',
    new Routing\Route(
        '/{type}/{template}/{identifiers}/{advertisingMediumCode}',
        [
            # container is defined and initialized in index.php, respectively container.php
            '_controller' => [ $container->get('app_controller'), 'generate' ],
            'advertisingMediumCode' => '',
        ]
    )
);

return $routes;
