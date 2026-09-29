<?php

namespace App\Controllers\Manager\Report;

use App\Controllers\BaseController;

class HasilUjianController extends BaseController
{
    public function index()
    {
        return view('manager/report/hasil_ujian', [
            'auth' => session()->get('manager_auth'),
            'pageTitle' => 'Hasil Ujian',
            'pageSubtitle' => 'Hasil & Laporan',
            'activeMenu' => 'report-results',
        ]);
    }
}
