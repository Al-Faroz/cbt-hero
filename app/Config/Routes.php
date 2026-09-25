<?php

use CodeIgniter\Router\RouteCollection;

/** @var RouteCollection $routes */

/*
|--------------------------------------------------------------------------
| PARTICIPANT ROOT (sementara)
|--------------------------------------------------------------------------
| Landing peserta belum dikerjakan pada Phase 1A.
*/
$routes->get('/', 'Home::index');

/*
|--------------------------------------------------------------------------
| MANAGER REALM
|--------------------------------------------------------------------------
*/
$routes->get('manager', 'Manager\\ManagerAuthController::index');

$routes->group('manager/api/auth', static function (RouteCollection $routes): void {
    $routes->post('login', 'Manager\\ManagerAuthController::login');

    $routes->post(
        'logout',
        'Manager\\ManagerAuthController::logout',
        ['filter' => 'manager-auth:api']
    );

    $routes->get(
        'session',
        'Manager\\ManagerAuthController::sessionInfo',
        ['filter' => 'manager-auth:api']
    );
});

$routes->group(
    'manager',
    ['filter' => 'manager-auth'],
    static function (RouteCollection $routes): void {
        $routes->get('dashboard', 'Manager\\DashboardController::index');
    }
);
