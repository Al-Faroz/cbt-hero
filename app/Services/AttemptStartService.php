<?php

namespace App\Services;

use Config\Database;
use DateInterval;
use RuntimeException;
use Throwable;

class AttemptStartService
{
    public function start(int $participantId, int $jadwalId, array $payload, string $idempotencyKey): array
    {
        $ops = new ParticipantOperationService();
        $ownership = new AttemptOwnershipService();

        if (!$ops->validKey($idempotencyKey))
            return $this->error(422, 'IDEMPOTENCY_REQUIRED', 'Idempotency-Key wajib dan formatnya tidak valid.');
        if (($payload['agreement'] ?? false) !== true)
            return $this->error(422, 'AGREEMENT_REQUIRED', 'Konfirmasi peserta wajib dicentang.');

        $clientUuid = trim($this->scalar($payload['client_uuid'] ?? null));
        if (!$ownership->validClientUuid($clientUuid))
            return $this->error(422, 'CLIENT_ID_INVALID', 'Identitas client tidak valid.');

        $token = mb_strtoupper(trim($this->scalar($payload['token'] ?? null)), 'UTF-8');
        $examProof = trim($this->scalar($payload['exam_browser_proof'] ?? null));
        $hash = hash('sha256', json_encode([
            'jadwal_id' => $jadwalId,
            'agreement' => true,
            'token_hash' => hash('sha256', $token),
            'client_uuid' => $clientUuid,
            'exam_proof_hash' => hash('sha256', $examProof),
        ], JSON_UNESCAPED_SLASHES));

        $db = Database::connect();
        $db->transBegin();

        try {
            $participant = $db->query(
                'SELECT id, username, credential_revision, status FROM peserta WHERE id = ? FOR UPDATE',
                [$participantId]
            )->getRowArray();
            if ($participant === null || $participant['status'] !== 'ACTIVE') {
                $db->transRollback();
                return $this->error(401, 'AUTH_REQUIRED', 'Peserta tidak aktif.');
            }

            $operation = $ops->begin($db, $idempotencyKey, $participantId, 'START_ATTEMPT', $jadwalId, $hash);
            if (!($operation['ok'] ?? false)) {
                $db->transRollback();
                return $this->error(409, 'IDEMPOTENCY_CONFLICT', 'Idempotency-Key sudah dipakai untuk request berbeda.');
            }
            $existingOp = $operation['existing'];
            if ($existingOp !== null && (int) ($existingOp['attempt_id'] ?? 0) > 0) {
                $attempt = $ownership->owned($db, $participantId, (int) $existingOp['attempt_id']);
                if ($attempt !== null) {
                    $db->transCommit();
                    return $this->success($attempt, true);
                }
            }

            $schedule = $db->query(
                "SELECT j.*, k.status AS kegiatan_status, k.jenis AS kegiatan_jenis, k.exam_browser_required,
                        b.status AS bank_status, b.tingkat AS bank_tingkat
                 FROM jadwal j
                 JOIN kegiatan k ON k.id = j.kegiatan_id
                 LEFT JOIN bank_soal b ON b.id = j.bank_soal_id
                 WHERE j.id = ? FOR UPDATE",
                [$jadwalId]
            )->getRowArray();
            if ($schedule === null || $schedule['kegiatan_jenis'] !== 'AKADEMIK'
                || $schedule['bank_soal_id'] === null || $schedule['psych_instrument_id'] !== null) {
                $db->transRollback();
                return $this->error(404, 'NOT_FOUND', 'Jadwal akademik tidak tersedia.');
            }

            if ($schedule['jenis_jadwal'] === 'MAIN' && $schedule['kegiatan_status'] !== 'BERJALAN') {
                $db->transRollback();
                return $this->error(409, 'ACTIVITY_NOT_RUNNING', 'Kegiatan belum berjalan.');
            }
            if ($schedule['jenis_jadwal'] === 'SUSULAN'
                && !in_array($schedule['kegiatan_status'], ['BERJALAN', 'SELESAI'], true)) {
                $db->transRollback();
                return $this->error(409, 'ACTIVITY_NOT_AVAILABLE', 'Kegiatan belum dapat menjalankan Susulan.');
            }
            if ($schedule['access_state'] !== 'BUKA') {
                $db->transRollback();
                return $this->error(423, 'SCHEDULE_HELD', 'Akses ujian sedang ditahan.');
            }

            $now = $ownership->now();
            $start = new \DateTimeImmutable((string) $schedule['mulai_at'], $ownership->timezone());
            $latest = new \DateTimeImmutable((string) $schedule['batas_mulai_at'], $ownership->timezone());
            if ($now < $start) {
                $db->transRollback();
                return $this->error(409, 'START_TOO_EARLY', 'Ujian belum memasuki waktu mulai.');
            }
            if ($now > $latest) {
                $db->transRollback();
                return $this->error(409, 'START_WINDOW_CLOSED', 'Batas waktu mulai telah berakhir.');
            }

            $membership = $db->query(
                "SELECT pk.*, p.username, p.credential_revision, p.credential_status, r.nama AS ruang, rb.tingkat AS current_tingkat
                 FROM peserta_kegiatan pk
                 JOIN peserta p ON p.id = pk.peserta_id
                 LEFT JOIN ruang r ON r.id = pk.ruang_id
                 JOIN rombel rb ON rb.id = p.rombel_id
                 WHERE pk.kegiatan_id = ? AND pk.peserta_id = ? FOR UPDATE",
                [(int) $schedule['kegiatan_id'], $participantId]
            )->getRowArray();
            if ($membership === null || $membership['status'] !== 'ACTIVE') {
                $db->transRollback();
                return $this->error(403, 'MEMBERSHIP_REQUIRED', 'Peserta bukan anggota aktif Kegiatan ini.');
            }
            if ((int) $membership['current_tingkat'] !== (int) $schedule['bank_tingkat']) {
                $db->transRollback();
                return $this->error(403, 'LEVEL_MISMATCH', 'Jadwal tidak sesuai tingkat peserta.');
            }
            if (trim((string) ($membership['nomor_peserta'] ?? '')) === '') {
                $db->transRollback();
                return $this->error(409, 'PARTICIPANT_NUMBER_REQUIRED', 'Nomor Peserta belum tersedia.');
            }
            if (($membership['credential_status'] ?? '') !== 'READY'
                || trim((string) ($membership['username'] ?? '')) === '') {
                $db->transRollback();
                return $this->error(409, 'CREDENTIAL_NOT_READY', 'Kredensial peserta belum siap.');
            }

            if ($schedule['jenis_jadwal'] === 'SUSULAN') {
                $target = $db->table('jadwal_peserta_target')
                    ->where('jadwal_id', $jadwalId)
                    ->where('peserta_kegiatan_id', (int) $membership['id'])
                    ->where('status', 'TARGETED')->get()->getRowArray();
                if ($target === null) {
                    $db->transRollback();
                    return $this->error(403, 'TARGET_REQUIRED', 'Peserta bukan target aktif Susulan ini.');
                }
            }

            $tokenError = $this->tokenError($db, $token);
            if ($tokenError !== null) {
                $db->transRollback();
                return $tokenError;
            }
            if ((int) $schedule['exam_browser_required'] === 1 && $examProof === '') {
                $db->transRollback();
                return $this->error(403, 'EXAM_BROWSER_REQUIRED', 'Ujian ini wajib dibuka melalui Exam Browser.');
            }

            $active = $db->table('attempt_active_lock AS l')
                ->select('a.*')->join('attempt AS a', 'a.id = l.attempt_id')
                ->where('l.peserta_id', $participantId)->where('a.status', 'ACTIVE')
                ->get()->getRowArray();
            if ($active !== null) {
                if ((int) $active['jadwal_id'] === $jadwalId
                    && (string) $active['client_uuid'] === $clientUuid) {
                    $ops->complete($db, $idempotencyKey, (int) $active['id']);
                    $db->transCommit();
                    return $this->success($active, true);
                }
                $db->transRollback();
                return $this->error(409, 'ACTIVE_ATTEMPT_EXISTS', 'Selesaikan ujian yang sedang berlangsung terlebih dahulu.');
            }

            $assignment = (new PreparationService())->readyAssignmentForStart(
                $jadwalId,
                (int) $membership['id']
            );
            if ($assignment === null) {
                $db->transRollback();
                return $this->error(409, 'NOT_PREPARED', 'Prepared Assignment belum siap atau sudah tidak sesuai.');
            }

            $rootId = $schedule['parent_jadwal_id'] === null
                ? $jadwalId
                : (int) $schedule['parent_jadwal_id'];
            $last = $db->table('attempt')
                ->selectMax('attempt_no', 'max_no')
                ->where('root_jadwal_id', $rootId)
                ->where('peserta_kegiatan_id', (int) $membership['id'])
                ->get()->getRowArray();
            $attemptNo = ((int) ($last['max_no'] ?? 0)) + 1;

            $supersedes = isset($assignment['supersede_attempt_id']) && $assignment['supersede_attempt_id'] !== null
                ? (int) $assignment['supersede_attempt_id']
                : null;
            if ($supersedes !== null) {
                $old = $db->query(
                    'SELECT id, status, peserta_kegiatan_id, root_jadwal_id FROM attempt WHERE id = ? FOR UPDATE',
                    [$supersedes]
                )->getRowArray();
                if ($old === null || $old['status'] !== 'FINISHED'
                    || (int) $old['peserta_kegiatan_id'] !== (int) $membership['id']
                    || (int) $old['root_jadwal_id'] !== $rootId) {
                    $db->transRollback();
                    return $this->error(409, 'REPLACEMENT_INVALID', 'Attempt yang akan diganti tidak valid.');
                }
            }

            $duration = (int) $schedule['durasi_seconds'];
            if ($duration < 1) {
                $db->transRollback();
                return $this->error(409, 'DURATION_INVALID', 'Durasi Jadwal tidak valid.');
            }
            $deadline = $now->add(new DateInterval('PT' . $duration . 'S'));

            $db->table('attempt')->insert([
                'peserta_id' => $participantId,
                'peserta_kegiatan_id' => (int) $membership['id'],
                'jadwal_id' => $jadwalId,
                'root_jadwal_id' => $rootId,
                'prepared_assignment_id' => (int) $assignment['id'],
                'attempt_no' => $attemptNo,
                'supersedes_attempt_id' => $supersedes,
                'status' => 'ACTIVE',
                'client_uuid' => $clientUuid,
                'client_generation' => 1,
                'auth_generation' => max(1, (int) $participant['credential_revision']),
                'start_at' => $now->format('Y-m-d H:i:s'),
                'deadline_at' => $deadline->format('Y-m-d H:i:s'),
                'duration_seconds_snapshot' => $duration,
                'nomor_peserta_snapshot' => $membership['nomor_peserta'],
                'username_snapshot' => $participant['username'],
                'nama_snapshot' => $membership['nama_snapshot'],
                'rombel_snapshot' => $membership['rombel_snapshot'],
                'ruang_snapshot' => $membership['ruang'],
            ]);
            $attemptId = (int) $db->insertID();

            $db->table('attempt_active_lock')->insert([
                'peserta_id' => $participantId,
                'attempt_id' => $attemptId,
            ]);
            $db->table('prepared_assignment')->where('id', (int) $assignment['id'])
                ->where('used_at', null)->update(['used_at' => $now->format('Y-m-d H:i:s')]);
            $db->table('jadwal')->where('id', $jadwalId)->where('first_attempt_started_at', null)
                ->update(['first_attempt_started_at' => $now->format('Y-m-d H:i:s')]);

            if ($supersedes !== null) {
                $db->table('attempt')->where('id', $supersedes)->where('status', 'FINISHED')
                    ->update(['status' => 'SUPERSEDED']);
            }

            $ops->complete($db, $idempotencyKey, $attemptId);
            if ($db->transStatus() === false || $db->transCommit() === false)
                throw new RuntimeException('Commit START gagal.');

            $attempt = $ownership->owned($db, $participantId, $attemptId);
            return $this->success($attempt, false);
        } catch (Throwable $e) {
            $db->transRollback();
            log_message('error', 'Participant START gagal: {message}', ['message' => $e->getMessage()]);
            return $this->error(409, 'START_FAILED', 'Ujian tidak dapat dimulai. Muat ulang lalu coba kembali.');
        }
    }

    private function tokenError($db, string $provided): ?array
    {
        $row = $db->table('token_control')->where('id', 1)->get()->getRowArray();
        if ($row === null || (int) $row['enabled'] !== 1) return null;
        $expected = mb_strtoupper(trim((string) ($row['current_token'] ?? '')), 'UTF-8');
        if ($expected === '' || $provided === '' || !hash_equals($expected, $provided))
            return $this->error(403, 'TOKEN_INVALID', 'Token ujian tidak sesuai.');
        return null;
    }

    private function success(?array $attempt, bool $replayed): array
    {
        if ($attempt === null) return $this->error(409, 'START_FAILED', 'Attempt tidak tersedia.');
        $ownership = new AttemptOwnershipService();
        $id = (int) $attempt['id'];
        return ['ok' => true, 'status' => 200, 'data' => [
            'attempt_id' => $id,
            'client_generation' => (int) $attempt['client_generation'],
            'start_at' => $ownership->iso((string) $attempt['start_at']),
            'deadline_at' => $ownership->iso((string) $attempt['deadline_at']),
            'bootstrap_url' => base_url('api/attempt/' . $id . '/bootstrap'),
            'redirect' => base_url('attempt/' . $id),
            'replayed' => $replayed,
        ]];
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
