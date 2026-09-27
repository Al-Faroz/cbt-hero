<?php

namespace App\Controllers\Manager\Exam;

use App\Controllers\BaseController;
use App\Services\KegiatanPreflightService;

class KegiatanPreflightController extends BaseController
{
    public function index(string $id)
    {
        $report = (new KegiatanPreflightService())->inspect((int) $id);
        $this->response->setHeader('Cache-Control', 'no-store, private')
            ->setHeader('X-Robots-Tag', 'noindex, nofollow');
        if ($report === null) return $this->response->setStatusCode(404)->setBody('Kegiatan tidak ditemukan.');
        return view('manager/exam/kegiatan/preflight', $report + [
            'auth' => session()->get('manager_auth'), 'pageTitle' => 'Kesiapan Kegiatan',
            'pageSubtitle' => 'Master Ujian', 'activeMenu' => 'exam-kegiatan',
        ]);
    }
}
