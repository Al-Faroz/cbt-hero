<?php

namespace App\Controllers\Manager\Bank;

use App\Controllers\BaseController;

class ImportSoalController extends BaseController
{
    public function index(string $id)
    {
        return view('manager/bank/import-soal', [
            'auth' => session()->get('manager_auth'), 'pageTitle' => 'Impor Soal Akademik',
            'pageSubtitle' => 'Master Ujian', 'activeMenu' => 'exam-bank', 'bankId' => (int) $id,
        ]);
    }
}
