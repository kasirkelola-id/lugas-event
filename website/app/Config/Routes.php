<?php

use CodeIgniter\Router\RouteCollection;

/** @var RouteCollection $routes */

$routes->group('api', function ($routes) {
    $routes->post('login', 'Api\AuthController::login');
    $routes->post('logout', 'Api\AuthController::logout', ['filter' => 'auth']);
    $routes->get('me', 'Api\AuthController::me', ['filter' => 'auth']);
    
    // Event Management
    $routes->get('events', 'Api\EventController::index', ['filter' => 'auth']);
    $routes->post('events', 'Api\EventController::create', ['filter' => 'auth']);
    $routes->get('events/(:num)', 'Api\EventController::show/$1', ['filter' => 'auth']);
    $routes->put('events/(:num)', 'Api\EventController::update/$1', ['filter' => 'auth']);
    $routes->patch('events/(:num)/status', 'Api\EventController::close/$1', ['filter' => 'auth']);
});

/** @var RouteCollection $routes */
$routes->get('/', 'Home::index');
