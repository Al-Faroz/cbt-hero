<?php

namespace App\Controllers\Manager\Master;

use App\Controllers\BaseController;

class PesertaController extends BaseController
{
    public function index()
    {
        return view('manager/master/peserta/index', [
            'auth' => session()->get('manager_auth'),
            'pageTitle' => 'Peserta',
            'pageSubtitle' => 'Master Data',
            'activeMenu' => 'master-peserta',
        ]);
    }

    public function import()
    {
        return view('manager/master/peserta/import', [
            'auth' => session()->get('manager_auth'),
            'pageTitle' => 'Import Peserta',
            'pageSubtitle' => 'Master Data',
            'activeMenu' => 'master-peserta',
        ]);
    }
}
