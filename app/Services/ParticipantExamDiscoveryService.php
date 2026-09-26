<?php

namespace App\Services;

use DateTimeImmutable;
use DateTimeZone;

class ParticipantExamDiscoveryService
{
    private const ALLOWED_ACTIVITY_STATUS = ['BERJALAN', 'SELESAI'];

    private string $timezone;

    public function __construct()
    {
        $this->timezone = (string) config('App')->appTimezone;
    }

    public function participantIdentity(int $participantId): ?array
    {
        $row = db_connect()
            ->table('peserta AS p')
            ->select([
                'p.id',
                'p.nisn',
                'p.nama',
                'p.username',
                'p.status',
                'r.tingkat',
                'r.display_name AS rombel',
            ])
            ->join('rombel AS r', 'r.id = p.rombel_id', 'inner')
            ->where('p.id', $participantId)
            ->get()
            ->getRowArray();

        if (
            ! is_array($row)
            || ($row['status'] ?? '') !== 'ACTIVE'
        ) {
            return null;
        }

        return [
            'id' => (int) $row['id'],
            'nisn' => (string) $row['nisn'],
            'nama' => (string) $row['nama'],
            'username' => (string) $row['username'],
            'tingkat' => (int) $row['tingkat'],
            'rombel' => (string) $row['rombel'],
        ];
    }

    public function listForParticipant(int $participantId): array
    {
        $participant = $this->participantIdentity($participantId);

        if ($participant === null) {
            return [
                'participant' => null,
                'items' => [],
            ];
        }

        $db = db_connect();

        /*
         * Ambil candidate schedule per membership participant.
         * Filter target MAIN/SUSULAN dilakukan setelah target-count diketahui,
         * sehingga tidak perlu N+1 query per jadwal.
         */
        $rows = $db
            ->table('jadwal AS j')
            ->select([
                'j.id AS jadwal_id',
                'j.kegiatan_id',
                'j.parent_jadwal_id',
                'j.jenis_jadwal',
                'j.mulai_at',
                'j.batas_mulai_at',
                'j.durasi_seconds',
                'j.access_state',
                'k.nama AS nama_kegiatan',
                'k.jenis AS jenis_kegiatan',
                'k.status AS kegiatan_status',
                'k.exam_browser_required',
                'pk.id AS peserta_kegiatan_id',
                'pk.nomor_peserta',
                'pk.rombel_snapshot',
                'r.nama AS ruang',
                'bs.id AS bank_soal_id',
                'bs.tingkat AS bank_tingkat',
                'mp.nama_mapel',
                'pi.id AS psych_instrument_id',
                'pi.nama AS nama_instrumen',
                'jpt.id AS own_target_id',
                'jpt.target_mode AS own_target_mode',
            ])
            ->join('kegiatan AS k', 'k.id = j.kegiatan_id', 'inner')
            ->join('peserta_kegiatan AS pk', 'pk.kegiatan_id = k.id', 'inner')
            ->join('ruang AS r', 'r.id = pk.ruang_id', 'left')
            ->join('bank_soal AS bs', 'bs.id = j.bank_soal_id', 'left')
            ->join('mata_pelajaran AS mp', 'mp.id = bs.mapel_id', 'left')
            ->join('psych_instrument AS pi', 'pi.id = j.psych_instrument_id', 'left')
            ->join(
                'jadwal_peserta_target AS jpt',
                "jpt.jadwal_id = j.id
                    AND jpt.peserta_kegiatan_id = pk.id
                    AND jpt.status = 'TARGETED'",
                'left',
                false
            )
            ->where('pk.peserta_id', $participantId)
            ->where('pk.status', 'ACTIVE')
            ->whereIn('k.status', self::ALLOWED_ACTIVITY_STATUS)
            ->orderBy('j.mulai_at', 'ASC')
            ->get()
            ->getResultArray();

        if ($rows === []) {
            return [
                'participant' => $participant,
                'items' => [],
            ];
        }

        $scheduleIds = array_values(array_unique(array_map(
            static fn (array $row): int => (int) $row['jadwal_id'],
            $rows
        )));

        $targetCounts = $this->targetCounts($scheduleIds);
        $attempts = $this->latestAttempts($participantId, $scheduleIds);
        $readyAssignments = $this->readyAssignments($rows);
        $activeAttempt = $this->activeAttempt($participantId);

        $items = [];

        foreach ($rows as $row) {
            $scheduleId = (int) $row['jadwal_id'];
            $jenisJadwal = strtoupper((string) $row['jenis_jadwal']);
            $ownTarget = ! empty($row['own_target_id']);
            $targetCount = $targetCounts[$scheduleId] ?? 0;

            if ($jenisJadwal === 'SUSULAN') {
                if (! $ownTarget) {
                    continue;
                }
            } elseif ($jenisJadwal === 'MAIN') {
                /*
                 * Jika MAIN memiliki target eksplisit, hanya participant
                 * yang ditargetkan yang berhak melihatnya.
                 */
                if ($targetCount > 0 && ! $ownTarget) {
                    continue;
                }

                /*
                 * Academic MAIN mengikuti tingkat Bank.
                 * Psych MAIN tidak mempunyai tingkat Bank.
                 */
                if (
                    ! empty($row['bank_soal_id'])
                    && (int) $row['bank_tingkat'] !== $participant['tingkat']
                ) {
                    continue;
                }
            } else {
                continue;
            }

            if (
                empty($row['bank_soal_id'])
                && empty($row['psych_instrument_id'])
            ) {
                continue;
            }

            $participantActivityId = (int) $row['peserta_kegiatan_id'];
            $assignmentKey = $scheduleId . ':' . $participantActivityId;
            $attempt = $attempts[$scheduleId] ?? null;
            $preparedReady = isset($readyAssignments[$assignmentKey]);

            $items[] = $this->buildItem(
                $row,
                $attempt,
                $preparedReady,
                $activeAttempt
            );
        }

        usort(
            $items,
            static function (array $a, array $b): int {
                $priority = [
                    'LANJUTKAN' => 0,
                    'BISA_DIMULAI' => 1,
                    'BELUM_DIBUKA' => 2,
                    'SELESAI' => 3,
                ];

                $left = $priority[$a['ui_state']] ?? 9;
                $right = $priority[$b['ui_state']] ?? 9;

                if ($left !== $right) {
                    return $left <=> $right;
                }

                return strcmp($a['mulai_at'], $b['mulai_at']);
            }
        );

        return [
            'participant' => $participant,
            'items' => $items,
        ];
    }

    public function confirmationData(
        int $participantId,
        int $scheduleId
    ): ?array {
        $payload = $this->listForParticipant($participantId);

        $exam = null;

        foreach ($payload['items'] as $item) {
            if ((int) $item['jadwal_id'] === $scheduleId) {
                $exam = $item;
                break;
            }
        }

        if ($exam === null || $payload['participant'] === null) {
            return null;
        }

        $tokenRow = db_connect()
            ->table('token_control')
            ->select('enabled')
            ->where('id', 1)
            ->get()
            ->getRowArray();

        $messageRow = db_connect()
            ->table('sys_settings')
            ->select('setting_value')
            ->where('setting_key', 'participant_pre_exam_message')
            ->get()
            ->getRowArray();

        $instruction = trim((string) ($messageRow['setting_value'] ?? ''));

        if ($instruction === '') {
            $instruction =
                'Pastikan identitas dan informasi ujian sudah benar sebelum melanjutkan.';
        }

        $mode = match ($exam['ui_state']) {
            'LANJUTKAN' => 'RESUME',
            'BISA_DIMULAI' => 'START',
            default => 'LOCKED',
        };

        return [
            'participant' => $payload['participant'],
            'exam' => $exam,
            'token_enabled' => (bool) ($tokenRow['enabled'] ?? false),
            'instruction' => $instruction,
            'mode' => $mode,
            'eligible' => (bool) ($exam['action_enabled'] ?? false)
                && in_array($mode, ['START', 'RESUME'], true),
        ];
    }

    private function targetCounts(array $scheduleIds): array
    {
        $rows = db_connect()
            ->table('jadwal_peserta_target')
            ->select('jadwal_id, COUNT(*) AS total')
            ->where('status', 'TARGETED')
            ->whereIn('jadwal_id', $scheduleIds)
            ->groupBy('jadwal_id')
            ->get()
            ->getResultArray();

        $result = [];

        foreach ($rows as $row) {
            $result[(int) $row['jadwal_id']] = (int) $row['total'];
        }

        return $result;
    }

    private function latestAttempts(
        int $participantId,
        array $scheduleIds
    ): array {
        $rows = db_connect()
            ->table('attempt')
            ->select([
                'id',
                'jadwal_id',
                'status',
                'finish_reason',
                'start_at',
                'finish_at',
                'deadline_at',
            ])
            ->where('peserta_id', $participantId)
            ->whereIn('jadwal_id', $scheduleIds)
            ->orderBy('id', 'DESC')
            ->get()
            ->getResultArray();

        $result = [];

        foreach ($rows as $row) {
            $scheduleId = (int) $row['jadwal_id'];

            if (! isset($result[$scheduleId])) {
                $result[$scheduleId] = $row;
            }
        }

        return $result;
    }

    private function readyAssignments(array $rows): array
    {
        $scheduleIds = [];
        $membershipIds = [];

        foreach ($rows as $row) {
            $scheduleIds[] = (int) $row['jadwal_id'];
            $membershipIds[] = (int) $row['peserta_kegiatan_id'];
        }

        $assignments = db_connect()
            ->table('prepared_assignment')
            ->select('generated_for_jadwal_id, peserta_kegiatan_id')
            ->where('status', 'READY')
            ->whereIn(
                'generated_for_jadwal_id',
                array_values(array_unique($scheduleIds))
            )
            ->whereIn(
                'peserta_kegiatan_id',
                array_values(array_unique($membershipIds))
            )
            ->get()
            ->getResultArray();

        $result = [];

        foreach ($assignments as $assignment) {
            $key = (int) $assignment['generated_for_jadwal_id']
                . ':'
                . (int) $assignment['peserta_kegiatan_id'];

            $result[$key] = true;
        }

        return $result;
    }

    private function activeAttempt(int $participantId): ?array
    {
        $row = db_connect()
            ->table('attempt_active_lock AS aal')
            ->select('aal.attempt_id, a.jadwal_id')
            ->join('attempt AS a', 'a.id = aal.attempt_id', 'inner')
            ->where('aal.peserta_id', $participantId)
            ->where('a.status', 'ACTIVE')
            ->get()
            ->getRowArray();

        if (! is_array($row)) {
            return null;
        }

        return [
            'attempt_id' => (int) $row['attempt_id'],
            'jadwal_id' => (int) $row['jadwal_id'],
        ];
    }

    private function buildItem(
        array $row,
        ?array $attempt,
        bool $preparedReady,
        ?array $activeAttempt
    ): array {
        $scheduleId = (int) $row['jadwal_id'];

        $isAcademic = ! empty($row['bank_soal_id']);
        $type = $isAcademic ? 'AKADEMIK' : 'PSIKOLOGIS';

        $subject = $isAcademic
            ? (string) ($row['nama_mapel'] ?: 'Ujian Akademik')
            : (string) ($row['nama_instrumen'] ?: 'Tes Psikologis');

        $attemptStatus = strtoupper((string) ($attempt['status'] ?? ''));
        $scheduleAccess = strtoupper((string) $row['access_state']);

        $uiState = 'BELUM_DIBUKA';
        $availability = 'NOT_OPEN';
        $reason = 'Ujian belum dapat dimulai.';
        $actionEnabled = false;
        $attemptId = isset($attempt['id']) ? (int) $attempt['id'] : null;

        if ($attemptStatus === 'ACTIVE') {
            $uiState = 'LANJUTKAN';

            if ($scheduleAccess === 'BUKA') {
                $availability = 'RESUME_READY';
                $reason = 'Ujian sedang berlangsung.';
                $actionEnabled = true;
            } else {
                $availability = 'HELD';
                $reason = 'Akses ujian sedang ditahan.';
            }
        } elseif (in_array($attemptStatus, ['FINISHED', 'SUPERSEDED'], true)) {
            $uiState = 'SELESAI';
            $availability = 'FINISHED';
            $reason = 'Ujian telah selesai.';
        } else {
            $now = $this->nowTimestamp();
            $start = $this->timestamp((string) $row['mulai_at']);
            $latestStart = $this->timestamp((string) $row['batas_mulai_at']);

            if ($now < $start) {
                $availability = 'TOO_EARLY';
                $reason = 'Ujian belum dibuka.';
            } elseif ($now > $latestStart) {
                $availability = 'START_WINDOW_CLOSED';
                $reason = 'Batas waktu mulai telah berakhir.';
            } elseif ($scheduleAccess !== 'BUKA') {
                $availability = 'HELD';
                $reason = 'Akses ujian sedang ditahan.';
            } elseif (! $preparedReady) {
                $availability = 'PREPARATION_NOT_READY';
                $reason = 'Ujian sedang dipersiapkan.';
            } elseif (
                $activeAttempt !== null
                && (int) $activeAttempt['jadwal_id'] !== $scheduleId
            ) {
                $availability = 'ACTIVE_ATTEMPT_EXISTS';
                $reason = 'Selesaikan ujian yang sedang berlangsung terlebih dahulu.';
            } else {
                $uiState = 'BISA_DIMULAI';
                $availability = 'START_READY';
                $reason = 'Ujian dapat dimulai.';
                $actionEnabled = true;
            }
        }

        return [
            'jadwal_id' => $scheduleId,
            'kegiatan_id' => (int) $row['kegiatan_id'],
            'nama_kegiatan' => (string) $row['nama_kegiatan'],
            'nama_mapel' => $isAcademic ? $subject : null,
            'nama_instrumen' => $isAcademic ? null : $subject,
            'nama_ujian' => $subject,
            'tipe' => $type,
            'jenis_jadwal' => strtoupper((string) $row['jenis_jadwal']),
            'mulai_at' => $this->isoDate((string) $row['mulai_at']),
            'batas_mulai_at' => $this->isoDate((string) $row['batas_mulai_at']),
            'durasi_seconds' => (int) $row['durasi_seconds'],
            'access_state' => $scheduleAccess,
            'ui_state' => $uiState,
            'availability' => $availability,
            'availability_message' => $reason,
            'action_enabled' => $actionEnabled,
            'attempt_id' => $attemptId,
            'nomor_peserta' => $row['nomor_peserta'] === null
                ? null
                : (string) $row['nomor_peserta'],
            'rombel' => (string) $row['rombel_snapshot'],
            'ruang' => $row['ruang'] === null ? null : (string) $row['ruang'],
            'exam_browser_required' => (bool) $row['exam_browser_required'],
        ];
    }

    private function isoDate(string $value): string
    {
        return (new DateTimeImmutable(
            $value,
            new DateTimeZone($this->timezone)
        ))->format(DATE_ATOM);
    }

    private function timestamp(string $value): int
    {
        return (new DateTimeImmutable(
            $value,
            new DateTimeZone($this->timezone)
        ))->getTimestamp();
    }

    private function nowTimestamp(): int
    {
        return (new DateTimeImmutable(
            'now',
            new DateTimeZone($this->timezone)
        ))->getTimestamp();
    }
}
