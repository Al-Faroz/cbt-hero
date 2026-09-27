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
            'master-data/peserta',
            'Manager\\Master\\PesertaController::index',
            ['filter' => 'manager-role:master.data.manage']
        );
        $routes->get('master-data/peserta/import', 'Manager\\Master\\PesertaController::import',
            ['filter' => 'manager-role:master.data.manage']);
        $routes->get('master-data/mapel', 'Manager\\Master\\MataPelajaranController::index',
            ['filter' => 'manager-role:master.data.manage']);
        $routes->get('master-ujian/kegiatan', 'Manager\\Exam\\KegiatanController::index',
            ['filter' => 'manager-role:master.exam.manage']);
        $routes->get('master-ujian/kegiatan/(:num)/peserta', 'Manager\\Exam\\PesertaUjianController::index/$1',
            ['filter' => 'manager-role:master.exam.manage']);
        $routes->get('master-ujian/kegiatan/(:num)/kartu', 'Manager\\Exam\\KartuUjianController::index/$1',
            ['filter' => 'manager-role:master.exam.manage']);
        $routes->get('master-ujian/kegiatan/(:num)/preflight', 'Manager\\Exam\\KegiatanPreflightController::index/$1',
            ['filter' => 'manager-role:master.exam.manage']);
        $routes->get('master-ujian/ruang', 'Manager\\Exam\\RuangController::index',
            ['filter' => 'manager-role:master.exam.manage']);
        $routes->get('master-ujian/bank-soal', 'Manager\\Bank\\BankSoalController::index',
            ['filter' => 'manager-role:master.exam.manage']);
        $routes->get('import-template/peserta.xlsx', 'Api\\Manager\\Master\\PesertaImportController::template',
            ['filter' => 'manager-role:master.data.manage']);

        $routes->get(
            'system/users',
            'Manager\\System\\ManagerUserController::index',
            ['filter' => 'manager-role:system.users.manage']
        );
        $routes->get('system/settings', 'Manager\\System\\SettingsController::index',
            ['filter' => 'manager-role:system.settings.manage']);
        $routes->get('system/card-logo', 'Manager\\System\\CardLogoController::show');
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

        $peserta = 'Api\\Manager\\Master\\PesertaController::';
        $routes->get('peserta', $peserta . 'index', $filter);
        $routes->post('peserta', $peserta . 'create', $filter);
        $routes->get('peserta/rombel-options', $peserta . 'rombelOptions', $filter);
        $routes->get('peserta/(:num)', $peserta . 'show/$1', $filter);
        $routes->put('peserta/(:num)', $peserta . 'update/$1', $filter);
        $routes->patch('peserta/(:num)/status', $peserta . 'status/$1', $filter);
        $account = 'Api\\Manager\\Master\\ParticipantAccountController::';
        $routes->get('peserta/(:num)/account', $account . 'show/$1', $filter);
        $routes->get('peserta/(:num)/account/printable', $account . 'printable/$1', $filter);
        $routes->post('peserta/(:num)/account/generate-username', $account . 'generateUsername/$1', $filter);
        $routes->put('peserta/(:num)/account/username', $account . 'setUsername/$1', $filter);
        $routes->post('peserta/(:num)/account/reset-password', $account . 'resetPassword/$1', $filter);
        $routes->post('peserta/accounts/generate-usernames', $account . 'bulkUsername', $filter);
        $routes->post('peserta/accounts/regenerate-usernames', $account . 'bulkRegenerateUsername', $filter);
        $routes->post('peserta/accounts/reset-passwords', $account . 'bulkResetPassword', $filter);
        $import = 'Api\\Manager\\Master\\PesertaImportController::';
        $routes->post('imports', $import . 'upload', $filter);
        $routes->get('imports/(:num)', $import . 'show/$1', $filter);
        $routes->get('imports/(:num)/items', $import . 'items/$1', $filter);
        $routes->post('imports/(:num)/parse', $import . 'parse/$1', $filter);
        $routes->post('imports/(:num)/validate', $import . 'validateJob/$1', $filter);
        $routes->patch('imports/(:num)/items/(:num)', $import . 'fix/$1/$2', $filter);
        $routes->post('imports/(:num)/items/(:num)/exclude', $import . 'exclude/$1/$2', $filter);
        $routes->post('imports/(:num)/items/(:num)/include', $import . 'includeItem/$1/$2', $filter);
        $routes->post('imports/(:num)/commit', $import . 'commit/$1', $filter);

        $mapel = 'Api\\Manager\\Master\\MataPelajaranController::';
        $routes->get('mapel', $mapel . 'index', $filter);
        $routes->post('mapel', $mapel . 'create', $filter);
        $routes->get('mapel/(:num)', $mapel . 'show/$1', $filter);
        $routes->put('mapel/(:num)', $mapel . 'update/$1', $filter);
        $routes->patch('mapel/(:num)/status', $mapel . 'status/$1', $filter);
        $routes->delete('mapel/(:num)', $mapel . 'remove/$1', $filter);

        $kegiatan = 'Api\\Manager\\Exam\\KegiatanController::';
        $examFilter = ['filter' => 'manager-role:master.exam.manage,api'];
        $routes->get('kegiatan', $kegiatan . 'index', $examFilter);
        $routes->post('kegiatan', $kegiatan . 'create', $examFilter);
        $routes->get('kegiatan/defaults', $kegiatan . 'defaults', $examFilter);
        $routes->get('kegiatan/(:num)', $kegiatan . 'show/$1', $examFilter);
        $routes->put('kegiatan/(:num)', $kegiatan . 'update/$1', $examFilter);
        $routes->delete('kegiatan/(:num)', $kegiatan . 'remove/$1', $examFilter);
        $pesertaUjian = 'Api\\Manager\\Exam\\PesertaUjianController::';
        $routes->get('kegiatan/(:num)/peserta', $pesertaUjian . 'index/$1', $examFilter);
        $routes->get('kegiatan/(:num)/peserta/candidates', $pesertaUjian . 'candidates/$1', $examFilter);
        $routes->post('kegiatan/(:num)/peserta', $pesertaUjian . 'assign/$1', $examFilter);
        $routes->post('kegiatan/(:num)/peserta/bulk-remove', $pesertaUjian . 'bulkRemove/$1', $examFilter);
        $routes->delete('kegiatan/(:num)/peserta/(:num)', $pesertaUjian . 'remove/$1/$2', $examFilter);
        $ruang = 'Api\\Manager\\Exam\\RuangController::';
        $routes->get('ruang', $ruang . 'index', $examFilter);
        $routes->get('ruang/options', $ruang . 'options', $examFilter);
        $routes->post('ruang', $ruang . 'create', $examFilter);
        $routes->put('ruang/(:num)', $ruang . 'update/$1', $examFilter);
        $routes->patch('ruang/(:num)/status', $ruang . 'status/$1', $examFilter);
        $routes->delete('ruang/(:num)', $ruang . 'remove/$1', $examFilter);
        $routes->post('kegiatan/(:num)/peserta/assign-ruang', $ruang . 'assign/$1', $examFilter);
        $routes->patch('kegiatan/(:num)/peserta/(:num)/ruang', $ruang . 'assignIndividual/$1/$2', $examFilter);
        $routes->post('kegiatan/(:num)/nomor-peserta/generate',
            'Api\\Manager\\Exam\\NomorPesertaController::generate/$1', $examFilter);
        $bank = 'Api\\Manager\\Bank\\BankSoalController::';
        $routes->get('bank-soal', $bank . 'index', $examFilter);
        $routes->get('bank-soal/options', $bank . 'options', $examFilter);
        $routes->post('bank-soal', $bank . 'create', $examFilter);
        $routes->get('bank-soal/(:num)', $bank . 'show/$1', $examFilter);
        $routes->put('bank-soal/(:num)', $bank . 'update/$1', $examFilter);
        $routes->delete('bank-soal/(:num)', $bank . 'remove/$1', $examFilter);

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
        $settings = 'Api\\Manager\\System\\SettingsController::';
        $settingsFilter = ['filter' => 'manager-role:system.settings.manage,api'];
        $routes->get('settings/academic', $settings . 'index', $settingsFilter);
        $routes->put('settings/academic', $settings . 'update', $settingsFilter);
        $cardIdentity = 'Api\\Manager\\System\\CardIdentityController::';
        $routes->get('settings/card-identity', $cardIdentity . 'index', $settingsFilter);
        $routes->post('settings/card-identity', $cardIdentity . 'update', $settingsFilter);
    }
);
