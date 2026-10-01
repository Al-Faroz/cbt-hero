<?php

namespace App\Services;

use Config\Database;
use RuntimeException;
use Throwable;

class AdvancedQuestionService
{
    public function save(int $bankId, ?int $id, array $payload, array $actor): array
    {
        $validated = (new QuestionValidationService())->validate($payload);
        if (! $validated['ok']) return $this->error(422, 'VALIDATION_FAILED', $validated['message']);
        $data = $validated['data'];
        if ($data['question_type'] === 'PG')
            return $this->error(422, 'VALIDATION_FAILED', 'Gunakan editor PG untuk Pilihan Ganda biasa.');
        $expected = $payload['expected_revision'] ?? null;
        $version = is_scalar($expected) ? filter_var($expected, FILTER_VALIDATE_INT,
            ['options' => ['min_range' => 1]]) : false;
        if ($id !== null && ! is_int($version))
            return $this->error(422, 'VALIDATION_FAILED', 'Nomor revisi wajib untuk edit.');
        $db = Database::connect(); $db->transBegin();
        try {
            $lookup = $bankId > 0 ? $db->table('bank_soal')->select('kegiatan_id')
                ->where('id', $bankId)->get()->getRowArray() : null;
            if ($lookup === null) { $db->transRollback(); return $this->error(404, 'NOT_FOUND', 'Bank tidak ditemukan.'); }
            $bank = $db->query('SELECT id, kegiatan_id, status FROM bank_soal WHERE id = ? FOR UPDATE',
                [$bankId])->getRowArray();
            if ($bank === null || (int) $bank['kegiatan_id'] !== (int) $lookup['kegiatan_id']) {
                $db->transRollback(); return $this->error(409, 'STATE_CONFLICT', 'Bank berubah. Muat ulang.');
            }
            if ($bank['status'] !== 'DRAFT') {
                $db->transRollback(); return $this->error(423, 'DATA_LOCKED', 'Bank READY tidak dapat diubah melalui editor biasa.');
            }
            $config = $db->table('bank_type_config')->select('scoring_mode, option_count')->where('bank_soal_id', $bankId)
                ->where('question_type', $data['question_type'])->get()->getRowArray();
            if ($config === null) {
                $db->transRollback(); return $this->error(409, 'STATE_CONFLICT', 'Aktifkan tipe soal pada Komposisi dahulu.');
            }
            if (in_array($data['question_type'], ['PG_KOMPLEKS', 'PG_BERTINGKAT'], true)
                && count($data['options']) !== (int) $config['option_count']) {
                $db->transRollback(); return $this->error(422, 'VALIDATION_FAILED',
                    'Jumlah pilihan harus sesuai Komposisi Bank (' . $config['option_count'] . ').');
            }
            if ($data['question_type'] === 'MATCHING'
                && count($data['pairs']) !== (int) $config['option_count']) {
                $db->transRollback(); return $this->error(422, 'VALIDATION_FAILED',
                    'Jumlah pasangan Menjodohkan harus sesuai Komposisi Bank (' . $config['option_count'] . ').');
            }
            if ($data['question_type'] === 'MATCHING' && $data['scoring_mode'] !== $config['scoring_mode']) {
                $db->transRollback(); return $this->error(422, 'VALIDATION_FAILED', 'Mode Menjodohkan harus sama dengan Komposisi Bank.');
            }
            $old = $id === null ? null : $db->query('SELECT id, bank_soal_id, current_revision_no, status '
                . 'FROM soal WHERE id = ? FOR UPDATE', [$id])->getRowArray();
            if ($id !== null && ($old === null || (int) $old['bank_soal_id'] !== $bankId || $old['status'] !== 'ACTIVE')) {
                $db->transRollback(); return $this->error(404, 'NOT_FOUND', 'Soal tidak ditemukan pada Bank ini.');
            }
            if ($old !== null) {
                if ((int) $old['current_revision_no'] !== $version) {
                    $db->transRollback(); return $this->error(409, 'STATE_CONFLICT', 'Soal berubah. Muat ulang sebelum edit.');
                }
                $previous = $db->table('soal_revision')->select('question_type, metadata_json')
                    ->where('soal_id', $id)->where('revision_no', $version)->get()->getRowArray();
                $meta = json_decode((string) ($previous['metadata_json'] ?? ''), true);
                if ($previous === null || $previous['question_type'] !== $data['question_type']
                    || ($meta['source'] ?? null) !== 'MANUAL_PLAIN_TEXT') {
                    $db->transRollback(); return $this->error(409, 'STATE_CONFLICT', 'Tipe soal tidak dapat diganti lewat editor ini.');
                }
                $next = $version + 1;
            } else {
                $sort = $db->table('soal')->selectMax('sort_order')->where('bank_soal_id', $bankId)
                    ->get()->getRowArray();
                $db->table('soal')->insert(['bank_soal_id' => $bankId,
                    'stable_key' => bin2hex(random_bytes(16)), 'current_revision_no' => 1,
                    'status' => 'ACTIVE', 'sort_order' => ((int) ($sort['sort_order'] ?? 0)) + 1]);
                $id = (int) $db->insertID(); $next = 1;
            }
            $revision = $data;
            unset($revision['options'], $revision['pairs'], $revision['accepted']);
            $revision += ['soal_id' => $id, 'revision_no' => $next,
                'metadata_json' => json_encode(['source' => 'MANUAL_PLAIN_TEXT']),
                'change_kind' => $old === null ? 'INITIAL' : 'CONTENT',
                'created_by' => (int) ($actor['user_id'] ?? 0)];
            $db->table('soal_revision')->insert($revision);
            $revisionId = (int) $db->insertID();
            foreach ($data['options'] as $row) $db->table('soal_opsi')->insert(['soal_revision_id' => $revisionId] + $row);
            foreach ($data['pairs'] as $row) $db->table('soal_matching_pair')->insert(['soal_revision_id' => $revisionId] + $row);
            foreach ($data['accepted'] as $row) $db->table('soal_short_answer_text')->insert(['soal_revision_id' => $revisionId] + $row);
            (new QuestionMediaService())->attach($db, $revisionId, $payload, $actor);
            if ($old !== null) $db->table('soal')->where('id', $id)->update(['current_revision_no' => $next]);
            $db->table('bank_soal')->where('id', $bankId)->set('version_no', 'version_no + 1', false)
                ->update(['fingerprint' => null, 'updated_by' => (int) ($actor['user_id'] ?? 0)]);
            (new AuditService())->log('MANAGER', (int) ($actor['user_id'] ?? 0),
                ($old === null ? 'CREATE' : 'UPDATE') . '_SOAL_' . $data['question_type'], 'MASTER_UJIAN',
                'Simpan Soal #' . $id . ' Bank #' . $bankId,
                (string) ($actor['ip'] ?? ''), (string) ($actor['agent'] ?? ''), 'soal', $id);
            if ($db->transStatus() === false || $db->transCommit() === false) throw new RuntimeException('Commit soal gagal.');
        } catch (Throwable $e) {
            $db->transRollback();
            log_message('error', 'Simpan soal {type} gagal: {message}',
                ['type' => $data['question_type'], 'message' => $e->getMessage()]);
            return $this->error(409, 'STATE_CONFLICT', 'Soal tidak dapat disimpan.');
        }
        $result = (new QuestionService())->show($bankId, $id);
        $result['status'] = $old === null ? 201 : 200;
        return $result;
    }

    private function error(int $status, string $code, string $message): array
    {
        return compact('status', 'code', 'message') + ['ok' => false];
    }
}
