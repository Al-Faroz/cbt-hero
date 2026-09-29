<?php

namespace App\Controllers\Manager\Execution;

use App\Controllers\BaseController;

class MonitoringController extends BaseController
{
    public function index()
    {
        return view('manager/execution/monitoring', [
            'auth' => session()->get('manager_auth'),
            'pageTitle' => 'Monitoring Ujian',
            'pageSubtitle' => 'Pelaksanaan Ujian',
            'activeMenu' => 'execution-monitoring',
        ]);
    }
}
