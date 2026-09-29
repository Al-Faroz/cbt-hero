<?php

namespace App\Controllers\Manager\Scoring;

use App\Controllers\BaseController;

class ScoringController extends BaseController
{
    public function index()
    {
        return view('manager/scoring/index', [
            'auth' => session()->get('manager_auth'),
            'pageTitle' => 'Penilaian Akademik',
            'pageSubtitle' => 'Pelaksanaan Ujian',
            'activeMenu' => 'execution-scoring',
        ]);
    }
}
