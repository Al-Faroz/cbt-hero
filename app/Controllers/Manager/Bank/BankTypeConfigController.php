<?php

namespace App\Controllers\Manager\Bank;

use App\Controllers\BaseController;

class BankTypeConfigController extends BaseController
{
    public function index(string $id)
    {
        return view('manager/bank/type-config', [
            'auth' => session()->get('manager_auth'), 'pageTitle' => 'Komposisi Bank Soal',
            'pageSubtitle' => 'Master Ujian', 'activeMenu' => 'exam-bank', 'bankId' => (int) $id,
        ]);
    }
}
