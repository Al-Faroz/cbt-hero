<?php

namespace App\Services;

use Config\Database;
use RuntimeException;
use Throwable;

class AttemptFullscreenGuardService
{
    public function pause(
        int $participantId,
        int $attemptId,
        array $payload,
        string $clientUuid,
        int $generation,
        string $idempotencyKey
    ): array {
        $ops = new ParticipantOperationService();
        $ownership = new AttemptOwnershipService();

        if (!$ops->validKey($idempotencyKey))
            return $this->error(422, 'IDEMPOTENCY_REQUIRED', 'Idempotency-Key wajib dan formatnya tidak valid.');
        if (!$ownership->validClientUuid($clientUuid) || $generation < 1)
            return $this->error(422, 'CLIENT_ID_INVALID', 'Identitas client tidak valid.');
        if (($payload['fullscreen_active'] ?? true) !== false)
            return $this->error(422, 'FULLSCREEN_STATE_INVALID', 'Event pause harus melaporkan bahwa fullscreen telah keluar.');

        $proof = trim(is_scalar($payload['exam_browser_proof'] ?? null) ? (string) $payload['exam_browser_proof'] : '');
        $hash = hash('sha256', json_encode([
            'attempt_id' => $attemptId,
            'client_uuid' => $clientUuid,
            'generation' => $generation,
            'reason' => 'FULLSCREEN_EXIT',
            'proof_hash' => hash('sha256', $proof),
        ], JSON_UNESCAPED_SLASHES));

        $db = Database::connect();
        $db->transBegin();
        try {
            $db->query('SELECT id FROM peserta WHERE id = ? FOR UPDATE', [$participantId])->getRowArray();
            $attempt = $ownership->owned($db, $participantId, $attemptId, true);
            if ($attempt === null) {
                $db->transRollback();
                return $this->error(404, 'NOT_FOUND', 'Attempt tidak ditemukan.');
            }

            $clientError = $ownership->validateClient($attempt, $clientUuid, $generation);
            if ($clientError !== null) {
                $db->transRollback();
                return $this->error($clientError['status'], $clientError['code'], $clientError['message']);
            }
            if ($attempt['status'] !== 'ACTIVE') {
                $db->transRollback();
                return $this->error(409, 'ATTEMPT_NOT_ACTIVE', 'Attempt tidak sedang aktif.');
            }
            if ($attempt['access_state'] !== 'BUKA') {
                $db->transRollback();
                return $this->error(423, 'SCHEDULE_HELD', 'Akses ujian sedang ditahan.');
            }
            if ((int) $attempt['exam_browser_required'] === 1) {
                $db->transRollback();
                return $this->error(409, 'EXAM_BROWSER_MODE', 'Kegiatan ini menggunakan Exam Browser wajib.');
            }
            if ($proof !== '') {
                $db->transRollback();
                return $this->error(409, 'EXAM_BROWSER_CLIENT', 'Fullscreen Guard browser biasa tidak diterapkan pada client Exam Browser.');
            }

            $openPause = $db->table('attempt_pause_event')->where('attempt_id', $attemptId)
                ->where('ended_at', null)->orderBy('id', 'DESC')->get()->getRowArray();
            if (!empty($attempt['pause_started_at'])
                && (string) ($openPause['reason'] ?? '') !== 'FULLSCREEN_EXIT') {
                $db->transRollback();
                return $this->error(409, 'ATTEMPT_ALREADY_PAUSED', 'Attempt sedang dijeda oleh pengawas atau proses lain.');
            }

            $operation = $ops->begin(
                $db,
                $idempotencyKey,
                $participantId,
                'FULLSCREEN_PAUSE',
                (int) $attempt['jadwal_id'],
                $hash
            );
            if (!($operation['ok'] ?? false)) {
                $db->transRollback();
                return $this->error(409, 'IDEMPOTENCY_CONFLICT', 'Idempotency-Key sudah digunakan untuk permintaan berbeda.');
            }
            if (!empty($operation['existing']['attempt_id'])) {
                $db->transCommit();
                return ['ok' => true, 'status' => 200, 'data' => [
                    'attempt_id' => $attemptId, 'status' => 'ACTIVE', 'paused' => true,
                    'pause_reason' => 'FULLSCREEN_EXIT', 'replayed' => true,
                ]];
            }

            if (empty($attempt['pause_started_at'])) {
                $now = $ownership->now()->format('Y-m-d H:i:s');
                $db->table('attempt')->where('id', $attemptId)->update([
                    'pause_started_at' => $now,
                    'last_activity_at' => $now,
                ]);
                $db->table('attempt_pause_event')->insert([
                    'attempt_id' => $attemptId,
                    'started_at' => $now,
                    'reason' => 'FULLSCREEN_EXIT',
                ]);
            }

            $db->table('attempt_client_event')->insert([
                'attempt_id' => $attemptId,
                'client_generation' => $generation,
                'event_type' => 'FULLSCREEN_EXIT',
                'metadata_json' => json_encode([
                    'source' => 'BROWSER_FULLSCREEN_API',
                    'client_mode' => 'BROWSER',
                ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
            ]);

            $ops->complete($db, $idempotencyKey, $attemptId);
            if ($db->transStatus() === false || $db->transCommit() === false)
                throw new RuntimeException('Commit Fullscreen Pause gagal.');

            return ['ok' => true, 'status' => 200, 'data' => [
                'attempt_id' => $attemptId,
                'status' => 'ACTIVE',
                'paused' => true,
                'pause_reason' => 'FULLSCREEN_EXIT',
                'message' => 'Ujian dijeda karena fullscreen telah keluar.',
            ]];
        } catch (Throwable $e) {
            $db->transRollback();
            log_message('error', 'Fullscreen Guard pause gagal: {message}', ['message' => $e->getMessage()]);
            return $this->error(409, 'FULLSCREEN_PAUSE_FAILED', 'Ujian belum dapat dijeda. Pertahankan layar ini dan hubungi pengawas.');
        }
    }

    public function resume(
        int $participantId,
        int $attemptId,
        array $payload,
        string $clientUuid,
        int $generation,
        string $idempotencyKey
    ): array {
        $ops = new ParticipantOperationService();
        $ownership = new AttemptOwnershipService();

        if (!$ops->validKey($idempotencyKey))
            return $this->error(422, 'IDEMPOTENCY_REQUIRED', 'Idempotency-Key wajib dan formatnya tidak valid.');
        if (!$ownership->validClientUuid($clientUuid) || $generation < 1)
            return $this->error(422, 'CLIENT_ID_INVALID', 'Identitas client tidak valid.');
        if (($payload['fullscreen_active'] ?? false) !== true)
            return $this->error(423, 'FULLSCREEN_REQUIRED', 'Aktifkan layar penuh sebelum melanjutkan ujian.');

        $hash = hash('sha256', json_encode([
            'attempt_id' => $attemptId,
            'client_uuid' => $clientUuid,
            'generation' => $generation,
            'reason' => 'FULLSCREEN_RESTORE',
        ], JSON_UNESCAPED_SLASHES));
        $db = Database::connect();
        $db->transBegin();

        try {
            $db->query('SELECT id FROM peserta WHERE id = ? FOR UPDATE', [$participantId])->getRowArray();
            $attempt = $ownership->owned($db, $participantId, $attemptId, true);
            if ($attempt === null) {
                $db->transRollback();
                return $this->error(404, 'NOT_FOUND', 'Attempt tidak ditemukan.');
            }
            $clientError = $ownership->validateClient($attempt, $clientUuid, $generation);
            if ($clientError !== null) {
                $db->transRollback();
                return $this->error($clientError['status'], $clientError['code'], $clientError['message']);
            }
            if ($attempt['status'] !== 'ACTIVE' || $attempt['access_state'] !== 'BUKA') {
                $db->transRollback();
                return $this->error(423, 'ATTEMPT_NOT_RESUMABLE', 'Attempt belum dapat dilanjutkan.');
            }

            $operation = $ops->begin(
                $db, $idempotencyKey, $participantId, 'FULLSCREEN_RESUME',
                (int) $attempt['jadwal_id'], $hash
            );
            if (!($operation['ok'] ?? false)) {
                $db->transRollback();
                return $this->error(409, 'IDEMPOTENCY_CONFLICT', 'Idempotency-Key sudah digunakan untuk permintaan berbeda.');
            }
            if (!empty($operation['existing']['attempt_id'])) {
                $db->transCommit();
                return ['ok' => true, 'status' => 200, 'data' => [
                    'attempt_id' => $attemptId, 'paused' => false, 'resumed' => true,
                    'deadline_at' => $ownership->iso((string) $attempt['deadline_at']),
                ]];
            }

            $openPause = $db->table('attempt_pause_event')->where('attempt_id', $attemptId)
                ->where('ended_at', null)->orderBy('id', 'DESC')->get()->getRowArray();
            if (empty($attempt['pause_started_at']) || (string) ($openPause['reason'] ?? '') !== 'FULLSCREEN_EXIT') {
                $db->transRollback();
                return $this->error(409, 'FULLSCREEN_PAUSE_NOT_FOUND', 'Tidak ada jeda fullscreen yang perlu dipulihkan.');
            }

            $now = $ownership->now();
            $pauseStart = new \DateTimeImmutable((string) $attempt['pause_started_at'], $ownership->timezone());
            $pauseSeconds = max(0, $now->getTimestamp() - $pauseStart->getTimestamp());
            $deadline = (new \DateTimeImmutable((string) $attempt['deadline_at'], $ownership->timezone()))
                ->modify('+' . $pauseSeconds . ' seconds');
            $db->table('attempt')->where('id', $attemptId)->update([
                'pause_started_at' => null,
                'paused_seconds' => (int) $attempt['paused_seconds'] + $pauseSeconds,
                'deadline_at' => $deadline->format('Y-m-d H:i:s'),
                'last_activity_at' => $now->format('Y-m-d H:i:s'),
            ]);
            $db->table('attempt_pause_event')->where('id', (int) $openPause['id'])->update([
                'ended_at' => $now->format('Y-m-d H:i:s'),
                'paused_seconds' => $pauseSeconds,
            ]);
            $db->table('attempt_client_event')->insert([
                'attempt_id' => $attemptId,
                'client_generation' => $generation,
                'event_type' => 'FULLSCREEN_RESTORED',
                'metadata_json' => json_encode(['source' => 'BROWSER_FULLSCREEN_API']),
            ]);
            $ops->complete($db, $idempotencyKey, $attemptId);
            if ($db->transStatus() === false || $db->transCommit() === false)
                throw new RuntimeException('Commit Fullscreen Resume gagal.');

            return ['ok' => true, 'status' => 200, 'data' => [
                'attempt_id' => $attemptId,
                'paused' => false,
                'resumed' => true,
                'deadline_at' => $ownership->iso($deadline->format('Y-m-d H:i:s')),
                'message' => 'Layar penuh aktif. Ujian dilanjutkan.',
            ]];
        } catch (Throwable $e) {
            $db->transRollback();
            log_message('error', 'Fullscreen Guard resume gagal: {message}', ['message' => $e->getMessage()]);
            return $this->error(409, 'FULLSCREEN_RESUME_FAILED', 'Ujian belum dapat dilanjutkan. Hubungi pengawas.');
        }
    }

    private function error(int $status, string $code, string $message): array
    {
        return compact('status', 'code', 'message') + ['ok' => false];
    }
}
