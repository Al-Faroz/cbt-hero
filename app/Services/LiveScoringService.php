<?php

namespace App\Services;

use Config\Database;
use RuntimeException;
use Throwable;

class LiveScoringService
{
    private const CLICK_TYPES = ['PG', 'PG_KOMPLEKS', 'PG_BERTINGKAT', 'MATCHING'];

    public function options(): array
    {
        $db = Database::connect();
        $rows = $db->table('jadwal AS j')
            ->select('j.id, j.kegiatan_id, j.parent_jadwal_id, j.jenis_jadwal, j.mulai_at, j.batas_mulai_at, '
                . 'k.nama AS kegiatan_nama, b.nama_bank, m.nama_mapel')
            ->join('kegiatan AS k', 'k.id = j.kegiatan_id')
            ->join('bank_soal AS b', 'b.id = j.bank_soal_id')
            ->join('mata_pelajaran AS m', 'm.id = b.mapel_id')
            ->where('j.psych_instrument_id', null)
            ->orderBy('j.mulai_at', 'DESC')
            ->orderBy('m.nama_mapel', 'ASC')
            ->get()->getResultArray();

        foreach ($rows as &$row) {
            $row['id'] = (int) $row['id'];
            $row['kegiatan_id'] = (int) $row['kegiatan_id'];
            $row['parent_jadwal_id'] = $row['parent_jadwal_id'] === null ? null : (int) $row['parent_jadwal_id'];
        }
        unset($row);

        return ['ok' => true, 'status' => 200, 'data' => $rows];
    }

    public function state(): array
    {
        $db = Database::connect();
        $config = $this->config($db);
        return ['ok' => true, 'status' => 200, 'data' => $this->statePayload($db, $config)];
    }

    public function start(int $jadwalId, array $actor): array
    {
        if ($jadwalId < 1) {
            return $this->error(422, 'VALIDATION_FAILED', 'Pilih Jadwal untuk Live Scoring.');
        }

        $db = Database::connect();
        $schedule = $this->schedule($db, $jadwalId);
        if ($schedule === null) {
            return $this->error(404, 'NOT_FOUND', 'Jadwal akademik tidak ditemukan.');
        }

        $actorId = (int) ($actor['user_id'] ?? 0);
        $db->transBegin();
        try {
            $config = $this->configForUpdate($db);
            $token = trim((string) ($config['public_token'] ?? ''));
            if ($token === '') {
                $token = $this->token();
            }

            $now = date('Y-m-d H:i:s');
            $db->table('live_scoring_config')->where('id', 1)->update([
                'enabled' => 1,
                'jadwal_id' => $jadwalId,
                'public_token' => $token,
                'started_at' => $now,
                'stopped_at' => null,
                'updated_by' => $actorId > 0 ? $actorId : null,
            ]);

            if ($db->transStatus() === false || $db->transCommit() === false) {
                throw new RuntimeException('Commit START Live Scoring gagal.');
            }
        } catch (Throwable $e) {
            $db->transRollback();
            log_message('error', 'START Live Scoring gagal: {message}', ['message' => $e->getMessage()]);
            return $this->error(409, 'STATE_CONFLICT', 'Live Scoring belum dapat diaktifkan.');
        }

        (new AuditService())->log(
            'MANAGER',
            $actorId,
            'LIVE_SCORING_START',
            'LIVE_SCORING',
            'Live Scoring dibuka untuk Jadwal #' . $jadwalId,
            (string) ($actor['ip'] ?? ''),
            (string) ($actor['agent'] ?? ''),
            'jadwal',
            $jadwalId,
            null,
            ['enabled' => true, 'jadwal_id' => $jadwalId]
        );

        return $this->state();
    }

    public function stop(array $actor): array
    {
        $db = Database::connect();
        $actorId = (int) ($actor['user_id'] ?? 0);
        $db->transBegin();
        try {
            $config = $this->configForUpdate($db);
            $jadwalId = $config['jadwal_id'] === null ? null : (int) $config['jadwal_id'];

            $db->table('live_scoring_config')->where('id', 1)->update([
                'enabled' => 0,
                'stopped_at' => date('Y-m-d H:i:s'),
                'updated_by' => $actorId > 0 ? $actorId : null,
            ]);

            if ($db->transStatus() === false || $db->transCommit() === false) {
                throw new RuntimeException('Commit STOP Live Scoring gagal.');
            }
        } catch (Throwable $e) {
            $db->transRollback();
            log_message('error', 'STOP Live Scoring gagal: {message}', ['message' => $e->getMessage()]);
            return $this->error(409, 'STATE_CONFLICT', 'Live Scoring belum dapat dihentikan.');
        }

        (new AuditService())->log(
            'MANAGER',
            $actorId,
            'LIVE_SCORING_STOP',
            'LIVE_SCORING',
            'Live Scoring dihentikan' . ($jadwalId ? ' untuk Jadwal #' . $jadwalId : ''),
            (string) ($actor['ip'] ?? ''),
            (string) ($actor['agent'] ?? ''),
            'jadwal',
            $jadwalId,
            ['enabled' => (bool) ($config['enabled'] ?? false)],
            ['enabled' => false]
        );

        return $this->state();
    }

    public function regenerate(array $actor): array
    {
        $db = Database::connect();
        $actorId = (int) ($actor['user_id'] ?? 0);
        $db->transBegin();
        try {
            $config = $this->configForUpdate($db);
            $db->table('live_scoring_config')->where('id', 1)->update([
                'public_token' => $this->token(),
                'public_token_version' => ((int) ($config['public_token_version'] ?? 1)) + 1,
                'updated_by' => $actorId > 0 ? $actorId : null,
            ]);

            if ($db->transStatus() === false || $db->transCommit() === false) {
                throw new RuntimeException('Commit regenerate URL Live Scoring gagal.');
            }
        } catch (Throwable $e) {
            $db->transRollback();
            log_message('error', 'Regenerate URL Live Scoring gagal: {message}', ['message' => $e->getMessage()]);
            return $this->error(409, 'STATE_CONFLICT', 'URL publik belum dapat diganti.');
        }

        (new AuditService())->log(
            'MANAGER',
            $actorId,
            'LIVE_SCORING_REGENERATE_URL',
            'LIVE_SCORING',
            'URL publik Live Scoring diganti.',
            (string) ($actor['ip'] ?? ''),
            (string) ($actor['agent'] ?? ''),
            'live_scoring_config',
            1
        );

        return $this->state();
    }

    public function publicState(string $token): array
    {
        $db = Database::connect();
        $config = $this->config($db);
        $expected = (string) ($config['public_token'] ?? '');

        if (!(bool) ($config['enabled'] ?? false)
            || $expected === ''
            || $token === ''
            || !hash_equals($expected, $token)
            || empty($config['jadwal_id'])) {
            return $this->error(404, 'NOT_FOUND', 'Live Scoring tidak tersedia.');
        }

        $schedule = $this->schedule($db, (int) $config['jadwal_id']);
        if ($schedule === null) {
            return $this->error(404, 'NOT_FOUND', 'Live Scoring tidak tersedia.');
        }

        return ['ok' => true, 'status' => 200, 'data' => [
            'schedule' => [
                'kegiatan' => (string) $schedule['kegiatan_nama'],
                'ujian' => (string) ($schedule['nama_mapel'] ?: $schedule['nama_bank']),
                'jenis_jadwal' => (string) $schedule['jenis_jadwal'],
            ],
            'rows' => $this->scoreRows($db, (int) $schedule['id']),
            'generated_at' => date(DATE_ATOM),
        ]];
    }

    private function scoreRows($db, int $jadwalId): array
    {
        $attempts = $db->table('attempt')
            ->select('id, prepared_assignment_id, status, nomor_peserta_snapshot, nama_snapshot')
            ->where('jadwal_id', $jadwalId)
            ->whereIn('status', ['ACTIVE', 'FINISHED'])
            ->orderBy('nomor_peserta_snapshot', 'ASC')
            ->get()->getResultArray();

        if (!$attempts) {
            return [];
        }

        $attemptIds = array_map(static fn(array $row): int => (int) $row['id'], $attempts);

        $aggregates = $db->table('attempt AS a')
            ->select(
                'a.id AS attempt_id, sr.question_type, '
                . 'MAX(COALESCE(btc.weight_percent, 0)) AS type_weight, '
                . "SUM(CASE WHEN s.status = 'VOID' OR sr.change_kind = 'VOID' THEN 0 ELSE sr.max_point END) AS max_points, "
                . "SUM(CASE WHEN s.status = 'VOID' OR sr.change_kind = 'VOID' THEN 0 ELSE COALESCE(ar.effective_score, 0) END) AS raw_points",
                false
            )
            ->join('prepared_assignment_item AS pai', 'pai.prepared_assignment_id = a.prepared_assignment_id')
            ->join('soal AS s', 's.id = pai.soal_id')
            ->join(
                'soal_revision AS sr',
                'sr.soal_id = s.id AND sr.revision_no = s.current_revision_no',
                'left',
                false
            )
            ->join(
                'bank_type_config AS btc',
                'btc.bank_soal_id = s.bank_soal_id AND btc.question_type = sr.question_type',
                'left',
                false
            )
            ->join(
                'attempt_response AS ar',
                'ar.attempt_id = a.id AND ar.prepared_assignment_item_id = pai.id',
                'left',
                false
            )
            ->whereIn('a.id', $attemptIds)
            ->whereIn('sr.question_type', self::CLICK_TYPES)
            ->groupBy('a.id, sr.question_type')
            ->get()->getResultArray();

        $byAttempt = [];
        foreach ($aggregates as $row) {
            $attemptId = (int) $row['attempt_id'];
            $max = max(0.0, (float) ($row['max_points'] ?? 0));
            $weight = max(0.0, (float) ($row['type_weight'] ?? 0));
            if ($max <= 0 || $weight <= 0) {
                continue;
            }
            $raw = max(0.0, (float) ($row['raw_points'] ?? 0));
            $byAttempt[$attemptId]['earned'] = ($byAttempt[$attemptId]['earned'] ?? 0.0)
                + (min($raw, $max) / $max) * $weight;
            $byAttempt[$attemptId]['weight'] = ($byAttempt[$attemptId]['weight'] ?? 0.0) + $weight;
        }

        $rows = [];
        foreach ($attempts as $attempt) {
            $id = (int) $attempt['id'];
            $earned = (float) ($byAttempt[$id]['earned'] ?? 0.0);
            $weight = (float) ($byAttempt[$id]['weight'] ?? 0.0);
            $score = $weight > 0 ? round(($earned / $weight) * 100.0, 2) : 0.0;

            $rows[] = [
                'no_peserta' => (string) ($attempt['nomor_peserta_snapshot'] ?? ''),
                'nama' => (string) $attempt['nama_snapshot'],
                'skor' => $score,
                'status' => (string) $attempt['status'] === 'FINISHED' ? 'SELESAI' : 'MENGERJAKAN',
            ];
        }

        usort($rows, static function (array $a, array $b): int {
            $score = ((float) $b['skor']) <=> ((float) $a['skor']);
            if ($score !== 0) return $score;
            return strnatcasecmp((string) $a['no_peserta'], (string) $b['no_peserta']);
        });

        $rank = 0;
        $position = 0;
        $previousScore = null;
        foreach ($rows as &$row) {
            $position++;
            $score = (float) $row['skor'];
            if ($previousScore === null || abs($score - $previousScore) > 0.000001) {
                $rank = $position;
                $previousScore = $score;
            }
            $row = [
                'no' => $rank,
                'no_peserta' => $row['no_peserta'],
                'nama' => $row['nama'],
                'skor' => $score,
                'status' => $row['status'],
            ];
        }
        unset($row);

        return $rows;
    }

    private function statePayload($db, array $config): array
    {
        $schedule = !empty($config['jadwal_id'])
            ? $this->schedule($db, (int) $config['jadwal_id']) : null;
        $token = trim((string) ($config['public_token'] ?? ''));

        return [
            'enabled' => (bool) ($config['enabled'] ?? false),
            'jadwal_id' => $config['jadwal_id'] === null ? null : (int) $config['jadwal_id'],
            'public_token_version' => (int) ($config['public_token_version'] ?? 1),
            'public_url' => $token === '' ? null : base_url('live/' . rawurlencode($token)),
            'started_at' => $config['started_at'] ?? null,
            'stopped_at' => $config['stopped_at'] ?? null,
            'schedule' => $schedule,
        ];
    }

    private function configForUpdate($db): array
    {
        $db->query('INSERT IGNORE INTO live_scoring_config (id, enabled) VALUES (1, 0)');
        return $db->query('SELECT * FROM live_scoring_config WHERE id = 1 FOR UPDATE')
            ->getRowArray() ?? [
                'id' => 1,
                'enabled' => 0,
                'jadwal_id' => null,
                'public_token' => null,
                'public_token_version' => 1,
                'started_at' => null,
                'stopped_at' => null,
            ];
    }

    private function config($db): array
    {
        $row = $db->table('live_scoring_config')->where('id', 1)->get()->getRowArray();
        if ($row !== null) {
            return $row;
        }

        $db->table('live_scoring_config')->insert(['id' => 1, 'enabled' => 0]);
        return $db->table('live_scoring_config')->where('id', 1)->get()->getRowArray() ?? [
            'id' => 1,
            'enabled' => 0,
            'jadwal_id' => null,
            'public_token' => null,
            'public_token_version' => 1,
            'started_at' => null,
            'stopped_at' => null,
        ];
    }

    private function schedule($db, int $jadwalId): ?array
    {
        return $db->table('jadwal AS j')
            ->select('j.id, j.parent_jadwal_id, j.jenis_jadwal, j.mulai_at, j.batas_mulai_at, '
                . 'j.kegiatan_id, k.nama AS kegiatan_nama, b.nama_bank, m.nama_mapel')
            ->join('kegiatan AS k', 'k.id = j.kegiatan_id')
            ->join('bank_soal AS b', 'b.id = j.bank_soal_id')
            ->join('mata_pelajaran AS m', 'm.id = b.mapel_id')
            ->where('j.id', $jadwalId)
            ->where('j.psych_instrument_id', null)
            ->get()->getRowArray();
    }

    private function token(): string
    {
        return rtrim(strtr(base64_encode(random_bytes(36)), '+/', '-_'), '=');
    }

    private function error(int $status, string $code, string $message): array
    {
        return compact('status', 'code', 'message') + ['ok' => false];
    }
}
