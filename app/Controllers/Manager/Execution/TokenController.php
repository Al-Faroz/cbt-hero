<?php

namespace App\Controllers\Manager\Execution;

use App\Controllers\BaseController;

class TokenController extends BaseController
{
    public function index()
    {
        return view('manager/execution/token', [
            'auth' => session()->get('manager_auth'),
            'pageTitle' => 'Token Ujian',
            'pageSubtitle' => 'Pelaksanaan Ujian',
            'activeMenu' => 'execution-token',
        ]);
    }
}
