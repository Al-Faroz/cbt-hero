<?php

namespace App\Services;

use Config\Database;
use RuntimeException;
use Throwable;

class TimeAdjustmentService
{
    public function execute(array $attemptIds, int $seconds, string $reason, string $idempotencyKey, array $actor): array
    {
        $ids = $this->ids($attemptIds);
        if (!$ids) return $this->error(422, 'VALIDATION_FAILED', 'Pilih Attempt yang akan diberi tambahan waktu.');
        if (count($ids) > 100) return $this->error(422, 'VALIDATION_FAILED', 'Maksimal 100 Attempt per command.');
        if ($seconds < 1 || $seconds > 43200)
            return $this->error(422, 'VALIDATION_FAILED', 'Tambahan waktu harus 1 detik sampai 12 jam.');

        $reason = mb_substr(trim($reason), 0, 255);
        if ($reason === '') $reason = 'Tambah Waktu oleh Manager';

        $ops = new AttemptControlOperationService();
        if (!$ops->validKey($idempotencyKey))
            return $this->error(422, 'IDEMPOTENCY_REQUIRED', 'Idempotency-Key wajib dan formatnya tidak valid.');

        $hash = hash('sha256', json_encode([
            'attempt_ids' => $ids, 'seconds_added' => $seconds, 'reason' => $reason,
        ], JSON_UNESCAPED_UNICODE));
        $db = Database::connect(); $db->transBegin();

        try {
            $operation = $ops->begin($db, $idempotencyKey, 'ADD_TIME', $hash, (int) ($actor['user_id'] ?? 0));
            if (!($operation['ok'] ?? false)) {
                $db->transRollback();
                return $this->error(409, 'IDEMPOTENCY_CONFLICT', 'Idempotency-Key sudah dipakai untuk command berbeda.');
            }
            if (($operation['existing']['status'] ?? null) === 'COMPLETED') {
                $affected = (int) ($operation['existing']['affected_count'] ?? 0);
                $db->transCommit();
                return $this->success($affected, $seconds, true);
            }

            $attempts = $this->lockedAttempts($db, $ids);
            if (count($attempts) !== count($ids)) {
                $db->transRollback();
                return $this->error(404, 'NOT_FOUND', 'Sebagian Attempt tidak ditemukan.');
            }
            foreach ($attempts as $attempt) {
                if ($attempt['status'] !== 'ACTIVE') {
                    $db->transRollback();
                    return $this->error(409, 'ATTEMPT_NOT_ACTIVE', 'Tambah Waktu hanya untuk Attempt yang masih ACTIVE.');
                }
            }

            foreach ($attempts as $attempt) {
                $id = (int) $attempt['id'];
                $db->query(
                    'UPDATE attempt SET added_seconds = added_seconds + ' . $seconds
                        . ', deadline_at = DATE_ADD(deadline_at, INTERVAL ' . $seconds
                        . ' SECOND), last_activity_at = ? WHERE id = ?',
                    [date('Y-m-d H:i:s'), $id]
                );
                $db->table('attempt_time_adjustment')->insert([
                    'attempt_id' => $id,
                    'seconds_added' => $seconds,
                    'reason' => $reason,
                    'created_by' => (int) ($actor['user_id'] ?? 0) ?: null,
                ]);
            }

            $ops->complete($db, $idempotencyKey, count($attempts));
            (new AuditService())->log(
                'MANAGER',
                (int) ($actor['user_id'] ?? 0),
                'ADD_TIME',
                'PELAKSANAAN',
                'Tambah ' . $seconds . ' detik pada ' . count($attempts) . ' Attempt',
                (string) ($actor['ip'] ?? ''),
                (string) ($actor['agent'] ?? ''),
                'attempt',
                count($attempts) === 1 ? (int) $attempts[0]['id'] : null,
                null,
                ['attempt_ids' => $ids, 'seconds_added' => $seconds, 'reason' => $reason]
            );

            if ($db->transStatus() === false || $db->transCommit() === false)
                throw new RuntimeException('Commit Tambah Waktu gagal.');

            return $this->success(count($attempts), $seconds, false);
        } catch (Throwable $e) {
            $db->transRollback();
            log_message('error', 'Tambah Waktu gagal: {message}', ['message' => $e->getMessage()]);
            return $this->error(409, 'ADD_TIME_FAILED', 'Tambahan waktu tidak dapat diterapkan.');
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

    private function success(int $affected, int $seconds, bool $replayed): array
    {
        return ['ok' => true, 'status' => 200, 'data' => [
            'affected_count' => $affected,
            'seconds_added' => $seconds,
            'replayed' => $replayed,
        ]];
    }

    private function error(int $status, string $code, string $message): array
    {
        return compact('status', 'code', 'message') + ['ok' => false];
    }
}
