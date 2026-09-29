<?php

namespace App\Services;

use Config\Database;
use RuntimeException;
use Throwable;

class RescoreService
{
    public function execute(int $jadwalId, string $idempotencyKey, array $actor): array
    {
        $ops = new ResultOperationService();
        if (!$ops->validKey($idempotencyKey)) {
            return $this->error(422, 'IDEMPOTENCY_REQUIRED', 'Idempotency-Key wajib dan formatnya tidak valid.');
        }

        $hash = hash('sha256', json_encode(['jadwal_id' => $jadwalId], JSON_UNESCAPED_SLASHES));
        $db = Database::connect();
        $db->transBegin();

        try {
            $jadwal = $db->query('SELECT * FROM jadwal WHERE id = ? FOR UPDATE', [$jadwalId])->getRowArray();
            if ($jadwal === null) {
                $db->transRollback();
                return $this->error(404, 'NOT_FOUND', 'Jadwal tidak ditemukan.');
            }
            if ($jadwal['psych_instrument_id'] !== null) {
                $db->transRollback();
                return $this->error(422, 'VALIDATION_FAILED', 'Rescore akademik hanya berlaku untuk Jadwal akademik.');
            }
            if ($jadwal['results_finalized_at'] !== null) {
                $db->transRollback();
                return $this->error(423, 'RESULT_ALREADY_FINAL', 'Hasil Jadwal sudah FINAL dan tidak dapat di-rescore.');
            }

            $operation = $ops->begin(
                $db,
                $idempotencyKey,
                $jadwalId,
                'RESCORE_RESULTS',
                $hash,
                (int) ($actor['user_id'] ?? 0)
            );
            if (!($operation['ok'] ?? false)) {
                $db->transRollback();
                return $this->error(409, 'IDEMPOTENCY_CONFLICT', 'Idempotency-Key sudah dipakai untuk request berbeda.');
            }
            if (($operation['existing']['status'] ?? null) === 'COMPLETED') {
                $db->transCommit();
                return ['ok' => true, 'status' => 200, 'data' => [
                    'jadwal_id' => $jadwalId,
                    'rescored_count' => 0,
                    'repeated' => true,
                ]];
            }

            $attempts = $db->table('attempt')
                ->where('jadwal_id', $jadwalId)
                ->where('status', 'FINISHED')
                ->orderBy('id', 'ASC')
                ->get()
                ->getResultArray();

            $scoring = new AcademicScoringService();
            $snapshots = new ResultSnapshotService();
            $count = 0;
            foreach ($attempts as $attempt) {
                $snapshot = $scoring->snapshot($db, $attempt, true);
                $snapshots->create($db, $attempt, $snapshot, false);
                $count++;
            }

            $ops->complete($db, $idempotencyKey, $jadwalId);
            (new AuditService())->log(
                'MANAGER',
                (int) ($actor['user_id'] ?? 0),
                'RESCORE_JADWAL',
                'SCORING',
                'Rescore ' . $count . ' Attempt selesai pada Jadwal #' . $jadwalId,
                (string) ($actor['ip'] ?? ''),
                (string) ($actor['agent'] ?? ''),
                'jadwal',
                $jadwalId,
                null,
                ['rescored_count' => $count]
            );

            if ($db->transStatus() === false || $db->transCommit() === false) {
                throw new RuntimeException('Commit rescore gagal.');
            }

            return ['ok' => true, 'status' => 200, 'data' => [
                'jadwal_id' => $jadwalId,
                'rescored_count' => $count,
                'repeated' => false,
            ]];
        } catch (Throwable $e) {
            $db->transRollback();
            log_message('error', 'Rescore Jadwal gagal: {message}', ['message' => $e->getMessage()]);
            return $this->error(409, 'STATE_CONFLICT', 'Rescore belum dapat diselesaikan.');
        }
    }

    private function error(int $status, string $code, string $message): array
    {
        return compact('status', 'code', 'message') + ['ok' => false];
    }
}
