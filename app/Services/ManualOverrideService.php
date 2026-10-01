<?php

namespace App\Services;

use Config\Database;
use RuntimeException;
use Throwable;

class ManualOverrideService
{
    public function apply(int $responseId, mixed $scoreValue, mixed $reasonValue, array $actor): array
    {
        if (!is_scalar($scoreValue)) {
            return $this->error(422, 'VALIDATION_FAILED', 'Nilai manual harus berupa angka.');
        }
        $scoreText = trim((string) $scoreValue);
        if (str_contains($scoreText, ',') && str_contains($scoreText, '.')) {
            return $this->error(422, 'VALIDATION_FAILED', 'Gunakan satu separator desimal: koma atau titik.');
        }
        $scoreText = str_replace(',', '.', $scoreText);
        if (!is_numeric($scoreText)) {
            return $this->error(422, 'VALIDATION_FAILED', 'Nilai manual harus berupa angka.');
        }
        $score = (float) $scoreText;
        $reason = trim(is_scalar($reasonValue) ? (string) $reasonValue : '');
        if ($reason === '' || mb_strlen($reason) > 500) {
            return $this->error(422, 'VALIDATION_FAILED', 'Alasan koreksi wajib diisi, maksimal 500 karakter.');
        }

        $db = Database::connect();
        $db->transBegin();

        try {
            $row = $db->query(
                'SELECT ar.*, a.jadwal_id, a.root_jadwal_id, a.peserta_kegiatan_id, a.prepared_assignment_id, '
                . 'a.status AS attempt_status, j.results_finalized_at, s.status AS question_status, '
                . 'sr.max_point, sr.change_kind '
                . 'FROM attempt_response ar '
                . 'JOIN attempt a ON a.id = ar.attempt_id '
                . 'JOIN jadwal j ON j.id = a.jadwal_id '
                . 'JOIN prepared_assignment_item pai ON pai.id = ar.prepared_assignment_item_id '
                . 'JOIN soal s ON s.id = pai.soal_id '
                . 'JOIN soal_revision sr ON sr.soal_id = s.id AND sr.revision_no = s.current_revision_no '
                . 'WHERE ar.id = ? FOR UPDATE',
                [$responseId]
            )->getRowArray();

            if ($row === null) {
                $db->transRollback();
                return $this->error(404, 'NOT_FOUND', 'Jawaban peserta tidak ditemukan.');
            }
            if ($row['results_finalized_at'] !== null) {
                $db->transRollback();
                return $this->error(423, 'RESULT_ALREADY_FINAL', 'Hasil Jadwal sudah FINAL dan tidak dapat dikoreksi.');
            }
            if ((string) $row['attempt_status'] !== 'FINISHED') {
                $db->transRollback();
                return $this->error(409, 'STATE_CONFLICT', 'Nilai manual hanya dapat diberikan pada Attempt yang sudah selesai.');
            }
            if ((string) ($row['question_status'] ?? '') === 'VOID'
                || (string) ($row['change_kind'] ?? '') === 'VOID') {
                $db->transRollback();
                return $this->error(423, 'QUESTION_VOID', 'Soal VOID dikeluarkan dari scoring dan tidak dapat diberi nilai manual.');
            }

            $max = max(0.0, (float) $row['max_point']);
            if ($score < 0 || $score > $max) {
                $db->transRollback();
                return $this->error(422, 'VALIDATION_FAILED', 'Nilai manual harus berada pada rentang 0 sampai ' . $this->format($max) . '.');
            }

            $oldManual = $row['manual_score'] === null ? null : (float) $row['manual_score'];
            $oldEffective = $row['effective_score'] === null ? null : (float) $row['effective_score'];

            $db->table('attempt_response')->where('id', $responseId)->update([
                'manual_score' => $score,
                'effective_score' => $score,
                'scoring_state' => 'MANUAL',
            ]);
            $db->table('score_adjustment_log')->insert([
                'attempt_response_id' => $responseId,
                'old_manual_score' => $oldManual,
                'new_manual_score' => $score,
                'old_effective_score' => $oldEffective,
                'new_effective_score' => $score,
                'reason' => $reason,
                'created_by' => (int) ($actor['user_id'] ?? 0) ?: null,
            ]);

            $attempt = $db->query('SELECT * FROM attempt WHERE id = ? FOR UPDATE', [(int) $row['attempt_id']])->getRowArray();
            if ($attempt === null) {
                throw new RuntimeException('Attempt tidak ditemukan saat membuat snapshot koreksi.');
            }

            $snapshot = (new AcademicScoringService())->snapshot($db, $attempt, true);
            $resultId = (new ResultSnapshotService())->create($db, $attempt, $snapshot, false);

            (new AuditService())->log(
                'MANAGER',
                (int) ($actor['user_id'] ?? 0),
                'MANUAL_SCORE',
                'SCORING',
                'Koreksi nilai response #' . $responseId . ' menjadi ' . $this->format($score),
                (string) ($actor['ip'] ?? ''),
                (string) ($actor['agent'] ?? ''),
                'attempt_response',
                $responseId,
                ['manual_score' => $oldManual, 'effective_score' => $oldEffective],
                ['manual_score' => $score, 'effective_score' => $score, 'reason' => $reason]
            );

            if ($db->transStatus() === false || $db->transCommit() === false) {
                throw new RuntimeException('Commit koreksi nilai gagal.');
            }

            return ['ok' => true, 'status' => 200, 'data' => [
                'response_id' => $responseId,
                'attempt_id' => (int) $attempt['id'],
                'result_snapshot_id' => $resultId,
                'manual_score' => $score,
                'scoring_status' => $snapshot['scoring_status'],
                'click_score' => $snapshot['click_score'],
                'typed_score' => $snapshot['typed_score'],
                'final_score' => $snapshot['final_score'],
            ]];
        } catch (Throwable $e) {
            $db->transRollback();
            log_message('error', 'Manual score gagal: {message}', ['message' => $e->getMessage()]);
            return $this->error(409, 'STATE_CONFLICT', 'Nilai manual belum dapat disimpan.');
        }
    }

    private function format(float $value): string
    {
        return rtrim(rtrim(number_format($value, 4, '.', ''), '0'), '.');
    }

    private function error(int $status, string $code, string $message): array
    {
        return compact('status', 'code', 'message') + ['ok' => false];
    }
}
