<?php

namespace App\Controllers\Manager\Report;

use App\Controllers\BaseController;

class AnalisisSoalController extends BaseController
{
    public function index()
    {
        return view('manager/report/analisis_soal', [
            'auth' => session()->get('manager_auth'),
            'pageTitle' => 'Analisis Soal',
            'pageSubtitle' => 'Hasil & Laporan',
            'activeMenu' => 'report-analysis',
        ]);
    }
}
