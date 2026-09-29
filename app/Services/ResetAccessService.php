<?php

namespace App\Services;

use Config\Database;
use RuntimeException;
use Throwable;

class ResetAccessService
{
    public function execute(array $attemptIds, string $reason, string $idempotencyKey, array $actor): array
    {
        $ids = $this->ids($attemptIds);
        if (!$ids) return $this->error(422, 'VALIDATION_FAILED', 'Pilih Attempt yang akan di-Reset Akses.');
        if (count($ids) > 100) return $this->error(422, 'VALIDATION_FAILED', 'Maksimal 100 Attempt per command.');

        $reason = mb_substr(trim($reason), 0, 255);
        if ($reason === '') $reason = 'Reset Akses oleh Manager';

        $ops = new AttemptControlOperationService();
        if (!$ops->validKey($idempotencyKey))
            return $this->error(422, 'IDEMPOTENCY_REQUIRED', 'Idempotency-Key wajib dan formatnya tidak valid.');

        $hash = hash('sha256', json_encode(['attempt_ids' => $ids, 'reason' => $reason], JSON_UNESCAPED_UNICODE));
        $db = Database::connect(); $db->transBegin();

        try {
            $operation = $ops->begin($db, $idempotencyKey, 'RESET_ACCESS', $hash, (int) ($actor['user_id'] ?? 0));
            if (!($operation['ok'] ?? false)) {
                $db->transRollback();
                return $this->error(409, 'IDEMPOTENCY_CONFLICT', 'Idempotency-Key sudah dipakai untuk command berbeda.');
            }
            if (($operation['existing']['status'] ?? null) === 'COMPLETED') {
                $affected = (int) ($operation['existing']['affected_count'] ?? 0);
                $db->transCommit();
                return $this->success($affected, true);
            }

            $attempts = $this->lockedAttempts($db, $ids);
            if (count($attempts) !== count($ids)) {
                $db->transRollback();
                return $this->error(404, 'NOT_FOUND', 'Sebagian Attempt tidak ditemukan.');
            }

            $now = date('Y-m-d H:i:s');
            foreach ($attempts as $attempt) {
                if ($attempt['status'] !== 'ACTIVE') {
                    $db->transRollback();
                    return $this->error(409, 'ATTEMPT_NOT_ACTIVE', 'Reset Akses hanya untuk Attempt yang masih ACTIVE.');
                }
                if ($attempt['pause_started_at'] !== null) {
                    $db->transRollback();
                    return $this->error(409, 'ACCESS_ALREADY_RESET', 'Salah satu Attempt sudah menunggu login ulang setelah Reset Akses.');
                }
            }

            foreach ($attempts as $attempt) {
                $id = (int) $attempt['id'];
                $db->table('attempt')->where('id', $id)->update([
                    'pause_started_at' => $now,
                    'client_uuid' => null,
                    'client_generation' => (int) $attempt['client_generation'] + 1,
                    'last_activity_at' => $now,
                ]);
                $db->table('attempt_pause_event')->insert([
                    'attempt_id' => $id,
                    'started_at' => $now,
                    'reason' => $reason,
                    'started_by' => (int) ($actor['user_id'] ?? 0) ?: null,
                ]);
                $db->table('attempt_client_event')->insert([
                    'attempt_id' => $id,
                    'client_generation' => (int) $attempt['client_generation'] + 1,
                    'event_type' => 'RESET_ACCESS',
                    'metadata_json' => json_encode(['reason' => $reason], JSON_UNESCAPED_UNICODE),
                ]);
            }

            $ops->complete($db, $idempotencyKey, count($attempts));
            $this->audit($attempts, $reason, $actor);

            if ($db->transStatus() === false || $db->transCommit() === false)
                throw new RuntimeException('Commit Reset Akses gagal.');

            return $this->success(count($attempts), false);
        } catch (Throwable $e) {
            $db->transRollback();
            log_message('error', 'Reset Akses gagal: {message}', ['message' => $e->getMessage()]);
            return $this->error(409, 'RESET_ACCESS_FAILED', 'Reset Akses tidak dapat diterapkan.');
        }
    }

    private function lockedAttempts($db, array $ids): array
    {
        $marks = implode(',', array_fill(0, count($ids), '?'));
        return $db->query(
            'SELECT * FROM attempt WHERE id IN (' . $marks . ') ORDER BY id FOR UPDATE',
            $ids
        )->getResultArray();
    }

    private function ids(array $values): array
    {
        $ids = array_values(array_unique(array_filter(array_map(
            static fn($value): int => is_scalar($value) ? (int) $value : 0,
            $values
        ), static fn(int $value): bool => $value > 0)));
        sort($ids);
        return $ids;
    }

    private function audit(array $attempts, string $reason, array $actor): void
    {
        (new AuditService())->log(
            'MANAGER',
            (int) ($actor['user_id'] ?? 0),
            'RESET_ACCESS',
            'PELAKSANAAN',
            'Reset Akses ' . count($attempts) . ' Attempt',
            (string) ($actor['ip'] ?? ''),
            (string) ($actor['agent'] ?? ''),
            'attempt',
            count($attempts) === 1 ? (int) $attempts[0]['id'] : null,
            null,
            ['attempt_ids' => array_map(static fn(array $a): int => (int) $a['id'], $attempts), 'reason' => $reason]
        );
    }

    private function success(int $affected, bool $replayed): array
    {
        return ['ok' => true, 'status' => 200, 'data' => [
            'affected_count' => $affected,
            'replayed' => $replayed,
        ]];
    }

    private function error(int $status, string $code, string $message): array
    {
        return compact('status', 'code', 'message') + ['ok' => false];
    }
}
