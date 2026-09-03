<?php

use CodeIgniter\Router\RouteCollection;

/** @var RouteCollection $routes */

$routes->group('api', function ($routes) {
    $routes->post('tenant/verify-pin', 'Api\AuthController::validatePin', ['filter' => 'ratelimit:10,60']);
    $routes->post('login', 'Api\AuthController::login', ['filter' => 'ratelimit:5,60']);
    $routes->post('register', 'Api\AuthController::register');
    $routes->post('logout', 'Api\AuthController::logout', ['filter' => 'auth']);
    $routes->get('me', 'Api\AuthController::me', ['filter' => 'auth']);
    $routes->post('fcm-token', 'Api\AuthController::updateFcmToken', ['filter' => 'auth']);
    $routes->delete('fcm-token', 'Api\AuthController::removeFcmToken', ['filter' => 'auth']);
    $routes->get('memberships', 'Api\MembershipController::index', ['filter' => 'auth']);
    
    // Profile Management
    $routes->put('profile', 'Api\ProfileController::updateProfile', ['filter' => 'auth']);
    $routes->patch('profile/password', 'Api\ProfileController::changePassword', ['filter' => 'auth']);
    $routes->post('profile/password', 'Api\ProfileController::changePassword', ['filter' => 'auth']);
    $routes->post('profile/photo', 'Api\ProfileController::updateProfilePhoto', ['filter' => 'auth']);
    $routes->delete('profile/photo', 'Api\ProfileController::updateProfilePhoto', ['filter' => 'auth']);

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
    $routes->post('absensi/checkin', 'Api\AbsensiController::checkin', ['filter' => 'auth']);
    $routes->post('absensi/checkout', 'Api\AbsensiController::checkout', ['filter' => 'auth']);
    $routes->get('absensi/status', 'Api\AbsensiController::status', ['filter' => 'auth']);
    $routes->get('absensi/my', 'Api\AbsensiController::myHistory', ['filter' => 'auth']);
    $routes->get('events/(:num)/absensi', 'Api\AbsensiController::eventAttendees/$1', ['filter' => 'auth']);
    
    // Kas
    $routes->get('kas/summary', 'Api\KasController::summary', ['filter' => 'auth']);
    $routes->get('kas', 'Api\KasController::index', ['filter' => 'auth']);
    $routes->post('kas', 'Api\KasController::create', ['filter' => 'auth']);
    $routes->delete('kas/(:num)', 'Api\KasController::delete/$1', ['filter' => 'auth']);
    
    // Settings
    $routes->get('settings', 'Api\SettingController::index', ['filter' => 'auth']);
    $routes->post('settings', 'Api\SettingController::update', ['filter' => 'auth']);
    // Dashboard
    $routes->get('dashboard', 'Api\DashboardController::index', ['filter' => 'auth']);
    
    // Chat API
    $routes->get('chats/rooms', 'Api\ChatController::getRooms', ['filter' => 'auth']);
    $routes->post('chats/rooms', 'Api\ChatController::createRoom', ['filter' => 'auth']);
    $routes->delete('chats/rooms/(:num)', 'Api\ChatController::deleteRoom/$1', ['filter' => 'auth']);
    $routes->get('chats/rooms/(:num)/messages', 'Api\ChatController::getRoomChats/$1', ['filter' => 'auth']);
    $routes->post('chats/messages', 'Api\ChatController::sendMessage', ['filter' => 'auth']);
    $routes->get('chats/private/(:num)', 'Api\ChatController::getPrivateChats/$1', ['filter' => 'auth']);

    // Votings
    $routes->get('votings', 'Api\VotingController::index', ['filter' => 'auth']);
    $routes->post('votings', 'Api\VotingController::create', ['filter' => 'auth']);
    $routes->get('votings/(:num)', 'Api\VotingController::show/$1', ['filter' => 'auth']);
    $routes->post('votings/(:num)/vote', 'Api\VotingController::vote/$1', ['filter' => 'auth']);
    $routes->patch('votings/(:num)/status', 'Api\VotingController::changeStatus/$1', ['filter' => 'auth']);

    // Inventories
    $routes->get('inventories', 'Api\InventoryController::index', ['filter' => 'auth']);
    $routes->post('inventories', 'Api\InventoryController::create', ['filter' => 'auth']);
    $routes->get('inventories/loans', 'Api\InventoryController::getLoans', ['filter' => 'auth']);
    $routes->post('inventories/loans', 'Api\InventoryController::requestLoan', ['filter' => 'auth']);
    $routes->patch('inventories/loans/(:num)/status', 'Api\InventoryController::changeLoanStatus/$1', ['filter' => 'auth']);

    // User Management (Ketua Only)
    $routes->get('users/roles-summary', 'Api\UserController::rolesSummary', ['filter' => 'auth']);
    $routes->get('users', 'Api\UserController::index', ['filter' => 'auth']);
    $routes->post('users', 'Api\UserController::create', ['filter' => 'auth']);
    $routes->put('users/(:num)', 'Api\UserController::update/$1', ['filter' => 'auth']);
    $routes->patch('users/(:num)/status', 'Api\UserController::toggleStatus/$1', ['filter' => 'auth']);
    $routes->patch('users/(:num)/role', 'Api\UserController::changeRole/$1', ['filter' => 'auth']);
    $routes->post('users/(:num)/reset-password', 'Api\UserController::resetPassword/$1', ['filter' => 'auth']);

    // Temporary Migration Endpoints - DISABLED FOR PRODUCTION
    // $routes->get('system/migrate/status', 'Api\MigrateController::status');
    // $routes->get('system/migrate/run', 'Api\MigrateController::run');

    // Internal API
    $routes->post('internal/socket-auth', 'Api\InternalApiController::socketAuth', ['filter' => 'auth']);
    $routes->post('internal/chat-notification', 'Api\InternalApiController::chatNotification');
});

/** @var RouteCollection $routes */
$routes->get('/', '\App\Controllers\Superadmin\AuthController::login');

$routes->group('superadmin', ['namespace' => 'App\Controllers\Superadmin'], function ($routes) {
    $routes->get('login', 'AuthController::login');
    $routes->post('login', 'AuthController::processLogin');
    $routes->get('logout', 'AuthController::logout');
    
    $routes->group('', ['filter' => 'superadmin'], function ($routes) {
        $routes->get('dashboard', 'DashboardController::index');
        $routes->get('settings', 'SettingController::index');
        $routes->post('settings', 'SettingController::update');
        
        $routes->get('karang_taruna', 'KarangTarunaController::index');
        $routes->post('karang_taruna/create', 'KarangTarunaController::create');
        $routes->post('karang_taruna/update/(:num)', 'KarangTarunaController::update/$1');
        $routes->get('karang_taruna/delete/(:num)', 'KarangTarunaController::delete/$1');
        $routes->get('karang_taruna/(:num)/users', 'KarangTarunaController::users/$1');
        
        // Manage per Karang Taruna
        $routes->get('manage/(:num)', 'ManageController::dashboard/$1');
        $routes->get('manage/(:num)/users', 'ManageController::users/$1');
        $routes->post('manage/(:num)/users/(:num)/role', 'ManageController::updateUserRole/$1/$2');
        $routes->get('manage/(:num)/users/(:num)/status', 'ManageController::toggleUserStatus/$1/$2');
        $routes->get('manage/(:num)/events', 'ManageController::events/$1');
        $routes->get('manage/(:num)/pengumuman', 'ManageController::pengumuman/$1');
        $routes->post('manage/(:num)/pengumuman/create', 'ManageController::createPengumuman/$1');
        $routes->get('manage/(:num)/pengumuman/delete/(:num)', 'ManageController::deletePengumuman/$1/$2');
        
        $routes->get('manage/(:num)/kas', 'ManageController::kas/$1');
    });
});
