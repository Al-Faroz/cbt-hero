<?php

namespace App\Controllers\Manager\Bank;

use App\Controllers\BaseController;

class SoalController extends BaseController
{
    public function index(string $id)
    {
        return view('manager/bank/soal', [
            'auth' => session()->get('manager_auth'), 'pageTitle' => 'Daftar Soal',
            'pageSubtitle' => 'Master Ujian', 'activeMenu' => 'exam-bank', 'bankId' => (int) $id,
        ]);
    }

    public function advanced(string $id)
    {
        return redirect()->to(base_url('manager/master-ujian/bank-soal/' . (int) $id . '/soal'));
    }
}
