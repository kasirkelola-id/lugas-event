<?php

use CodeIgniter\Router\RouteCollection;

/** @var RouteCollection $routes */

$routes->group('api', function ($routes) {
    $routes->post('login', 'Api\AuthController::login');
    $routes->post('register', 'Api\AuthController::register');
    $routes->post('logout', 'Api\AuthController::logout', ['filter' => 'auth']);
    $routes->get('me', 'Api\AuthController::me', ['filter' => 'auth']);
    
    // Profile Management
    $routes->put('profile', 'Api\ProfileController::updateProfile', ['filter' => 'auth']);
    $routes->patch('profile/password', 'Api\ProfileController::changePassword', ['filter' => 'auth']);

    // Announcements
    $routes->get('announcements', 'Api\AnnouncementController::index', ['filter' => 'auth']);
    $routes->post('announcements', 'Api\AnnouncementController::create', ['filter' => 'auth']);
    $routes->put('announcements/(:num)', 'Api\AnnouncementController::update/$1', ['filter' => 'auth']);
    $routes->patch('announcements/(:num)/status', 'Api\AnnouncementController::toggleStatus/$1', ['filter' => 'auth']);
    $routes->delete('announcements/(:num)', 'Api\AnnouncementController::delete/$1', ['filter' => 'auth']);
    
    // Event Management
    $routes->get('events', 'Api\EventController::index', ['filter' => 'auth']);
    $routes->post('events', 'Api\EventController::create', ['filter' => 'auth']);
    $routes->get('events/(:num)', 'Api\EventController::show/$1', ['filter' => 'auth']);
    $routes->put('events/(:num)', 'Api\EventController::update/$1', ['filter' => 'auth']);
    $routes->patch('events/(:num)/status', 'Api\EventController::close/$1', ['filter' => 'auth']);
    
    // Participants
    $routes->get('events/(:num)/participants', 'Api\ParticipantController::getByEvent/$1', ['filter' => 'auth']);
    $routes->post('events/(:num)/participants', 'Api\ParticipantController::add/$1', ['filter' => 'auth']);
    $routes->delete('events/(:num)/participants/(:num)', 'Api\ParticipantController::remove/$1/$2', ['filter' => 'auth']);
    
    // Reports
    $routes->get('reports/summary', 'Api\ReportController::summary', ['filter' => 'auth']);
    
    // Attendance
    $routes->post('absensi', 'Api\AbsensiController::create', ['filter' => 'auth']);
    $routes->get('absensi/my', 'Api\AbsensiController::myHistory', ['filter' => 'auth']);
    $routes->get('events/(:num)/absensi', 'Api\AbsensiController::eventAttendees/$1', ['filter' => 'auth']);
    
    // User Management (Admin Only)
    $routes->get('users/roles-summary', 'Api\UserController::rolesSummary', ['filter' => 'auth']);
    $routes->get('users', 'Api\UserController::index', ['filter' => 'auth']);
    $routes->post('users', 'Api\UserController::create', ['filter' => 'auth']);
    $routes->put('users/(:num)', 'Api\UserController::update/$1', ['filter' => 'auth']);
    $routes->patch('users/(:num)/status', 'Api\UserController::toggleStatus/$1', ['filter' => 'auth']);
    $routes->patch('users/(:num)/role', 'Api\UserController::changeRole/$1', ['filter' => 'auth']);
    $routes->patch('users/(:num)/reset-password', 'Api\UserController::resetPassword/$1', ['filter' => 'auth']);

    // Temporary Migration Endpoints
    $routes->get('system/migrate/status', 'Api\MigrateController::status');
    $routes->get('system/migrate/run', 'Api\MigrateController::run');
});

/** @var RouteCollection $routes */
$routes->get('/', 'Home::index');
