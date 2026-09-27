<?php

namespace App\Controllers\Manager\Exam;

use App\Controllers\BaseController;

class KegiatanController extends BaseController
{
    public function index()
    {
        return view('manager/exam/kegiatan/index', [
            'auth' => session()->get('manager_auth'),
            'pageTitle' => 'Kegiatan Ujian',
            'pageSubtitle' => 'Master Ujian',
            'activeMenu' => 'exam-kegiatan',
        ]);
    }
}
