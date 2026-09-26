<?php

namespace App\Controllers\Manager\Master;

use App\Controllers\BaseController;

class RombelController extends BaseController
{
    public function index()
    {
        return view('manager/master/rombel/index', [
            'auth' => session()->get('manager_auth'),
            'pageTitle' => 'Rombel',
            'pageSubtitle' => 'Master Data',
            'activeMenu' => 'master-rombel',
        ]);
    }
}
