<?php

namespace App\Controllers\Manager\Master;

use App\Controllers\BaseController;

class MataPelajaranController extends BaseController
{
    public function index()
    {
        return view('manager/master/mapel/index', [
            'auth' => session()->get('manager_auth'),
            'pageTitle' => 'Mata Pelajaran',
            'pageSubtitle' => 'Master Data',
            'activeMenu' => 'master-mapel',
        ]);
    }
}
