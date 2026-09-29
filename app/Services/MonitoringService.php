<?php

namespace App\Services;

use Config\Database;

class MonitoringService
{
    public function options(): array
    {
        $db = Database::connect();

        $kegiatan = $db->table('kegiatan')
            ->select('id, nama, status, tahun_pelajaran, semester')
            ->where('jenis', 'AKADEMIK')
            ->whereIn('status', ['BERJALAN', 'SELESAI'])
            ->orderBy('status', 'ASC')->orderBy('id', 'DESC')
            ->get()->getResultArray();

        $jadwal = $db->table('jadwal AS j')
            ->select('j.id, j.kegiatan_id, j.parent_jadwal_id, j.jenis_jadwal, j.mulai_at, j.access_state, '
                . 'k.nama AS kegiatan_nama, k.status AS kegiatan_status, b.nama_bank, b.tingkat, m.nama_mapel')
            ->join('kegiatan AS k', 'k.id = j.kegiatan_id')
            ->join('bank_soal AS b', 'b.id = j.bank_soal_id')
            ->join('mata_pelajaran AS m', 'm.id = b.mapel_id')
            ->where('k.jenis', 'AKADEMIK')
            ->whereIn('k.status', ['BERJALAN', 'SELESAI'])
            ->orderBy('j.mulai_at', 'DESC')->orderBy('j.id', 'DESC')
            ->get()->getResultArray();

        $ruang = $db->table('ruang')->select('id, kode, nama')
            ->where('status', 'ACTIVE')->orderBy('nama', 'ASC')->get()->getResultArray();

        return ['ok' => true, 'status' => 200, 'data' => [
            'kegiatan' => $kegiatan,
            'jadwal' => $jadwal,
            'ruang' => $ruang,
        ]];
    }

    public function summary(array $query): array
    {
        $jadwalId = $this->positive($query['jadwal_id'] ?? null, 0);
        if ($jadwalId < 1)
            return $this->error(422, 'JADWAL_REQUIRED', 'Pilih Jadwal untuk Monitoring.');

        $db = Database::connect();
        $schedule = $this->schedule($db, $jadwalId);
        if ($schedule === null)
            return $this->error(404, 'NOT_FOUND', 'Jadwal akademik tidak ditemukan.');

        $filters = $this->filters($query);
        $rows = $this->builder($db, $schedule, $filters)
            ->select($this->listSelect())
            ->get()->getResultArray();

        $summary = ['total' => count($rows), 'belum' => 0, 'sedang' => 0, 'selesai' => 0, 'tidak_terdeteksi' => 0];
        foreach ($rows as $row) {
            $state = $this->rowState($row);
            if ($state['status'] === 'BELUM') $summary['belum']++;
            elseif ($state['status'] === 'SEDANG') $summary['sedang']++;
            elseif ($state['status'] === 'SELESAI') $summary['selesai']++;
            if ($state['connection_state'] === 'TIDAK_TERDETEKSI') $summary['tidak_terdeteksi']++;
        }

        return ['ok' => true, 'status' => 200, 'data' => [
            'summary' => $summary,
            'jadwal' => $this->schedulePublic($schedule),
            'cursor' => time(),
        ]];
    }

    public function attempts(array $query): array
    {
        $jadwalId = $this->positive($query['jadwal_id'] ?? null, 0);
        if ($jadwalId < 1)
            return $this->error(422, 'JADWAL_REQUIRED', 'Pilih Jadwal untuk Monitoring.');

        $db = Database::connect();
        $schedule = $this->schedule($db, $jadwalId);
        if ($schedule === null)
            return $this->error(404, 'NOT_FOUND', 'Jadwal akademik tidak ditemukan.');

        $filters = $this->filters($query);
        $page = min($this->positive($query['page'] ?? null, 1), 100000);
        $perPage = $this->positive($query['per_page'] ?? null, 50);
        $perPage = in_array($perPage, [25, 50, 100], true) ? $perPage : 50;

        $total = (int) $this->builder($db, $schedule, [])->countAllResults();
        $filtered = (int) $this->builder($db, $schedule, $filters)->countAllResults();
        $pages = max(1, (int) ceil($filtered / $perPage));
        $page = min($page, $pages);

        $rows = $this->builder($db, $schedule, $filters)
            ->select($this->listSelect())
            ->orderBy('pk.rombel_snapshot', 'ASC')
            ->orderBy('pk.nama_snapshot', 'ASC')
            ->limit($perPage, ($page - 1) * $perPage)
            ->get()->getResultArray();

        $items = [];
        foreach ($rows as $row) {
            $state = $this->rowState($row);
            $items[] = [
                'peserta_kegiatan_id' => (int) $row['peserta_kegiatan_id'],
                'attempt_id' => $row['attempt_id'] === null ? null : (int) $row['attempt_id'],
                'nomor_peserta' => $row['nomor_peserta'],
                'nama' => $row['nama'],
                'rombel' => $row['rombel'],
                'ruang' => $row['ruang'],
                'ujian' => $schedule['nama_mapel'],
                'jenis_jadwal' => $schedule['jenis_jadwal'],
                'status' => $state['status'],
                'attempt_status' => $row['attempt_status'],
                'finish_reason' => $row['finish_reason'],
                'used_seconds' => $state['used_seconds'],
                'remaining_seconds' => $state['remaining_seconds'],
                'last_sync_at' => $row['last_sync_at'],
                'last_activity_at' => $row['last_activity_at'],
                'connection_state' => $state['connection_state'],
                'paused' => $row['pause_started_at'] !== null,
                'pending_sync_risk' => $this->pendingSyncRisk($row),
                'client_generation' => $row['client_generation'] === null ? null : (int) $row['client_generation'],
            ];
        }

        return ['ok' => true, 'status' => 200, 'data' => [
            'items' => $items,
            'pagination' => [
                'page' => $page,
                'per_page' => $perPage,
                'pages' => $pages,
                'total' => $total,
                'filtered' => $filtered,
            ],
            'cursor' => time(),
        ]];
    }

    public function detail(int $attemptId): array
    {
        if ($attemptId < 1)
            return $this->error(404, 'NOT_FOUND', 'Attempt tidak ditemukan.');

        $db = Database::connect();
        $attempt = $db->table('attempt AS a')
            ->select('a.*, j.jenis_jadwal, j.access_state, k.nama AS kegiatan_nama, '
                . 'b.nama_bank, b.tingkat, m.nama_mapel')
            ->join('jadwal AS j', 'j.id = a.jadwal_id')
            ->join('kegiatan AS k', 'k.id = j.kegiatan_id')
            ->join('bank_soal AS b', 'b.id = j.bank_soal_id')
            ->join('mata_pelajaran AS m', 'm.id = b.mapel_id')
            ->where('a.id', $attemptId)->get()->getRowArray();
        if ($attempt === null)
            return $this->error(404, 'NOT_FOUND', 'Attempt tidak ditemukan.');

        $responseSummary = $db->table('attempt_response')
            ->select('COUNT(*) AS saved_count, '
                . 'SUM(CASE WHEN answer_payload IS NOT NULL AND answer_payload != "" THEN 1 ELSE 0 END) AS answered_count, '
                . 'SUM(CASE WHEN is_flagged = 1 THEN 1 ELSE 0 END) AS flagged_count, '
                . 'MAX(server_revision) AS max_server_revision', false)
            ->where('attempt_id', $attemptId)->get()->getRowArray();

        $events = $db->table('attempt_client_event')
            ->select('event_type, client_generation, metadata_json, created_at')
            ->where('attempt_id', $attemptId)
            ->orderBy('id', 'DESC')->limit(20)->get()->getResultArray();

        $pauses = $db->table('attempt_pause_event')
            ->select('started_at, ended_at, paused_seconds, reason')
            ->where('attempt_id', $attemptId)
            ->orderBy('id', 'DESC')->limit(20)->get()->getResultArray();

        $adjustments = $db->table('attempt_time_adjustment')
            ->select('seconds_added, reason, created_at')
            ->where('attempt_id', $attemptId)
            ->orderBy('id', 'DESC')->limit(20)->get()->getResultArray();

        $state = $this->rowState([
            'attempt_id' => $attempt['id'],
            'attempt_status' => $attempt['status'],
            'finish_reason' => $attempt['finish_reason'],
            'deadline_at' => $attempt['deadline_at'],
            'pause_started_at' => $attempt['pause_started_at'],
            'duration_seconds_snapshot' => $attempt['duration_seconds_snapshot'],
            'added_seconds' => $attempt['added_seconds'],
            'last_sync_at' => $attempt['last_sync_at'],
            'last_activity_at' => $attempt['last_activity_at'],
            'start_at' => $attempt['start_at'],
        ]);

        return ['ok' => true, 'status' => 200, 'data' => [
            'attempt' => [
                'id' => (int) $attempt['id'],
                'jadwal_id' => (int) $attempt['jadwal_id'],
                'root_jadwal_id' => (int) $attempt['root_jadwal_id'],
                'attempt_no' => (int) $attempt['attempt_no'],
                'status' => $attempt['status'],
                'finish_reason' => $attempt['finish_reason'],
                'nomor_peserta' => $attempt['nomor_peserta_snapshot'],
                'username' => $attempt['username_snapshot'],
                'nama' => $attempt['nama_snapshot'],
                'rombel' => $attempt['rombel_snapshot'],
                'ruang' => $attempt['ruang_snapshot'],
                'kegiatan' => $attempt['kegiatan_nama'],
                'ujian' => $attempt['nama_mapel'],
                'jenis_jadwal' => $attempt['jenis_jadwal'],
                'start_at' => $attempt['start_at'],
                'finish_at' => $attempt['finish_at'],
                'deadline_at' => $attempt['deadline_at'],
                'remaining_seconds' => $state['remaining_seconds'],
                'used_seconds' => $state['used_seconds'],
                'paused' => $attempt['pause_started_at'] !== null,
                'client_generation' => (int) $attempt['client_generation'],
                'server_sync_revision' => (int) $attempt['server_sync_revision'],
                'last_sync_at' => $attempt['last_sync_at'],
                'last_activity_at' => $attempt['last_activity_at'],
                'connection_state' => $state['connection_state'],
                'pending_sync_risk' => $this->pendingSyncRisk($attempt),
            ],
            'response_summary' => [
                'saved_count' => (int) ($responseSummary['saved_count'] ?? 0),
                'answered_count' => (int) ($responseSummary['answered_count'] ?? 0),
                'flagged_count' => (int) ($responseSummary['flagged_count'] ?? 0),
                'max_server_revision' => (int) ($responseSummary['max_server_revision'] ?? 0),
            ],
            'client_events' => $events,
            'pause_events' => $pauses,
            'time_adjustments' => $adjustments,
        ]];
    }

    private function schedule($db, int $jadwalId): ?array
    {
        $row = $db->table('jadwal AS j')
            ->select('j.*, k.nama AS kegiatan_nama, k.jenis AS kegiatan_jenis, k.status AS kegiatan_status, '
                . 'b.nama_bank, b.tingkat AS bank_tingkat, m.nama_mapel')
            ->join('kegiatan AS k', 'k.id = j.kegiatan_id')
            ->join('bank_soal AS b', 'b.id = j.bank_soal_id')
            ->join('mata_pelajaran AS m', 'm.id = b.mapel_id')
            ->where('j.id', $jadwalId)->get()->getRowArray();

        if ($row === null || $row['kegiatan_jenis'] !== 'AKADEMIK' || $row['bank_soal_id'] === null)
            return null;
        return $row;
    }

    private function builder($db, array $schedule, array $filters)
    {
        $jadwalId = (int) $schedule['id'];
        $builder = $db->table('peserta_kegiatan AS pk')
            ->join('peserta AS p', 'p.id = pk.peserta_id')
            ->join('ruang AS r', 'r.id = pk.ruang_id', 'left')
            ->join(
                'attempt AS a',
                'a.id = (SELECT MAX(a2.id) FROM attempt a2 '
                    . 'WHERE a2.jadwal_id = ' . $jadwalId
                    . ' AND a2.peserta_kegiatan_id = pk.id)',
                'left',
                false
            )
            ->where('pk.status', 'ACTIVE')->where('p.status', 'ACTIVE');

        if ($schedule['jenis_jadwal'] === 'SUSULAN') {
            $builder->join(
                'jadwal_peserta_target AS t',
                't.peserta_kegiatan_id = pk.id AND t.jadwal_id = ' . $jadwalId
                    . " AND t.status = 'TARGETED'",
                'inner',
                false
            );
        } else {
            $builder->join('rombel AS rb', 'rb.id = p.rombel_id')
                ->where('pk.kegiatan_id', (int) $schedule['kegiatan_id'])
                ->where('rb.tingkat', (int) $schedule['bank_tingkat']);
        }

        $q = (string) ($filters['q'] ?? '');
        if ($q !== '') {
            $builder->groupStart()
                ->like('pk.nomor_peserta', $q)
                ->orLike('pk.nama_snapshot', $q)
                ->orLike('pk.rombel_snapshot', $q)
                ->groupEnd();
        }
        if (($filters['ruang_id'] ?? 0) > 0)
            $builder->where('pk.ruang_id', (int) $filters['ruang_id']);
        if (($filters['rombel'] ?? '') !== '')
            $builder->where('pk.rombel_snapshot', (string) $filters['rombel']);
        if (($filters['status'] ?? '') !== '') {
            $status = (string) $filters['status'];
            if ($status === 'BELUM') $builder->where('a.id', null);
            elseif ($status === 'SEDANG') $builder->where('a.status', 'ACTIVE');
            elseif ($status === 'SELESAI') $builder->whereIn('a.status', ['FINISHED', 'SUPERSEDED']);
        }

        return $builder;
    }

    private function listSelect(): string
    {
        return 'pk.id AS peserta_kegiatan_id, pk.nomor_peserta, pk.nama_snapshot AS nama, '
            . 'pk.rombel_snapshot AS rombel, r.nama AS ruang, '
            . 'a.id AS attempt_id, a.status AS attempt_status, a.finish_reason, a.start_at, a.finish_at, '
            . 'a.deadline_at, a.pause_started_at, a.duration_seconds_snapshot, a.added_seconds, '
            . 'a.client_generation, a.server_sync_revision, a.last_sync_at, a.last_activity_at';
    }

    private function rowState(array $row): array
    {
        if (($row['attempt_id'] ?? null) === null) {
            return [
                'status' => 'BELUM',
                'connection_state' => 'BELUM',
                'used_seconds' => 0,
                'remaining_seconds' => null,
            ];
        }

        $status = strtoupper((string) ($row['attempt_status'] ?? ''));
        $total = max(0, (int) ($row['duration_seconds_snapshot'] ?? 0) + (int) ($row['added_seconds'] ?? 0));

        if ($status !== 'ACTIVE') {
            return [
                'status' => 'SELESAI',
                'connection_state' => 'SELESAI',
                'used_seconds' => $total,
                'remaining_seconds' => 0,
            ];
        }

        $deadline = strtotime((string) ($row['deadline_at'] ?? ''));
        $anchor = !empty($row['pause_started_at'])
            ? strtotime((string) $row['pause_started_at'])
            : time();
        $remaining = ($deadline === false || $anchor === false) ? 0 : max(0, $deadline - $anchor);
        $used = max(0, min($total, $total - $remaining));

        if (!empty($row['pause_started_at'])) {
            $connection = 'RESET_AKSES';
        } else {
            $last = strtotime((string) (($row['last_sync_at'] ?? null) ?: ($row['last_activity_at'] ?? null)));
            $connection = ($last !== false && (time() - $last) <= 30)
                ? 'ONLINE'
                : 'TIDAK_TERDETEKSI';
        }

        return [
            'status' => 'SEDANG',
            'connection_state' => $connection,
            'used_seconds' => $used,
            'remaining_seconds' => $remaining,
        ];
    }

    private function pendingSyncRisk(array $row): bool
    {
        if (strtoupper((string) ($row['attempt_status'] ?? $row['status'] ?? '')) !== 'ACTIVE')
            return false;

        $lastSync = strtotime((string) ($row['last_sync_at'] ?? ''));
        $lastActivity = strtotime((string) ($row['last_activity_at'] ?? ''));
        if ($lastSync === false) return true;
        if ($lastActivity !== false && $lastActivity > ($lastSync + 5)) return true;
        return (time() - $lastSync) > 20;
    }

    private function filters(array $query): array
    {
        return [
            'q' => mb_substr(trim($this->scalar($query['q'] ?? null)), 0, 100),
            'ruang_id' => $this->positive($query['ruang_id'] ?? null, 0),
            'rombel' => mb_substr(trim($this->scalar($query['rombel'] ?? null)), 0, 50),
            'status' => in_array(strtoupper(trim($this->scalar($query['status'] ?? null))), ['BELUM', 'SEDANG', 'SELESAI'], true)
                ? strtoupper(trim($this->scalar($query['status'] ?? null))) : '',
        ];
    }

    private function schedulePublic(array $schedule): array
    {
        return [
            'id' => (int) $schedule['id'],
            'kegiatan_id' => (int) $schedule['kegiatan_id'],
            'kegiatan_nama' => $schedule['kegiatan_nama'],
            'jenis_jadwal' => $schedule['jenis_jadwal'],
            'nama_bank' => $schedule['nama_bank'],
            'nama_mapel' => $schedule['nama_mapel'],
            'tingkat' => (int) $schedule['bank_tingkat'],
            'access_state' => $schedule['access_state'],
        ];
    }

    private function positive(mixed $value, int $default): int
    {
        $parsed = is_scalar($value)
            ? filter_var($value, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]])
            : false;
        return is_int($parsed) ? $parsed : $default;
    }

    private function scalar(mixed $value): string
    {
        return is_scalar($value) ? (string) $value : '';
    }

    private function error(int $status, string $code, string $message): array
    {
        return compact('status', 'code', 'message') + ['ok' => false];
    }
}
