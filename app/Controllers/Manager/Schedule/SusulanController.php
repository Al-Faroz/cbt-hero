<?php

namespace App\Controllers\Manager\Schedule;

use App\Controllers\BaseController;

class SusulanController extends BaseController
{
    public function index(string $mainId)
    {
        return view('manager/schedule/susulan', [
            'auth' => session()->get('manager_auth'),
            'pageTitle' => 'Jadwal Susulan',
            'pageSubtitle' => 'Master Ujian',
            'activeMenu' => 'exam-jadwal',
            'mainJadwalId' => (int) $mainId,
            'appTimezone' => (string) config('App')->appTimezone,
        ]);
    }
}
