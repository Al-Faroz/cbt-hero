<?php

use CodeIgniter\Router\RouteCollection;

/** @var RouteCollection $routes */

/*
|--------------------------------------------------------------------------
| PARTICIPANT ROOT (sementara)
|--------------------------------------------------------------------------
| Landing peserta belum dikerjakan pada Phase 1.
*/
$routes->get('/', 'Home::index');

/*
|--------------------------------------------------------------------------
| MANAGER REALM
|--------------------------------------------------------------------------
*/
$routes->get('manager', 'Manager\\ManagerAuthController::index');

/*
|--------------------------------------------------------------------------
| MANAGER AUTH API
|--------------------------------------------------------------------------
*/
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

/*
|--------------------------------------------------------------------------
| MANAGER PROTECTED UI
|--------------------------------------------------------------------------
*/
$routes->group(
    'manager',
    ['filter' => 'manager-auth'],
    static function (RouteCollection $routes): void {
        $routes->get('dashboard', 'Manager\\DashboardController::index');

        /*
         * Final UI route sudah ditetapkan di Dokumen Routes/API.
         * User Manager adalah Admin-only.
         */
        $routes->get(
            'system/users',
            'Manager\\System\\ManagerUserController::index',
            ['filter' => 'manager-role:system.users.manage']
        );
    }
);

/*
|--------------------------------------------------------------------------
| MANAGER PROTECTED API
|--------------------------------------------------------------------------
*/
$routes->group(
    'manager/api',
    ['filter' => 'manager-auth:api'],
    static function (RouteCollection $routes): void {
        /*
         * GET/POST /manager/api/users adalah bagian kontrak final Routes/API.
         * Endpoint lain (status/reset-password/update) dikerjakan pada modul
         * System tanpa mengubah permission boundary ini.
         */
        $routes->get(
            'users',
            'Manager\\System\\ManagerUserController::list',
            ['filter' => 'manager-role:system.users.manage,api']
        );

        $routes->post(
            'users',
            'Manager\\System\\ManagerUserController::create',
            ['filter' => 'manager-role:system.users.manage,api']
        );
    }
);
