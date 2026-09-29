<?php

namespace App\Services;

use Config\Database;
use RuntimeException;
use Throwable;

class AttemptResumeService
{
    public function resume(int $participantId, int $attemptId, array $payload, string $idempotencyKey): array
    {
        $ops = new ParticipantOperationService();
        $ownership = new AttemptOwnershipService();
        if (!$ops->validKey($idempotencyKey))
            return $this->error(422, 'IDEMPOTENCY_REQUIRED', 'Idempotency-Key wajib dan formatnya tidak valid.');

        $clientUuid = trim($this->scalar($payload['client_uuid'] ?? null));
        $generation = is_scalar($payload['client_generation'] ?? null)
            ? (int) $payload['client_generation'] : 0;
        if (!$ownership->validClientUuid($clientUuid) || $generation < 1)
            return $this->error(422, 'CLIENT_ID_INVALID', 'Identitas client tidak valid.');

        $token = mb_strtoupper(trim($this->scalar($payload['token'] ?? null)), 'UTF-8');
        $examProof = trim($this->scalar($payload['exam_browser_proof'] ?? null));
        $hash = hash('sha256', json_encode([
            'attempt_id' => $attemptId,
            'token_hash' => hash('sha256', $token),
            'client_uuid' => $clientUuid,
            'client_generation' => $generation,
            'exam_proof_hash' => hash('sha256', $examProof),
        ], JSON_UNESCAPED_SLASHES));

        $tokenError = (new TokenService())->validateParticipantToken($token);
        if ($tokenError !== null) return $tokenError;

        $db = Database::connect(); $db->transBegin();
        try {
            $db->query('SELECT id FROM peserta WHERE id = ? FOR UPDATE', [$participantId])->getRowArray();
            $attempt = $ownership->owned($db, $participantId, $attemptId, true);
            if ($attempt === null) {
                $db->transRollback(); return $this->error(404, 'NOT_FOUND', 'Attempt tidak ditemukan.');
            }

            $operation = $ops->begin($db, $idempotencyKey, $participantId, 'RESUME_ATTEMPT', (int) $attempt['jadwal_id'], $hash);
            if (!($operation['ok'] ?? false)) {
                $db->transRollback(); return $this->error(409, 'IDEMPOTENCY_CONFLICT', 'Idempotency-Key sudah dipakai untuk request berbeda.');
            }
            if ($attempt['status'] !== 'ACTIVE') {
                $db->transRollback(); return $this->error(409, 'ATTEMPT_NOT_ACTIVE', 'Attempt sudah tidak aktif.');
            }
            if ($attempt['access_state'] !== 'BUKA') {
                $db->transRollback(); return $this->error(423, 'SCHEDULE_HELD', 'Akses ujian sedang ditahan.');
            }

            if ((int) $attempt['exam_browser_required'] === 1 && $examProof === '') {
                $db->transRollback(); return $this->error(403, 'EXAM_BROWSER_REQUIRED', 'Ujian ini wajib dibuka melalui Exam Browser.');
            }

            if ($generation !== (int) $attempt['client_generation']) {
                $db->transRollback();
                return $this->error(409, 'CLIENT_GENERATION_MISMATCH', 'Akses ujian sudah berubah. Muat ulang identitas client.');
            }

            $now = $ownership->now();
            if (!empty($attempt['pause_started_at'])) {
                $pauseStart = new \DateTimeImmutable((string) $attempt['pause_started_at'], $ownership->timezone());
                $pauseSeconds = max(0, $now->getTimestamp() - $pauseStart->getTimestamp());
                $deadline = (new \DateTimeImmutable((string) $attempt['deadline_at'], $ownership->timezone()))
                    ->modify('+' . $pauseSeconds . ' seconds');

                $db->table('attempt')->where('id', $attemptId)->update([
                    'client_uuid' => $clientUuid,
                    'pause_started_at' => null,
                    'paused_seconds' => (int) $attempt['paused_seconds'] + $pauseSeconds,
                    'deadline_at' => $deadline->format('Y-m-d H:i:s'),
                    'last_activity_at' => $now->format('Y-m-d H:i:s'),
                ]);
                $event = $db->table('attempt_pause_event')->where('attempt_id', $attemptId)
                    ->where('ended_at', null)->orderBy('id', 'DESC')->get()->getRowArray();
                if ($event !== null) {
                    $db->table('attempt_pause_event')->where('id', (int) $event['id'])->update([
                        'ended_at' => $now->format('Y-m-d H:i:s'),
                        'paused_seconds' => $pauseSeconds,
                    ]);
                }
            } else {
                $clientError = $ownership->validateClient($attempt, $clientUuid, $generation);
                if ($clientError !== null) {
                    $db->transRollback();
                    return $this->error($clientError['status'], $clientError['code'], $clientError['message']);
                }
                $db->table('attempt')->where('id', $attemptId)->update([
                    'last_activity_at' => $now->format('Y-m-d H:i:s'),
                ]);
            }

            $ops->complete($db, $idempotencyKey, $attemptId);
            if ($db->transStatus() === false || $db->transCommit() === false)
                throw new RuntimeException('Commit RESUME gagal.');

            $fresh = $ownership->owned($db, $participantId, $attemptId);
            return $this->success($fresh);
        } catch (Throwable $e) {
            $db->transRollback();
            log_message('error', 'Participant RESUME gagal: {message}', ['message' => $e->getMessage()]);
            return $this->error(409, 'RESUME_FAILED', 'Ujian tidak dapat dilanjutkan.');
        }
    }

    private function success(?array $attempt): array
    {
        if ($attempt === null) return $this->error(409, 'RESUME_FAILED', 'Attempt tidak tersedia.');
        $ownership = new AttemptOwnershipService();
        $id = (int) $attempt['id'];
        return ['ok' => true, 'status' => 200, 'data' => [
            'attempt_id' => $id,
            'client_generation' => (int) $attempt['client_generation'],
            'deadline_at' => $ownership->iso((string) $attempt['deadline_at']),
            'server_sync_revision' => (int) $attempt['server_sync_revision'],
            'bootstrap_url' => base_url('api/attempt/' . $id . '/bootstrap'),
            'redirect' => base_url('attempt/' . $id),
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
