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

        $scheduleRows = $db->table('jadwal AS j')
            ->select('j.id, j.kegiatan_id, j.urutan_ujian, j.mulai_at, j.batas_mulai_at, j.durasi_seconds, j.access_state, '
                . 'j.first_attempt_started_at, j.results_finalized_at, '
                . 'k.nama AS kegiatan_nama, b.nama_bank, b.tingkat, m.nama_mapel')
            ->join('kegiatan AS k', 'k.id = j.kegiatan_id')
            ->join('bank_soal AS b', 'b.id = j.bank_soal_id', 'left')
            ->join('mata_pelajaran AS m', 'm.id = b.mapel_id', 'left')
            ->where('j.jenis_jadwal', 'MAIN')
            ->where('j.parent_jadwal_id', null)
            ->orderBy('j.mulai_at', 'DESC')
            ->orderBy('j.id', 'DESC')
            ->limit(6)
            ->get()->getResultArray();

        $preparationService = new \App\Services\PreparationService();
        foreach ($scheduleRows as &$scheduleRow) {
            $prep = $preparationService->status((int) $scheduleRow['id']);
            $prepData = ($prep['ok'] ?? false) ? ($prep['data'] ?? []) : [];
            $scheduleRow['preparation_state'] = ($prepData['can_start'] ?? false) ? 'READY' : 'DRAFT';

            $now = new DateTimeImmutable('now', $tz);
            $start = new DateTimeImmutable((string) $scheduleRow['mulai_at'], $tz);
            $latest = new DateTimeImmutable((string) $scheduleRow['batas_mulai_at'], $tz);

            if ($scheduleRow['results_finalized_at'] !== null) {
                $scheduleRow['operational_state'] = 'SELESAI';
            } elseif ($scheduleRow['access_state'] === 'TAHAN') {
                $scheduleRow['operational_state'] = 'DITAHAN';
            } elseif ($now < $start) {
                $scheduleRow['operational_state'] = 'MENUNGGU_WAKTU';
            } elseif ($now <= $latest) {
                $scheduleRow['operational_state'] = 'SEDANG_BERJALAN';
            } else {
                $scheduleRow['operational_state'] = 'BATAS_MULAI_LEWAT';
            }
        }
        unset($scheduleRow);

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
                'running_activities' => 0,
                'today_schedules' => $todaySchedules,
                'active_attempts' => $activeAttempts,
                'finished_today' => $finishedToday,
                'pending_scoring' => $pendingScoring,
                'ready_assignments' => $readyAssignments,
                'finalized_schedules' => $finalizedSchedules,
            ],
            'recentSchedules' => $scheduleRows,
        ]);
    }
}
