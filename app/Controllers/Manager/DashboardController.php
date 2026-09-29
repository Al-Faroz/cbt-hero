<?php

namespace App\Controllers\Manager;

use App\Controllers\BaseController;
use DateTimeImmutable;
use DateTimeZone;
use Config\Database;

class DashboardController extends BaseController
{
    public function index()
    {
        $db = Database::connect();
        $tz = new DateTimeZone((string) config('App')->appTimezone);
        $today = new DateTimeImmutable('today', $tz);
        $tomorrow = $today->modify('+1 day');
        $todayStart = $today->format('Y-m-d H:i:s');
        $tomorrowStart = $tomorrow->format('Y-m-d H:i:s');

        $participants = (int) $db->table('peserta')->where('status', 'ACTIVE')->countAllResults();
        $ready = (int) $db->table('peserta')->where('status', 'ACTIVE')
            ->where('credential_status', 'READY')->countAllResults();

        $activities = $db->table('kegiatan')
            ->select('id, nama, status, tahun_pelajaran, semester, created_at')
            ->orderBy('created_at', 'DESC')->orderBy('id', 'DESC')->limit(5)
            ->get()->getResultArray();

        $todaySchedules = (int) $db->table('jadwal')
            ->where('mulai_at >=', $todayStart)
            ->where('mulai_at <', $tomorrowStart)
            ->countAllResults();

        $activeAttempts = (int) $db->table('attempt')
            ->where('status', 'ACTIVE')->countAllResults();

        $finishedToday = (int) $db->table('attempt')
            ->where('status', 'FINISHED')
            ->where('finish_at >=', $todayStart)
            ->where('finish_at <', $tomorrowStart)
            ->countAllResults();

        $pendingScoring = (int) $db->table('attempt')
            ->where('status', 'FINISHED')
            ->whereNotIn('scoring_status', ['COMPLETE'])
            ->countAllResults();

        $readyAssignments = (int) $db->table('prepared_assignment')
            ->where('status', 'READY')->countAllResults();

        $finalizedSchedules = (int) $db->table('jadwal')
            ->where('results_finalized_at IS NOT NULL', null, false)
            ->countAllResults();

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
                'running_activities' => (int) $db->table('kegiatan')->where('status', 'BERJALAN')->countAllResults(),
                'today_schedules' => $todaySchedules,
                'active_attempts' => $activeAttempts,
                'finished_today' => $finishedToday,
                'pending_scoring' => $pendingScoring,
                'ready_assignments' => $readyAssignments,
                'finalized_schedules' => $finalizedSchedules,
            ],
            'recentActivities' => $activities,
        ]);
    }
}
