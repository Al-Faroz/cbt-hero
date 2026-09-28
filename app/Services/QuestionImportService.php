<?php

namespace App\Services;

use CodeIgniter\HTTP\Files\UploadedFile;
use Config\Database;
use RuntimeException;
use Throwable;

class QuestionImportService
{
    private const TYPES = ['BANK_WORD', 'BANK_EXCEL'];

    public function jobs(int $bankId): array
    {
        $db = Database::connect();
        if ($db->table('bank_soal')->where('id', $bankId)->countAllResults() === 0)
            return $this->error(404, 'NOT_FOUND', 'Bank tidak ditemukan.');
        $items = $db->table('import_jobs')->select('id, original_filename, status, total_items, valid_items, invalid_items, created_at')
            ->whereIn('import_type', self::TYPES)->where('context_type', 'bank_soal')->where('context_id', $bankId)
            ->orderBy('id', 'DESC')->limit(25)->get()->getResultArray();
        return ['ok' => true, 'status' => 200, 'data' => ['items' => $items]];
    }

    public function upload(int $bankId, ?UploadedFile $file, array $actor): array
    {
        $db = Database::connect();
        $bank = $db->table('bank_soal AS b')->select('b.id, b.status, k.status AS kegiatan_status')
            ->join('kegiatan AS k', 'k.id = b.kegiatan_id')->where('b.id', $bankId)->get()->getRowArray();
        if ($bank === null) return $this->error(404, 'NOT_FOUND', 'Bank tidak ditemukan.');
        if ($bank['status'] !== 'DRAFT' || $bank['kegiatan_status'] !== 'DRAFT') return $this->error(423, 'DATA_LOCKED', 'Bank/Kegiatan terkunci.');
        $name = $file?->getClientName() ?? '';
        $extension = strtolower(pathinfo($name, PATHINFO_EXTENSION));
        if ($file === null || !$file->isValid() || !in_array($extension, ['xlsx', 'docx'], true)
            || $file->getSize() > 5 * 1024 * 1024 || $file->getSize() < 100)
            return $this->error(422, 'VALIDATION_FAILED', 'Unggah file XLSX/DOCX valid maksimal 5 MB.');
        $path = $file->getTempName();
        $media = new QuestionMediaService(); $createdMedia = [];
        try { $rows = (new QuestionDocumentService())->parse($path, $extension,
            function (string $bytes) use ($media, $actor, &$createdMedia): int {
                $id = $media->importImage($bytes, (int) ($actor['user_id'] ?? 0));
                $createdMedia[] = $id; return $id;
            }); }
        catch (Throwable $e) {
            foreach ($createdMedia as $mediaId) $media->discardImport($mediaId);
            return $this->error(422, 'IMPORT_INVALID', $e->getMessage());
        }
        $db->transBegin();
        try {
            $db->table('import_jobs')->insert(['import_type' => $extension === 'docx' ? 'BANK_WORD' : 'BANK_EXCEL', 'context_type' => 'bank_soal',
                'context_id' => $bankId, 'original_filename' => mb_substr(basename($name), 0, 255),
                'status' => 'PARSED', 'total_items' => count($rows), 'created_by' => (int) ($actor['user_id'] ?? 0)]);
            $id = (int) $db->insertID();
            foreach ($rows as $row) $db->table('import_staging_items')->insert([
                'import_job_id' => $id, 'item_no' => $row['line'],
                'source_ref' => ($extension === 'xlsx' ? 'Sheet Soal baris ' : 'Tabel Word baris ') . $row['line'],
                'payload_json' => json_encode($row['data'], JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR),
                'validation_status' => 'INVALID']);
            if ($db->transStatus() === false || $db->transCommit() === false) throw new RuntimeException('Staging gagal.');
            return $this->validate($bankId, $id);
        } catch (Throwable $e) {
            $db->transRollback(); log_message('error', 'Upload Bank Soal gagal: {message}', ['message' => $e->getMessage()]);
            foreach ($createdMedia as $mediaId) $media->discardImport($mediaId);
            return $this->error(409, 'STATE_CONFLICT', 'Staging tidak dapat dibuat.');
        }
    }

    public function job(int $bankId, int $id): array
    {
        $db = Database::connect();
        $job = $this->find($db, $bankId, $id);
        if ($job === null) return $this->error(404, 'NOT_FOUND', 'Job tidak ditemukan pada Bank ini.');
        $items = $db->table('import_staging_items')->select('id, item_no, source_ref, payload_json, validation_status, errors_json, committed_entity_id')
            ->where('import_job_id', $id)->orderBy('item_no')->get()->getResultArray();
        foreach ($items as &$item) {
            $item['payload'] = json_decode((string) $item['payload_json'], true) ?: [];
            $item['errors'] = json_decode((string) ($item['errors_json'] ?? ''), true) ?: [];
            $item['media'] = [];
            $ids = array_unique(array_column((new QuestionMediaService())->references($item['payload']), 'media_asset_id'));
            if ($ids) {
                $mediaRows = $db->table('media_assets')->select('id, media_kind, external_url')
                    ->where('status', 'ACTIVE')->whereIn('id', $ids)->get()->getResultArray();
                foreach ($mediaRows as $asset) $item['media'][(string) $asset['id']] = [
                    'kind' => $asset['media_kind'], 'url' => $asset['media_kind'] === 'VIDEO' ? $asset['external_url']
                        : base_url('manager/api/question-media/' . $asset['id'])];
            }
            unset($item['payload_json'], $item['errors_json']);
        }
        unset($item);
        return ['ok' => true, 'status' => 200, 'data' => ['job' => $job, 'items' => $items]];
    }

    public function validate(int $bankId, int $id): array
    {
        $db = Database::connect(); $db->transBegin();
        try {
            $job = $db->query('SELECT * FROM import_jobs WHERE id = ? FOR UPDATE', [$id])->getRowArray();
            if ($job === null || !in_array($job['import_type'], self::TYPES, true) || (int) $job['context_id'] !== $bankId) {
                $db->transRollback(); return $this->error(404, 'NOT_FOUND', 'Job tidak ditemukan.');
            }
            if (!in_array($job['status'], ['PARSED', 'VALIDATED'], true)) {
                $db->transRollback(); return $this->error(409, 'STATE_CONFLICT', 'Job tidak dapat divalidasi.');
            }
            $bank = $db->table('bank_soal AS b')->select('b.status, k.status AS kegiatan_status')
                ->join('kegiatan AS k', 'k.id = b.kegiatan_id')->where('b.id', $bankId)->get()->getRowArray();
            if ($bank === null || $bank['status'] !== 'DRAFT' || $bank['kegiatan_status'] !== 'DRAFT') {
                $db->transRollback(); return $this->error(423, 'DATA_LOCKED', 'Bank/Kegiatan terkunci.');
            }
            $configRows = $db->table('bank_type_config')->select('question_type, scoring_mode, option_count')->where('bank_soal_id', $bankId)
                ->get()->getResultArray();
            $configs = array_column($configRows, 'question_type');
            $configByType = array_column($configRows, null, 'question_type');
            $matchingMode = null;
            foreach ($configRows as $config) if ($config['question_type'] === 'MATCHING') $matchingMode = $config['scoring_mode'];
            $rows = $db->table('import_staging_items')->where('import_job_id', $id)->orderBy('item_no')->get()->getResultArray();
            $valid = 0; $invalid = 0;
            foreach ($rows as $row) {
                if ($row['validation_status'] === 'EXCLUDED') continue;
                $payload = json_decode((string) $row['payload_json'], true) ?: [];
                $check = (new QuestionValidationService())->validate($payload);
                $errors = [];
                if (!in_array($payload['question_type'] ?? '', $configs, true)) $errors[] = 'Tipe belum aktif di Komposisi.';
                if (!$check['ok']) $errors[] = $check['message'];
                if (($payload['question_type'] ?? '') === 'MATCHING' && ($payload['scoring_mode'] ?? '') !== $matchingMode)
                    $errors[] = 'Mode Menjodohkan harus sama dengan Komposisi Bank.';
                if (($payload['question_type'] ?? '') === 'MATCHING'
                    && is_array($payload['pairs'] ?? null)
                    && count($payload['pairs']) !== (int) ($configByType['MATCHING']['option_count'] ?? 0))
                    $errors[] = 'Jumlah pasangan Menjodohkan harus sesuai Komposisi Bank.';
                if (in_array($payload['question_type'] ?? '', ['PG', 'PG_KOMPLEKS', 'PG_BERTINGKAT'], true)
                    && is_array($payload['options'] ?? null)
                    && count($payload['options']) !== (int) ($configByType[$payload['question_type']]['option_count'] ?? 0))
                    $errors[] = 'Jumlah pilihan harus sesuai Komposisi Bank.';
                if (($payload['question_type'] ?? '') === 'PG' && (is_array($payload['options'] ?? null) && count($payload['options']) > 6 || !empty($payload['stimulus_text'])))
                    $errors[] = 'PG pada template memakai 2–6 opsi tanpa stimulus.';
                $mediaIds = array_unique(array_column((new QuestionMediaService())->references($payload), 'media_asset_id'));
                if ($mediaIds && $db->table('media_assets')->whereIn('id', $mediaIds)
                    ->where('status', 'ACTIVE')->countAllResults() !== count($mediaIds))
                    $errors[] = 'Ada referensi media tidak aktif atau tidak ditemukan.';
                if ($errors) $invalid++; else $valid++;
                $db->table('import_staging_items')->where('id', $row['id'])->update([
                    'validation_status' => $errors ? 'INVALID' : 'VALID',
                    'errors_json' => $errors ? json_encode($errors, JSON_UNESCAPED_UNICODE) : null]);
            }
            $db->table('import_jobs')->where('id', $id)->update(['status' => 'VALIDATED',
                'valid_items' => $valid, 'invalid_items' => $invalid, 'validated_at' => date('Y-m-d H:i:s')]);
            if ($db->transStatus() === false || $db->transCommit() === false) throw new RuntimeException('Validasi gagal.');
            return $this->job($bankId, $id);
        } catch (Throwable $e) {
            $db->transRollback(); log_message('error', 'Validasi impor soal gagal: {message}', ['message' => $e->getMessage()]);
            return $this->error(409, 'STATE_CONFLICT', 'Validasi staging gagal.');
        }
    }

    public function change(int $bankId, int $id, int $itemId, string $action, array $payload): array
    {
        $db = Database::connect(); $db->transBegin();
        try {
            $job = $db->query('SELECT * FROM import_jobs WHERE id = ? FOR UPDATE', [$id])->getRowArray();
            if ($job === null || !in_array($job['import_type'], self::TYPES, true) || (int) $job['context_id'] !== $bankId) {
                $db->transRollback(); return $this->error(404, 'NOT_FOUND', 'Job tidak ditemukan.');
            }
            if (!in_array($job['status'], ['PARSED', 'VALIDATED'], true)) {
                $db->transRollback(); return $this->error(409, 'STATE_CONFLICT', 'Job sudah dikunci.');
            }
            $bank = $db->table('bank_soal AS b')->select('b.status, k.status AS kegiatan_status')
                ->join('kegiatan AS k', 'k.id = b.kegiatan_id')->where('b.id', $bankId)->get()->getRowArray();
            if ($bank === null || $bank['status'] !== 'DRAFT' || $bank['kegiatan_status'] !== 'DRAFT') {
                $db->transRollback(); return $this->error(423, 'DATA_LOCKED', 'Bank/Kegiatan terkunci.');
            }
            $row = $db->table('import_staging_items')->where('import_job_id', $id)->where('id', $itemId)->get()->getRowArray();
            if ($row === null) { $db->transRollback(); return $this->error(404, 'NOT_FOUND', 'Baris tidak ditemukan.'); }
            $update = match ($action) {
                'EXCLUDE' => ['validation_status' => 'EXCLUDED', 'errors_json' => null],
                'INCLUDE' => ['validation_status' => 'INVALID', 'errors_json' => null],
                'FIX' => ['payload_json' => json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR),
                    'validation_status' => 'INVALID', 'errors_json' => null],
                default => null,
            };
            if ($update === null || ($action === 'FIX' && !$payload)) {
                $db->transRollback(); return $this->error(422, 'VALIDATION_FAILED', 'Perubahan staging tidak valid.');
            }
            $db->table('import_staging_items')->where('id', $itemId)->update($update);
            $db->table('import_jobs')->where('id', $id)->update(['status' => 'PARSED', 'valid_items' => 0, 'invalid_items' => 0]);
            if ($db->transStatus() === false || $db->transCommit() === false) throw new RuntimeException('Perubahan gagal.');
            return $this->job($bankId, $id);
        } catch (Throwable $e) {
            $db->transRollback(); return $this->error(409, 'STATE_CONFLICT', 'Baris tidak dapat diubah.');
        }
    }

    public function commit(int $bankId, int $id, array $actor, string $key): array
    {
        if (!preg_match('/^[A-Za-z0-9_-]{16,100}$/D', $key))
            return $this->error(422, 'VALIDATION_FAILED', 'Idempotency-Key wajib.');
        $db = Database::connect(); $db->transBegin();
        try {
            $job = $db->query('SELECT * FROM import_jobs WHERE id = ? FOR UPDATE', [$id])->getRowArray();
            if ($job === null || !in_array($job['import_type'], self::TYPES, true) || (int) $job['context_id'] !== $bankId) {
                $db->transRollback(); return $this->error(404, 'NOT_FOUND', 'Job tidak ditemukan.');
            }
            if ($job['status'] === 'COMMITTED') { $db->transRollback(); return $this->job($bankId, $id); }
            if ($job['status'] !== 'VALIDATED' || (int) $job['invalid_items'] > 0 || (int) $job['valid_items'] < 1) {
                $db->transRollback(); return $this->error(409, 'IMPORT_NOT_READY', 'Perbaiki atau keluarkan baris invalid, lalu validasi ulang.');
            }
            $lookup = $db->table('bank_soal')->select('kegiatan_id')->where('id', $bankId)->get()->getRowArray();
            if ($lookup === null) throw new RuntimeException('Bank hilang.');
            $activity = $db->query('SELECT status FROM kegiatan WHERE id = ? FOR UPDATE', [$lookup['kegiatan_id']])->getRowArray();
            $bank = $db->query('SELECT status FROM bank_soal WHERE id = ? FOR UPDATE', [$bankId])->getRowArray();
            if ($activity === null || $bank === null || $activity['status'] !== 'DRAFT' || $bank['status'] !== 'DRAFT')
                throw new RuntimeException('Bank/Kegiatan terkunci.');
            $rows = $db->table('import_staging_items')->where('import_job_id', $id)->where('validation_status', 'VALID')
                ->orderBy('item_no')->get()->getResultArray();
            if (count($rows) !== (int) $job['valid_items']) throw new RuntimeException('Jumlah staging berubah.');
            foreach ($rows as $row) {
                $payload = json_decode((string) $row['payload_json'], true) ?: [];
                if (($payload['question_type'] ?? '') === 'PG') {
                    unset($payload['question_type'], $payload['stimulus_text'], $payload['pairs'], $payload['scoring_mode'],
                        $payload['short_answer_mode'], $payload['accepted_values'], $payload['expected_numeric'],
                        $payload['numeric_tolerance'], $payload['rubric_text']);
                    $result = (new QuestionService())->save($bankId, null, $payload, $actor);
                } else $result = (new AdvancedQuestionService())->save($bankId, null, $payload, $actor);
                if (!$result['ok']) throw new RuntimeException($result['message']);
                $db->table('import_staging_items')->where('id', $row['id'])->update([
                    'committed_entity_type' => 'soal', 'committed_entity_id' => (int) $result['data']['item']['id']]);
            }
            $db->table('import_jobs')->where('id', $id)->update(['status' => 'COMMITTED', 'committed_at' => date('Y-m-d H:i:s')]);
            (new AuditService())->log('MANAGER', (int) ($actor['user_id'] ?? 0), 'IMPORT_BANK_SOAL',
                'MASTER_UJIAN', 'Commit impor ' . count($rows) . ' soal pada Bank #' . $bankId,
                (string) ($actor['ip'] ?? ''), (string) ($actor['agent'] ?? ''), 'import_jobs', $id);
            if ($db->transStatus() === false || $db->transCommit() === false) throw new RuntimeException('Commit gagal.');
            return $this->job($bankId, $id);
        } catch (Throwable $e) {
            $db->transRollback(); log_message('error', 'Commit impor soal gagal: {message}', ['message' => $e->getMessage()]);
            return $this->error(409, 'STATE_CONFLICT', 'Impor gagal; tidak ada soal yang ditambahkan. Validasi ulang dan coba lagi.');
        }
    }

    private function find($db, int $bankId, int $id): ?array
    {
        return $db->table('import_jobs')->where('id', $id)->whereIn('import_type', self::TYPES)
            ->where('context_type', 'bank_soal')->where('context_id', $bankId)->get()->getRowArray();
    }

    private function error(int $status, string $code, string $message): array
    {
        return compact('status', 'code', 'message') + ['ok' => false];
    }
}
