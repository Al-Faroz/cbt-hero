<?php

namespace App\Services;

use Config\Database;
use RuntimeException;
use Throwable;

class ForceFinishService
{
    public function execute(
        array $attemptIds,
        string $reason,
        bool $confirmPendingSyncRisk,
        string $idempotencyKey,
        array $actor
    ): array {
        $ids = $this->ids($attemptIds);
        if (!$ids) return $this->error(422, 'VALIDATION_FAILED', 'Pilih Attempt yang akan dipaksa selesai.');
        if (count($ids) > 100) return $this->error(422, 'VALIDATION_FAILED', 'Maksimal 100 Attempt per command.');

        $reason = mb_substr(trim($reason), 0, 255);
        if ($reason === '') $reason = 'Paksa Selesai oleh Manager';

        $ops = new AttemptControlOperationService();
        if (!$ops->validKey($idempotencyKey))
            return $this->error(422, 'IDEMPOTENCY_REQUIRED', 'Idempotency-Key wajib dan formatnya tidak valid.');

        $hash = hash('sha256', json_encode([
            'attempt_ids' => $ids,
            'reason' => $reason,
            'confirm_pending_sync_risk' => $confirmPendingSyncRisk,
        ], JSON_UNESCAPED_UNICODE));
        $db = Database::connect(); $db->transBegin();

        try {
            $operation = $ops->begin($db, $idempotencyKey, 'FORCE_FINISH', $hash, (int) ($actor['user_id'] ?? 0));
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

            $riskIds = [];
            foreach ($attempts as $attempt) {
                if (!in_array($attempt['status'], ['ACTIVE', 'FINISHED', 'SUPERSEDED'], true)) {
                    $db->transRollback();
                    return $this->error(409, 'ATTEMPT_NOT_FINISHABLE', 'Salah satu Attempt tidak dapat dipaksa selesai.');
                }
                if ($attempt['status'] === 'ACTIVE' && $this->pendingSyncRisk($attempt))
                    $riskIds[] = (int) $attempt['id'];
            }
            if ($riskIds && !$confirmPendingSyncRisk) {
                $db->transRollback();
                return [
                    'ok' => false,
                    'status' => 409,
                    'code' => 'PENDING_SYNC_RISK',
                    'message' => 'Ada Attempt dengan sinkronisasi yang belum meyakinkan. Konfirmasi risiko pending sync sebelum Paksa Selesai.',
                    'fields' => ['attempt_ids' => $riskIds],
                ];
            }

            $finalizer = new AttemptFinalizeService();
            $changed = 0;
            foreach ($attempts as $attempt) {
                $result = $finalizer->forceFinishLocked($db, $attempt);
                if ($result['changed'] ?? false) $changed++;
            }

            $ops->complete($db, $idempotencyKey, $changed);
            (new AuditService())->log(
                'MANAGER',
                (int) ($actor['user_id'] ?? 0),
                'FORCE_FINISH',
                'PELAKSANAAN',
                'Paksa Selesai ' . $changed . ' Attempt',
                (string) ($actor['ip'] ?? ''),
                (string) ($actor['agent'] ?? ''),
                'attempt',
                count($ids) === 1 ? $ids[0] : null,
                null,
                [
                    'attempt_ids' => $ids,
                    'reason' => $reason,
                    'confirmed_pending_sync_risk' => $confirmPendingSyncRisk,
                ]
            );

            if ($db->transStatus() === false || $db->transCommit() === false)
                throw new RuntimeException('Commit Paksa Selesai gagal.');

            return $this->success($changed, false);
        } catch (Throwable $e) {
            $db->transRollback();
            log_message('error', 'Paksa Selesai gagal: {message}', ['message' => $e->getMessage()]);
            return $this->error(409, 'FORCE_FINISH_FAILED', 'Attempt belum dapat dipaksa selesai.');
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

    private function pendingSyncRisk(array $attempt): bool
    {
        if (($attempt['status'] ?? '') !== 'ACTIVE') return false;
        $lastSync = strtotime((string) ($attempt['last_sync_at'] ?? ''));
        $lastActivity = strtotime((string) ($attempt['last_activity_at'] ?? ''));
        if ($lastSync === false) return true;
        if ($lastActivity !== false && $lastActivity > ($lastSync + 5)) return true;
        return (time() - $lastSync) > 20;
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
