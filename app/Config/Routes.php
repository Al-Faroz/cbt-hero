<?php

use CodeIgniter\Router\RouteCollection;

/** @var RouteCollection $routes */

/*
|--------------------------------------------------------------------------
| PARTICIPANT REALM
|--------------------------------------------------------------------------
|
| GET / bersifat optional-auth:
| - belum login  -> Landing/Login Peserta
| - sudah login  -> /ujian
|
*/
$routes->get('/', 'Participant\\ParticipantAuthController::index');

$routes->get(
    'ujian',
    'Participant\\ParticipantExamController::index',
    ['filter' => 'participant-auth']
);

/*
|--------------------------------------------------------------------------
| PARTICIPANT AUTH API
|--------------------------------------------------------------------------
*/
$routes->group('api/auth', static function (RouteCollection $routes): void {
    $routes->post(
        'login',
        'Participant\\ParticipantAuthController::login'
    );

    $routes->post(
        'logout',
        'Participant\\ParticipantAuthController::logout',
        ['filter' => 'participant-auth:api']
    );

    $routes->get(
        'session',
        'Participant\\ParticipantAuthController::sessionInfo',
        ['filter' => 'participant-auth:api']
    );
});

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
