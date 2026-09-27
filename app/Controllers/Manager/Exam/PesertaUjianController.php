<?php

namespace App\Controllers\Manager\Exam;

use App\Controllers\BaseController;

class PesertaUjianController extends BaseController
{
    public function index(string $id)
    {
        return view('manager/exam/peserta/index', [
            'auth' => session()->get('manager_auth'),
            'pageTitle' => 'Peserta Ujian', 'pageSubtitle' => 'Master Ujian',
            'activeMenu' => 'exam-peserta', 'kegiatanId' => (int) $id,
        ]);
    }
}
