<?php

namespace App\Controllers\Manager\Bank;

use App\Controllers\BaseController;

class SoalController extends BaseController
{
    public function index(string $id)
    {
        return view('manager/bank/soal', [
            'auth' => session()->get('manager_auth'), 'pageTitle' => 'Soal Pilihan Ganda',
            'pageSubtitle' => 'Master Ujian', 'activeMenu' => 'exam-bank', 'bankId' => (int) $id,
        ]);
    }
}
