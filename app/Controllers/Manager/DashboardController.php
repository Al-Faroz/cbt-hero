<?php

namespace App\Controllers\Manager;

use App\Controllers\BaseController;
use Config\Database;

class DashboardController extends BaseController
{
    public function index()
    {
        $db = Database::connect();
        $participants = (int) $db->table('peserta')->where('status', 'ACTIVE')->countAllResults();
        $ready = (int) $db->table('peserta')->where('status', 'ACTIVE')
            ->where('credential_status', 'READY')->countAllResults();
        $activities = $db->table('kegiatan')->select('id, nama, status, tahun_pelajaran, semester, created_at')
            ->orderBy('created_at', 'DESC')->orderBy('id', 'DESC')->limit(5)->get()->getResultArray();

        return view('manager/dashboard/index', [
            'auth' => session()->get('manager_auth'),
            'pageTitle' => 'Dashboard',
            'pageSubtitle' => 'Ringkasan operasional CBT-HERO',
            'activeMenu' => 'dashboard',
            'summary' => [
                'participants' => $participants,
                'ready' => $ready,
                'classes' => (int) $db->table('rombel')->where('status', 'ACTIVE')->countAllResults(),
                'subjects' => (int) $db->table('mata_pelajaran')->where('status', 'ACTIVE')->countAllResults(),
                'activities' => (int) $db->table('kegiatan')->countAllResults(),
                'drafts' => (int) $db->table('kegiatan')->where('status', 'DRAFT')->countAllResults(),
            ],
            'recentActivities' => $activities,
        ]);
    }
}
