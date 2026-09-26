<?php

use CodeIgniter\Router\RouteCollection;

/** @var RouteCollection $routes */

/*
|--------------------------------------------------------------------------
| PARTICIPANT REALM
|--------------------------------------------------------------------------
*/
$routes->get('/', 'Participant\\ParticipantAuthController::index');

$routes->group(
    '',
    ['filter' => 'participant-auth'],
    static function (RouteCollection $routes): void {
        $routes->get(
            'ujian',
            'Participant\\ParticipantExamController::index'
        );

        $routes->get(
            'ujian/(:num)/konfirmasi',
            'Participant\\ParticipantExamController::confirmation/$1'
        );
    }
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
| PARTICIPANT EXAM DISCOVERY API
|--------------------------------------------------------------------------
*/
$routes->group(
    'api/ujian',
    ['filter' => 'participant-auth:api'],
    static function (RouteCollection $routes): void {
        $routes->get(
            '',
            'Api\\Participant\\ExamDiscoveryController::index'
        );

        $routes->get(
            '(:num)/konfirmasi',
            'Api\\Participant\\ExamDiscoveryController::confirmation/$1'
        );
    }
);

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
            'master-data/rombel',
            'Manager\\Master\\RombelController::index',
            ['filter' => 'manager-role:master.data.manage']
        );

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
        $rombel = 'Api\\Manager\\Master\\RombelController::';
        $filter = ['filter' => 'manager-role:master.data.manage,api'];
        $routes->get('rombel', $rombel . 'index', $filter);
        $routes->post('rombel', $rombel . 'create', $filter);
        $routes->get('rombel/(:num)', $rombel . 'show/$1', $filter);
        $routes->put('rombel/(:num)', $rombel . 'update/$1', $filter);
        $routes->patch('rombel/(:num)/status', $rombel . 'status/$1', $filter);
        $routes->delete('rombel/(:num)', $rombel . 'remove/$1', $filter);

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
