<?php

use CodeIgniter\Router\RouteCollection;

/** @var RouteCollection $routes */
$routes->get('/', 'Home::index');
$routes->get('results', 'Home::results');
$routes->get('favorites', 'Home::favorites');
$routes->get('login', 'Auth::login');
$routes->get('signup', 'Auth::signup');
$routes->get('logout', 'Auth::logout');
$routes->post('logout', 'Auth::logout');

$routes->group('api', static function (RouteCollection $routes) {
    $routes->get('recommend', 'Api::recommend');
    $routes->get('detail', 'Api::detail');
    $routes->get('subsidy24', 'Api::subsidy24');
    $routes->post('crawl/run', 'Api::crawlRun');
    $routes->get('crawl/status', 'Api::crawlStatus');
    $routes->get('favorites', 'Api::favorites');
    $routes->post('favorites', 'Api::saveFavorite');
    $routes->delete('favorites', 'Api::removeFavorite');
    $routes->post('auth/signup', 'Auth::signupApi');
    $routes->post('auth/login', 'Auth::loginApi');
    $routes->post('auth/logout', 'Auth::logout');
    $routes->get('auth/me', 'Auth::me');
});
