<?php

namespace App\Services;

use Config\Database;
use RuntimeException;
use Throwable;

class QuestionService
{
    private const SOURCE = 'MANUAL_PLAIN_TEXT';

    public function list(int $bankId, array $query): array
    {
        $db = Database::connect();
        $bank = $this->bank($bankId);
        if ($bank === null) return $this->error(404, 'NOT_FOUND', 'Bank Soal tidak ditemukan.');
        $page = min($this->positive($query['page'] ?? null, 1), 100000);
        $size = $this->positive($query['per_page'] ?? null, 25);
        $size = in_array($size, [25, 50, 100], true) ? $size : 25;
        $type = is_string($query['type'] ?? null) ? $query['type'] : 'PG';
        if (! in_array($type, ['ALL', 'ADVANCED'], true) && ! in_array($type, QuestionValidationService::TYPES, true))
            return $this->error(422, 'VALIDATION_FAILED', 'Filter tipe soal tidak valid.');
        $builder = $db->table('soal AS s')->join('soal_revision AS sr',
            'sr.soal_id = s.id AND sr.revision_no = s.current_revision_no')
            ->where('s.bank_soal_id', $bankId)->where('s.status', 'ACTIVE');
        if ($type === 'ADVANCED') $builder->whereIn('sr.question_type', array_slice(QuestionValidationService::TYPES, 1));
        elseif ($type !== 'ALL') $builder->where('sr.question_type', $type);
        $total = (int) $builder->countAllResults(false);
        $pages = max(1, (int) ceil($total / $size)); $page = min($page, $pages);
        $items = $builder->select('s.id, s.sort_order, s.current_revision_no, sr.question_type, sr.question_html, sr.max_point, sr.metadata_json')
            ->orderBy('s.sort_order', 'ASC')->orderBy('s.id', 'ASC')
            ->limit($size, ($page - 1) * $size)->get()->getResultArray();
        foreach ($items as &$item) {
            $item['question_text'] = ($this->metadata($item['metadata_json'])['source'] ?? '') === self::SOURCE
                ? $this->plainText($item['question_html']) : '[Konten kaya — gunakan editor lanjutan]';
            unset($item['question_html'], $item['metadata_json']);
        }
        unset($item);
        $pgConfig = $db->table('bank_type_config')->select('option_count')->where('bank_soal_id', $bankId)
            ->where('question_type', 'PG')->get()->getRowArray();
        return $this->success(['bank' => $bank, 'pg_configured' => $pgConfig !== null,
            'pg_option_count' => $pgConfig === null ? null : (int) $pgConfig['option_count'], 'items' => $items,
            'pagination' => ['page' => $page, 'pages' => $pages, 'per_page' => $size, 'total' => $total]]);
    }

    public function show(int $bankId, int $id): array
    {
        $db = Database::connect();
        $row = $db->table('soal AS s')->join('soal_revision AS sr',
            'sr.soal_id = s.id AND sr.revision_no = s.current_revision_no')
            ->select('s.id, s.bank_soal_id, s.current_revision_no, s.sort_order, sr.id AS revision_id, '
                . 'sr.question_type, sr.question_html, sr.stimulus_html, sr.max_point, '
                . 'sr.scoring_mode, sr.short_answer_mode, sr.expected_numeric, sr.numeric_tolerance, '
                . 'sr.rubric_html, sr.metadata_json')
            ->where('s.bank_soal_id', $bankId)->where('s.id', $id)->where('s.status', 'ACTIVE')
            ->get()->getRowArray();
        if ($row === null) return $this->error(404, 'NOT_FOUND', 'Soal tidak ditemukan pada Bank ini.');
        if (($this->metadata($row['metadata_json'])['source'] ?? '') !== self::SOURCE)
            return $this->error(409, 'UNSUPPORTED_CONTENT', 'Soal ini memerlukan editor rich content.');
        $options = $db->table('soal_opsi')->select('option_key, content_html, is_correct, point_value, sort_order')
            ->where('soal_revision_id', $row['revision_id'])->orderBy('sort_order', 'ASC')->get()->getResultArray();
        $row['question_text'] = $this->plainText($row['question_html']);
        $row['stimulus_text'] = $row['stimulus_html'] === null ? '' : $this->plainText($row['stimulus_html']);
        $row['rubric_text'] = $row['rubric_html'] === null ? '' : $this->plainText($row['rubric_html']);
        unset($row['metadata_json']);
        foreach ($options as &$option) $option['content_text'] = $this->plainText($option['content_html']);
        unset($option);
        $row['options'] = $options;
        $pairs = $db->table('soal_matching_pair')->select('left_key, left_html, right_key, right_html, sort_order')
            ->where('soal_revision_id', $row['revision_id'])->orderBy('sort_order')->get()->getResultArray();
        foreach ($pairs as &$pair) {
            $pair['left_text'] = $this->plainText($pair['left_html']);
            $pair['right_text'] = $this->plainText($pair['right_html']);
        }
        unset($pair);
        $row['pairs'] = $pairs;
        $row['accepted_values'] = array_column($db->table('soal_short_answer_text')
            ->select('accepted_value')->where('soal_revision_id', $row['revision_id'])
            ->orderBy('sort_order')->get()->getResultArray(), 'accepted_value');
        $media = $db->table('soal_revision_media AS link')->select('link.media_asset_id, asset.media_kind, asset.external_url, asset.status')
            ->join('media_assets AS asset', 'asset.id = link.media_asset_id')
            ->where('link.soal_revision_id', $row['revision_id'])->get()->getResultArray();
        $row['media'] = [];
        foreach ($media as $asset) if ($asset['status'] === 'ACTIVE') $row['media'][(string) $asset['media_asset_id']] = [
            'kind' => $asset['media_kind'], 'url' => $asset['media_kind'] === 'VIDEO' ? $asset['external_url']
                : base_url('manager/api/question-media/' . $asset['media_asset_id'])];
        return $this->success(['item' => $row]);
    }

    public function save(int $bankId, ?int $id, array $payload, array $actor): array
    {
        if (array_diff(array_keys($payload), ['question_text', 'options', 'correct_key', 'max_point', 'expected_revision']) !== [])
            return $this->error(422, 'VALIDATION_FAILED', 'Kolom soal tidak dikenal.');
        $mediaError = (new QuestionMediaService())->directiveError($payload);
        if ($mediaError !== null) return $this->error(422, 'VALIDATION_FAILED', $mediaError);
        $text = $this->normalText($payload['question_text'] ?? null);
        $rawOptions = $payload['options'] ?? null;
        $correct = $payload['correct_key'] ?? null;
        $point = $payload['max_point'] ?? null;
        if ($text === '' || mb_strlen($text) > 10000 || str_contains($text, "\0"))
            return $this->error(422, 'VALIDATION_FAILED', 'Pertanyaan wajib 1–10000 karakter.');
        if (! is_array($rawOptions) || count($rawOptions) < 2 || count($rawOptions) > 6)
            return $this->error(422, 'VALIDATION_FAILED', 'PG membutuhkan 2–6 opsi.');
        $options = [];
        foreach (array_values($rawOptions) as $index => $option) {
            if (! is_array($option) || array_keys($option) !== ['text'])
                return $this->error(422, 'VALIDATION_FAILED', 'Format opsi tidak valid.');
            $value = $this->normalText($option['text']);
            if ($value === '' || mb_strlen($value) > 3000 || str_contains($value, "\0"))
                return $this->error(422, 'VALIDATION_FAILED', 'Tiap opsi wajib 1–3000 karakter.');
            $options[] = ['option_key' => chr(65 + $index), 'content_html' => $this->safeHtml($value),
                'is_correct' => $correct === chr(65 + $index) ? 1 : 0, 'sort_order' => $index + 1];
        }
        if (! is_string($correct) || ! in_array($correct, array_column($options, 'option_key'), true))
            return $this->error(422, 'VALIDATION_FAILED', 'Pilih tepat satu jawaban benar.');
        if ((! is_string($point) && ! is_int($point) && ! is_float($point))
            || ! preg_match('/^(?:[0-9]{1,3}|1000)(?:\.[0-9]{1,4})?$/D', (string) $point)
            || (float) $point <= 0 || (float) $point > 1000)
            return $this->error(422, 'VALIDATION_FAILED', 'Poin harus lebih dari 0 hingga 1000 (maksimal empat desimal).');
        $point = number_format((float) $point, 4, '.', '');
        $expected = $payload['expected_revision'] ?? null;
        $revision = is_scalar($expected) ? filter_var($expected, FILTER_VALIDATE_INT,
            ['options' => ['min_range' => 1]]) : false;
        if ($id !== null && ! is_int($revision))
            return $this->error(422, 'VALIDATION_FAILED', 'Nomor revisi wajib dikirim saat edit.');

        $db = Database::connect(); $db->transBegin();
        try {
            $lookup = $this->bank($bankId);
            if ($lookup === null) { $db->transRollback(); return $this->error(404, 'NOT_FOUND', 'Bank tidak ditemukan.'); }
            $kegiatan = $db->query('SELECT status FROM kegiatan WHERE id = ? FOR UPDATE',
                [$lookup['kegiatan_id']])->getRowArray();
            $bank = $db->query('SELECT id, status FROM bank_soal WHERE id = ? FOR UPDATE', [$bankId])->getRowArray();
            if ($bank === null || $kegiatan === null) {
                $db->transRollback(); return $this->error(404, 'NOT_FOUND', 'Bank tidak ditemukan.');
            }
            if ($bank['status'] !== 'DRAFT' || $kegiatan['status'] !== 'DRAFT') {
                $db->transRollback(); return $this->error(423, 'DATA_LOCKED', 'Bank atau Kegiatan sudah terkunci.');
            }
            $config = $db->table('bank_type_config')->select('option_count')->where('bank_soal_id', $bankId)
                ->where('question_type', 'PG')->get()->getRowArray();
            if ($config === null) {
                $db->transRollback(); return $this->error(409, 'STATE_CONFLICT', 'Aktifkan tipe PG pada Komposisi terlebih dahulu.');
            }
            if (count($options) !== (int) $config['option_count']) {
                $db->transRollback(); return $this->error(422, 'VALIDATION_FAILED',
                    'Jumlah pilihan PG harus sesuai Komposisi Bank (' . $config['option_count'] . ').');
            }
            $old = $id === null ? null : $db->query('SELECT id, bank_soal_id, current_revision_no, status '
                . 'FROM soal WHERE id = ? FOR UPDATE', [$id])->getRowArray();
            if ($id !== null && ($old === null || (int) $old['bank_soal_id'] !== $bankId || $old['status'] !== 'ACTIVE')) {
                $db->transRollback(); return $this->error(404, 'NOT_FOUND', 'Soal tidak ditemukan pada Bank ini.');
            }
            if ($old !== null) {
                if ((int) $old['current_revision_no'] !== $revision) {
                    $db->transRollback(); return $this->error(409, 'STATE_CONFLICT', 'Soal telah berubah. Muat ulang sebelum mengedit.');
                }
                $previous = $db->table('soal_revision')->select('question_type, metadata_json')
                    ->where('soal_id', $id)->where('revision_no', $revision)->get()->getRowArray();
                if ($previous === null || $previous['question_type'] !== 'PG'
                    || ($this->metadata($previous['metadata_json'])['source'] ?? '') !== self::SOURCE) {
                    $db->transRollback(); return $this->error(409, 'UNSUPPORTED_CONTENT', 'Soal perlu editor rich content.');
                }
                $next = $revision + 1;
            } else {
                $sort = $db->table('soal')->selectMax('sort_order')->where('bank_soal_id', $bankId)->get()->getRowArray();
                $db->table('soal')->insert(['bank_soal_id' => $bankId, 'stable_key' => bin2hex(random_bytes(16)),
                    'current_revision_no' => 1, 'status' => 'ACTIVE', 'sort_order' => ((int) ($sort['sort_order'] ?? 0)) + 1]);
                $id = (int) $db->insertID(); $next = 1;
            }
            $db->table('soal_revision')->insert(['soal_id' => $id, 'revision_no' => $next,
                'question_type' => 'PG', 'question_html' => $this->safeHtml($text), 'max_point' => $point,
                'metadata_json' => json_encode(['source' => self::SOURCE]),
                'change_kind' => $old === null ? 'INITIAL' : 'CONTENT',
                'created_by' => (int) ($actor['user_id'] ?? 0)]);
            $revisionId = (int) $db->insertID();
            foreach ($options as $option) $db->table('soal_opsi')->insert(['soal_revision_id' => $revisionId] + $option);
            (new QuestionMediaService())->attach($db, $revisionId, $payload);
            if ($old !== null) $db->table('soal')->where('id', $id)->update(['current_revision_no' => $next]);
            $db->table('bank_soal')->where('id', $bankId)->set('version_no', 'version_no + 1', false)
                ->update(['fingerprint' => null, 'updated_by' => (int) ($actor['user_id'] ?? 0)]);
            $this->audit($bankId, $id, $old === null ? 'CREATE' : 'UPDATE', $actor);
            if ($db->transStatus() === false || $db->transCommit() === false) throw new RuntimeException('Commit soal gagal.');
        } catch (Throwable $e) {
            $db->transRollback();
            log_message('error', 'Simpan Soal PG gagal: {message}', ['message' => $e->getMessage()]);
            return $this->error(409, 'STATE_CONFLICT', 'Soal PG tidak dapat disimpan.');
        }
        $result = $this->show($bankId, $id);
        $result['status'] = $old === null ? 201 : 200;
        return $result;
    }

    public function delete(int $bankId, int $id, array $actor): array
    {
        $db = Database::connect(); $db->transBegin();
        try {
            $lookup = $this->bank($bankId);
            if ($lookup === null) { $db->transRollback(); return $this->error(404, 'NOT_FOUND', 'Bank tidak ditemukan.'); }
            $kegiatan = $db->query('SELECT status FROM kegiatan WHERE id = ? FOR UPDATE',
                [$lookup['kegiatan_id']])->getRowArray();
            $bank = $db->query('SELECT status FROM bank_soal WHERE id = ? FOR UPDATE', [$bankId])->getRowArray();
            if ($bank === null || $kegiatan === null) {
                $db->transRollback(); return $this->error(404, 'NOT_FOUND', 'Bank tidak ditemukan.');
            }
            if ($bank['status'] !== 'DRAFT' || $kegiatan['status'] !== 'DRAFT') {
                $db->transRollback(); return $this->error(423, 'DATA_LOCKED', 'Bank atau Kegiatan sudah terkunci.');
            }
            $question = $db->query('SELECT s.id, sr.question_type, sr.metadata_json FROM soal s '
                . 'JOIN soal_revision sr ON sr.soal_id = s.id AND sr.revision_no = s.current_revision_no '
                . 'WHERE s.id = ? AND s.bank_soal_id = ? AND s.status = ? FOR UPDATE',
                [$id, $bankId, 'ACTIVE'])->getRowArray();
            if ($question === null) { $db->transRollback(); return $this->error(404, 'NOT_FOUND', 'Soal tidak ditemukan.'); }
            if (($this->metadata($question['metadata_json'])['source'] ?? '') !== self::SOURCE) {
                $db->transRollback(); return $this->error(409, 'UNSUPPORTED_CONTENT', 'Soal ini memerlukan editor lain.');
            }
            if ($db->table('jadwal')->where('bank_soal_id', $bankId)->countAllResults() > 0) {
                $db->transRollback(); return $this->error(409, 'DEPENDENCY_EXISTS', 'Bank telah dipakai Jadwal.');
            }
            $db->table('soal')->where('id', $id)->delete();
            $db->table('bank_soal')->where('id', $bankId)->set('version_no', 'version_no + 1', false)
                ->update(['fingerprint' => null, 'updated_by' => (int) ($actor['user_id'] ?? 0)]);
            $this->audit($bankId, $id, 'DELETE', $actor);
            if ($db->transStatus() === false || $db->transCommit() === false) throw new RuntimeException('Commit hapus soal gagal.');
            return $this->success(['removed' => $id]);
        } catch (Throwable $e) {
            $db->transRollback();
            log_message('error', 'Hapus Soal gagal: {message}', ['message' => $e->getMessage()]);
            return $this->error(409, 'DEPENDENCY_EXISTS', 'Soal tidak dapat dihapus.');
        }
    }

    private function bank(int $id): ?array
    {
        return $id > 0 ? Database::connect()->table('bank_soal AS b')->select('b.id, b.kegiatan_id, b.nama_bank, '
            . 'b.status, b.tingkat, b.version_no, k.nama AS kegiatan_nama, k.status AS kegiatan_status, m.nama_mapel AS mapel_nama')
            ->join('kegiatan AS k', 'k.id = b.kegiatan_id')->join('mata_pelajaran AS m', 'm.id = b.mapel_id')
            ->where('b.id', $id)->get()->getRowArray() : null;
    }

    private function normalText(mixed $value): string
    {
        return is_string($value) ? trim(str_replace(["\r\n", "\r"], "\n", $value)) : '';
    }

    private function safeHtml(string $value): string
    {
        return str_replace("\n", '<br>', htmlspecialchars($value, ENT_QUOTES | ENT_SUBSTITUTE | ENT_HTML5, 'UTF-8'));
    }

    private function plainText(string $html): string
    {
        return html_entity_decode(str_replace('<br>', "\n", $html), ENT_QUOTES | ENT_HTML5, 'UTF-8');
    }

    private function metadata(?string $json): array
    {
        $data = json_decode((string) $json, true);
        return is_array($data) ? $data : [];
    }

    private function positive(mixed $value, int $default): int
    {
        $parsed = is_scalar($value) ? filter_var($value, FILTER_VALIDATE_INT,
            ['options' => ['min_range' => 1]]) : false;
        return is_int($parsed) ? $parsed : $default;
    }

    private function audit(int $bankId, int $id, string $action, array $actor): void
    {
        (new AuditService())->log('MANAGER', (int) ($actor['user_id'] ?? 0),
            $action . '_SOAL', 'MASTER_UJIAN', $action . ' Soal #' . $id . ' Bank #' . $bankId,
            (string) ($actor['ip'] ?? ''), (string) ($actor['agent'] ?? ''), 'soal', $id);
    }

    private function success(array $data): array
    {
        return ['ok' => true, 'status' => 200, 'data' => $data];
    }

    private function error(int $status, string $code, string $message): array
    {
        return compact('status', 'code', 'message') + ['ok' => false];
    }
}
