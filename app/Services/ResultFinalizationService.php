<?php

namespace App\Services;

use Config\Database;
use RuntimeException;
use Throwable;

class ResultFinalizationService
{
    public function finalize(int $jadwalId, string $idempotencyKey, array $actor): array
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
                return $this->error(422, 'VALIDATION_FAILED', 'Finalisasi Phase 8 hanya untuk hasil akademik.');
            }
            if ($jadwal['results_finalized_at'] !== null) {
                $db->transCommit();
                return ['ok' => true, 'status' => 200, 'data' => $this->state($jadwal, true)];
            }

            $operation = $ops->begin(
                $db,
                $idempotencyKey,
                $jadwalId,
                'FINALIZE_RESULTS',
                $hash,
                (int) ($actor['user_id'] ?? 0)
            );
            if (!($operation['ok'] ?? false)) {
                $db->transRollback();
                return $this->error(409, 'IDEMPOTENCY_CONFLICT', 'Idempotency-Key sudah dipakai untuk request berbeda.');
            }

            $active = (int) $db->table('attempt')
                ->where('jadwal_id', $jadwalId)
                ->where('status', 'ACTIVE')
                ->countAllResults();
            if ($active > 0) {
                $db->transRollback();
                return $this->error(409, 'STATE_CONFLICT', 'Masih ada Attempt aktif. Selesaikan pelaksanaan sebelum finalisasi hasil.');
            }

            $attempts = $db->table('attempt')
                ->where('jadwal_id', $jadwalId)
                ->where('status', 'FINISHED')
                ->orderBy('id', 'ASC')
                ->get()
                ->getResultArray();
            if (!$attempts) {
                $db->transRollback();
                return $this->error(409, 'STATE_CONFLICT', 'Belum ada hasil Attempt yang dapat difinalkan.');
            }

            $scoring = new AcademicScoringService();
            $prepared = [];
            foreach ($attempts as $attempt) {
                $snapshot = $scoring->snapshot($db, $attempt, true);
                if ((string) $snapshot['scoring_status'] !== 'COMPLETE') {
                    $db->transRollback();
                    return $this->error(
                        409,
                        'SCORING_INCOMPLETE',
                        'Masih ada jawaban yang memerlukan penilaian atau pemeriksaan manual.'
                    );
                }
                $prepared[] = [$attempt, $snapshot];
            }

            $now = date('Y-m-d H:i:s');
            $snapshots = new ResultSnapshotService();
            foreach ($prepared as [$attempt, $snapshot]) {
                $snapshots->create($db, $attempt, $snapshot, true, $now);
            }

            $actorId = (int) ($actor['user_id'] ?? 0);
            $db->table('jadwal')->where('id', $jadwalId)->update([
                'results_finalized_at' => $now,
                'results_finalized_by' => $actorId > 0 ? $actorId : null,
            ]);
            $ops->complete($db, $idempotencyKey, $jadwalId);

            (new AuditService())->log(
                'MANAGER',
                $actorId,
                'FINALIZE_RESULTS',
                'SCORING',
                'Finalisasi hasil Jadwal #' . $jadwalId . ' untuk ' . count($prepared) . ' Attempt',
                (string) ($actor['ip'] ?? ''),
                (string) ($actor['agent'] ?? ''),
                'jadwal',
                $jadwalId,
                null,
                ['results_finalized_at' => $now, 'attempt_count' => count($prepared)]
            );

            if ($db->transStatus() === false || $db->transCommit() === false) {
                throw new RuntimeException('Commit finalisasi hasil gagal.');
            }

            $jadwal['results_finalized_at'] = $now;
            $jadwal['results_finalized_by'] = $actorId > 0 ? $actorId : null;
            return ['ok' => true, 'status' => 200, 'data' => $this->state($jadwal, false) + [
                'attempt_count' => count($prepared),
            ]];
        } catch (Throwable $e) {
            $db->transRollback();
            log_message('error', 'Finalisasi hasil gagal: {message}', ['message' => $e->getMessage()]);
            return $this->error(409, 'STATE_CONFLICT', 'Finalisasi hasil belum dapat diselesaikan.');
        }
    }

    private function state(array $jadwal, bool $repeated): array
    {
        return [
            'jadwal_id' => (int) $jadwal['id'],
            'final' => $jadwal['results_finalized_at'] !== null,
            'results_finalized_at' => $jadwal['results_finalized_at'],
            'results_finalized_by' => $jadwal['results_finalized_by'] === null
                ? null : (int) $jadwal['results_finalized_by'],
            'repeated' => $repeated,
        ];
    }

    private function error(int $status, string $code, string $message): array
    {
        return compact('status', 'code', 'message') + ['ok' => false];
    }
}
