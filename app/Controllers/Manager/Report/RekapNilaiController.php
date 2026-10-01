<?php

namespace App\Controllers\Manager\Report;

use App\Controllers\BaseController;

class RekapNilaiController extends BaseController
{
    public function index()
    {
        return view('manager/report/rekap_nilai', [
            'auth' => session()->get('manager_auth'),
            'pageTitle' => 'Rekap Nilai',
            'pageSubtitle' => 'Hasil & Laporan',
            'activeMenu' => 'report-rekap',
        ]);
    }
}
