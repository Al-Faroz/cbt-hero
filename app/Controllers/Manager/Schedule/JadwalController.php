<?php

namespace App\Controllers\Manager\Schedule;

use App\Controllers\BaseController;

class JadwalController extends BaseController
{
    public function index()
    {
        return view('manager/schedule/jadwal', [
            'auth' => session()->get('manager_auth'),
            'pageTitle' => 'Jadwal Ujian',
            'pageSubtitle' => 'Master Ujian',
            'activeMenu' => 'exam-jadwal',
            'appTimezone' => (string) config('App')->appTimezone,
        ]);
    }
}
