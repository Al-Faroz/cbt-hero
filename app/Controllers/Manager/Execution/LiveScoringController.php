<?php

namespace App\Controllers\Manager\Execution;

use App\Controllers\BaseController;

class LiveScoringController extends BaseController
{
    public function index()
    {
        return view('manager/execution/live_scoring', [
            'auth' => session()->get('manager_auth'),
            'pageTitle' => 'Live Scoring',
            'pageSubtitle' => 'Pelaksanaan Ujian',
            'activeMenu' => 'execution-live-scoring',
        ]);
    }
}
