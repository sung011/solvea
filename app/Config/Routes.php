<?php

use CodeIgniter\Router\RouteCollection;

/** @var RouteCollection $routes */
$routes->get('/', 'Home::index');
$routes->get('results', 'Home::results');

$routes->group('api', static function (RouteCollection $routes) {
    $routes->get('recommend', 'Api::recommend');
    $routes->get('detail', 'Api::detail');
});
