<?php

namespace App\Controllers\Manager\Bank;

use App\Controllers\BaseController;

class BankSoalController extends BaseController
{
    public function index()
    {
        return view('manager/bank/index', [
            'auth' => session()->get('manager_auth'), 'pageTitle' => 'Bank Soal',
            'pageSubtitle' => 'Master Ujian', 'activeMenu' => 'exam-bank',
        ]);
    }
}
