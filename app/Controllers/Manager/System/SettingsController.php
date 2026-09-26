<?php

namespace App\Controllers\Manager\System;

use App\Controllers\BaseController;

class SettingsController extends BaseController
{
    public function index()
    {
        return view('manager/system/settings/index', [
            'auth' => session()->get('manager_auth'),
            'pageTitle' => 'Pengaturan',
            'pageSubtitle' => 'Default akademik',
            'activeMenu' => 'system-settings',
        ]);
    }
}
