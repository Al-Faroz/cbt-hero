<?php

namespace App\Controllers\Manager;

use App\Controllers\BaseController;

class DashboardController extends BaseController
{
    public function index()
    {
        return view('manager/dashboard/index', [
            'auth' => session()->get('manager_auth'),
        ]);
    }
}
