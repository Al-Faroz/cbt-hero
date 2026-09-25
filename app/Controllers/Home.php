<?php

namespace App\Controllers;

class Home extends BaseController
{
    public function index(): string
    {
        return view('participant/auth/login', [
            'pageTitle' => 'Login Peserta | CBT-HERO',
            'portalLabel' => 'Portal Peserta',
            'heroTitle' => 'Ujian lebih fokus. Tampilan tetap sederhana.',
            'heroText' => 'Masuk, pilih ujian, kerjakan, dan selesaikan dalam alur yang dirancang konsisten di desktop maupun Android.',
        ]);
    }
}
