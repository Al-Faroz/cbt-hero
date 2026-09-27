<?php

namespace App\Controllers\Manager\Exam;

use App\Controllers\BaseController;

class RuangController extends BaseController
{
    public function index()
    {
        return view('manager/exam/ruang/index', [
            'auth' => session()->get('manager_auth'), 'pageTitle' => 'Ruang Ujian',
            'pageSubtitle' => 'Master Ujian', 'activeMenu' => 'exam-ruang',
        ]);
    }
}
