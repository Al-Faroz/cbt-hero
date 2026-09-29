<?php

namespace App\Services;

use Config\Database;
use RuntimeException;
use Throwable;

class VoidService
{
    public function void(int $bankId, int $questionId, array $payload, array $actor): array
    {
        $note = trim(is_scalar($payload['change_note'] ?? null) ? (string) $payload['change_note'] : '');
        $expected = $payload['expected_revision'] ?? null;
        $expected = is_scalar($expected)
            ? filter_var($expected, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]])
            : false;

        if ($note === '' || mb_strlen($note) > 500) {
            return $this->error(422, 'VALIDATION_FAILED', 'Alasan VOID wajib diisi, maksimal 500 karakter.');
        }
        if (!is_int($expected)) {
            return $this->error(422, 'VALIDATION_FAILED', 'Nomor revisi saat ini wajib dikirim.');
        }

        $db = Database::connect();
        $db->transBegin();

        try {
            $bank = $db->query('SELECT * FROM bank_soal WHERE id = ? FOR UPDATE', [$bankId])->getRowArray();
            $question = $db->query(
                'SELECT * FROM soal WHERE id = ? AND bank_soal_id = ? FOR UPDATE',
                [$questionId, $bankId]
            )->getRowArray();
            if ($bank === null || $question === null || (string) $question['status'] !== 'ACTIVE') {
                $db->transRollback();
                return $this->error(404, 'NOT_FOUND', 'Bank atau Soal tidak ditemukan.');
            }
            if ((string) $bank['status'] !== 'READY') {
                $db->transRollback();
                return $this->error(409, 'USE_DRAFT_EDITOR', 'VOID operasional hanya berlaku pada Bank READY.');
            }
            if ((int) $question['current_revision_no'] !== $expected) {
                $db->transRollback();
                return $this->error(409, 'STATE_CONFLICT', 'Soal sudah mempunyai revisi lebih baru. Muat ulang.');
            }

            $current = $db->table('soal_revision')
                ->where('soal_id', $questionId)
                ->where('revision_no', $expected)
                ->get()->getRowArray();
            if ($current === null) throw new RuntimeException('Revisi aktif tidak ditemukan.');

            if ((string) $current['change_kind'] === 'VOID') {
                $db->transCommit();
                return ['ok' => true, 'status' => 200, 'data' => [
                    'bank_id' => $bankId,
                    'question_id' => $questionId,
                    'revision_id' => (int) $current['id'],
                    'revision_no' => $expected,
                    'voided' => true,
                    'repeated' => true,
                ]];
            }

            $next = $expected + 1;
            $meta = json_decode((string) ($current['metadata_json'] ?? ''), true);
            $meta = is_array($meta) ? $meta : [];
            $meta['live_edit'] = [
                'previous_revision_id' => (int) $current['id'],
                'active_answer_policy' => 'PRESERVE',
                'change_kind' => 'VOID',
            ];

            $copy = $current;
            unset($copy['id'], $copy['created_at']);
            $copy['revision_no'] = $next;
            $copy['metadata_json'] = json_encode($meta, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
            $copy['change_kind'] = 'VOID';
            $copy['change_note'] = $note;
            $copy['created_by'] = (int) ($actor['user_id'] ?? 0) ?: null;

            $db->table('soal_revision')->insert($copy);
            $revisionId = (int) $db->insertID();

            foreach ($db->table('soal_opsi')->where('soal_revision_id', (int) $current['id'])
                ->orderBy('sort_order')->get()->getResultArray() as $row) {
                unset($row['id']);
                $row['soal_revision_id'] = $revisionId;
                $db->table('soal_opsi')->insert($row);
            }
            foreach ($db->table('soal_matching_pair')->where('soal_revision_id', (int) $current['id'])
                ->orderBy('sort_order')->get()->getResultArray() as $row) {
                unset($row['id']);
                $row['soal_revision_id'] = $revisionId;
                $db->table('soal_matching_pair')->insert($row);
            }
            foreach ($db->table('soal_short_answer_text')->where('soal_revision_id', (int) $current['id'])
                ->orderBy('sort_order')->get()->getResultArray() as $row) {
                unset($row['id']);
                $row['soal_revision_id'] = $revisionId;
                $db->table('soal_short_answer_text')->insert($row);
            }
            foreach ($db->table('soal_revision_media')->where('soal_revision_id', (int) $current['id'])
                ->orderBy('sort_order')->get()->getResultArray() as $row) {
                unset($row['id']);
                $row['soal_revision_id'] = $revisionId;
                $db->table('soal_revision_media')->insert($row);
            }

            $db->table('soal')->where('id', $questionId)->update(['current_revision_no' => $next]);
            $this->touchActiveAttempts($db, $questionId);
            (new BankReadinessService())->validateLiveEdit($db, $bankId);

            (new AuditService())->log(
                'MANAGER',
                (int) ($actor['user_id'] ?? 0),
                'VOID_SOAL',
                'SCORING',
                'VOID Soal #' . $questionId . ' revisi ' . $expected . ' → ' . $next . ': ' . $note,
                (string) ($actor['ip'] ?? ''),
                (string) ($actor['agent'] ?? ''),
                'soal',
                $questionId,
                ['revision_no' => $expected, 'voided' => false],
                ['revision_no' => $next, 'voided' => true]
            );

            if ($db->transStatus() === false || $db->transCommit() === false) {
                throw new RuntimeException('Commit VOID gagal.');
            }

            return ['ok' => true, 'status' => 201, 'data' => [
                'bank_id' => $bankId,
                'question_id' => $questionId,
                'revision_id' => $revisionId,
                'revision_no' => $next,
                'voided' => true,
                'repeated' => false,
            ]];
        } catch (Throwable $e) {
            $db->transRollback();
            log_message('error', 'VOID Soal gagal: {message}', ['message' => $e->getMessage()]);
            return $this->error(409, 'STATE_CONFLICT', 'Soal belum dapat di-VOID.');
        }
    }

    private function touchActiveAttempts($db, int $questionId): void
    {
        $rows = $db->table('attempt AS a')
            ->select('DISTINCT a.id, a.server_sync_revision', false)
            ->join('prepared_assignment_item AS pai', 'pai.prepared_assignment_id = a.prepared_assignment_id')
            ->where('pai.soal_id', $questionId)
            ->where('a.status', 'ACTIVE')
            ->get()->getResultArray();

        foreach ($rows as $row) {
            $db->table('attempt')->where('id', (int) $row['id'])->update([
                'server_sync_revision' => ((int) $row['server_sync_revision']) + 1,
                'last_sync_at' => date('Y-m-d H:i:s'),
            ]);
        }
    }

    private function error(int $status, string $code, string $message): array
    {
        return compact('status', 'code', 'message') + ['ok' => false];
    }
}
