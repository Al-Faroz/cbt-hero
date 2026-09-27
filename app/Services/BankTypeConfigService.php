<?php

namespace App\Services;

use Config\Database;
use RuntimeException;
use Throwable;

class BankTypeConfigService
{
    private const TYPES = [
        'PG', 'PG_KOMPLEKS', 'PG_BERTINGKAT', 'MATCHING', 'ISIAN_SINGKAT', 'URAIAN',
    ];
    private const SHUFFLE_QUESTIONS = ['PG', 'PG_KOMPLEKS', 'MATCHING', 'PG_BERTINGKAT'];
    private const SHUFFLE_OPTIONS = ['PG', 'PG_KOMPLEKS', 'MATCHING', 'PG_BERTINGKAT'];

    public function read(int $bankId): array
    {
        $db = Database::connect();
        $bank = $bankId > 0 ? $db->table('bank_soal AS b')->select('b.id, b.nama_bank, b.status, b.version_no, '
            . 'b.kegiatan_id, k.nama AS kegiatan_nama, k.status AS kegiatan_status, '
            . 'm.nama_mapel AS mapel_nama, b.tingkat')
            ->join('kegiatan AS k', 'k.id = b.kegiatan_id')
            ->join('mata_pelajaran AS m', 'm.id = b.mapel_id')
            ->where('b.id', $bankId)->get()->getRowArray() : null;
        if ($bank === null) return $this->error(404, 'NOT_FOUND', 'Bank Soal tidak ditemukan.');
        $items = $db->table('bank_type_config')->select('question_type, question_count, option_count, weight_percent, '
            . 'shuffle_questions, shuffle_options, scoring_mode')
            ->where('bank_soal_id', $bankId)->get()->getResultArray();
        return ['ok' => true, 'status' => 200, 'data' => [
            'bank' => $bank, 'items' => $items, 'types' => self::TYPES,
            'editable' => $bank['status'] === 'DRAFT' && $bank['kegiatan_status'] === 'DRAFT',
        ]];
    }

    public function save(int $bankId, array $payload, array $actor): array
    {
        $expected = $payload['expected_version'] ?? null;
        $version = is_scalar($expected) ? filter_var($expected, FILTER_VALIDATE_INT,
            ['options' => ['min_range' => 1]]) : false;
        $raw = $payload['items'] ?? null;
        if (! is_int($version) || ! is_array($raw) || count($raw) > count(self::TYPES))
            return $this->error(422, 'VALIDATION_FAILED', 'Versi atau daftar tipe tidak valid.');
        $items = []; $totalWeight = 0;
        foreach ($raw as $input) {
            if (! is_array($input) || array_diff(array_keys($input), [
                'question_type', 'question_count', 'option_count', 'weight_percent',
                'shuffle_questions', 'shuffle_options', 'scoring_mode',
            ]) !== []) return $this->error(422, 'VALIDATION_FAILED', 'Kolom konfigurasi tidak dikenal.');
            $type = $input['question_type'] ?? null;
            if (! is_string($type) || ! in_array($type, self::TYPES, true) || isset($items[$type]))
                return $this->error(422, 'VALIDATION_FAILED', 'Tipe soal tidak valid atau duplikat.');
            $rawCount = $input['question_count'] ?? null;
            $count = is_scalar($rawCount) ? filter_var($rawCount, FILTER_VALIDATE_INT,
                ['options' => ['min_range' => 1, 'max_range' => 1000]]) : false;
            $choiceType = in_array($type, ['PG', 'PG_KOMPLEKS', 'PG_BERTINGKAT'], true);
            $rawOptions = $input['option_count'] ?? null;
            $optionCount = is_scalar($rawOptions) ? filter_var($rawOptions, FILTER_VALIDATE_INT,
                ['options' => ['min_range' => 2, 'max_range' => $type === 'PG' ? 6 : 8]]) : false;
            if (($choiceType && ! is_int($optionCount)) || (! $choiceType && $rawOptions !== null))
                return $this->error(422, 'VALIDATION_FAILED', 'Jumlah pilihan tidak valid untuk tipe soal.');
            $weight = $input['weight_percent'] ?? null;
            if (! is_int($count) || (! is_string($weight) && ! is_int($weight) && ! is_float($weight))
                || ! preg_match('/^(?:100(?:\.0{1,3})?|[0-9]{1,2}(?:\.[0-9]{1,3})?)$/D', (string) $weight))
                return $this->error(422, 'VALIDATION_FAILED', 'Jumlah soal atau bobot tidak valid (0–100, maksimal tiga desimal).');
            $millis = (int) round((float) $weight * 1000);
            $totalWeight += $millis;
            $questions = $input['shuffle_questions'] ?? null;
            $options = $input['shuffle_options'] ?? null;
            if (! is_bool($questions) || ! is_bool($options))
                return $this->error(422, 'VALIDATION_FAILED', 'Pengacakan harus bernilai boolean.');
            if (($questions && ! in_array($type, self::SHUFFLE_QUESTIONS, true))
                || ($options && ! in_array($type, self::SHUFFLE_OPTIONS, true)))
                return $this->error(422, 'VALIDATION_FAILED', 'Pengacakan tidak didukung pada tipe isian/uraian.');
            $mode = $input['scoring_mode'] ?? null;
            if ($type === 'MATCHING') {
                if (! in_array($mode, ['PARTIAL', 'ALL_OR_NOTHING'], true))
                    return $this->error(422, 'VALIDATION_FAILED', 'Pilih mode penilaian Matching.');
            } elseif ($mode !== null) {
                return $this->error(422, 'VALIDATION_FAILED', 'Mode penilaian tingkat Bank hanya untuk Matching.');
            }
            $items[$type] = [
                'question_type' => $type, 'question_count' => $count,
                'option_count' => $choiceType ? $optionCount : null,
                'weight_percent' => number_format($millis / 1000, 3, '.', ''),
                'shuffle_questions' => (int) $questions, 'shuffle_options' => (int) $options,
                'scoring_mode' => $mode,
            ];
        }
        if ($totalWeight > 100000)
            return $this->error(422, 'VALIDATION_FAILED', 'Total bobot tidak boleh melebihi 100%.');

        $db = Database::connect(); $db->transBegin();
        try {
            $lookup = $bankId > 0 ? $db->table('bank_soal')->select('kegiatan_id')->where('id', $bankId)->get()->getRowArray() : null;
            if ($lookup === null) { $db->transRollback(); return $this->error(404, 'NOT_FOUND', 'Bank tidak ditemukan.'); }
            $kegiatan = $db->query('SELECT status FROM kegiatan WHERE id = ? FOR UPDATE',
                [$lookup['kegiatan_id']])->getRowArray();
            $bank = $db->query('SELECT id, status, version_no, kegiatan_id FROM bank_soal WHERE id = ? FOR UPDATE',
                [$bankId])->getRowArray();
            if ($bank === null || $kegiatan === null || (int) $bank['kegiatan_id'] !== (int) $lookup['kegiatan_id']) {
                $db->transRollback(); return $this->error(409, 'STATE_CONFLICT', 'Bank berubah. Muat ulang halaman.');
            }
            if ($bank['status'] !== 'DRAFT' || $kegiatan['status'] !== 'DRAFT') {
                $db->transRollback(); return $this->error(423, 'DATA_LOCKED', 'Bank atau Kegiatan sudah terkunci.');
            }
            if ((int) $bank['version_no'] !== $version) {
                $db->transRollback(); return $this->error(409, 'STATE_CONFLICT', 'Konfigurasi telah berubah. Muat ulang sebelum menyimpan.');
            }
            $usedTypes = $db->table('soal_revision AS sr')->distinct()->select('sr.question_type')
                ->join('soal AS s', 's.id = sr.soal_id AND s.current_revision_no = sr.revision_no')
                ->where('s.bank_soal_id', $bankId)->where('s.status', 'ACTIVE')
                ->get()->getResultArray();
            foreach ($usedTypes as $used) {
                if (! isset($items[$used['question_type']])) {
                    $db->transRollback(); return $this->error(409, 'DEPENDENCY_EXISTS',
                        'Tipe yang sudah memiliki soal tidak dapat dihapus dari komposisi.');
                }
            }
            $existing = $db->table('bank_type_config')->select('id, question_type')
                ->where('bank_soal_id', $bankId)->get()->getResultArray();
            foreach ($existing as $row) {
                $type = $row['question_type'];
                if (! isset($items[$type])) $db->table('bank_type_config')->where('id', $row['id'])->delete();
                else {
                    $db->table('bank_type_config')->where('id', $row['id'])->update($items[$type]);
                    unset($items[$type]);
                }
            }
            foreach ($items as $item) $db->table('bank_type_config')->insert(['bank_soal_id' => $bankId] + $item);
            $db->table('bank_soal')->where('id', $bankId)->update([
                'version_no' => $version + 1, 'fingerprint' => null,
                'updated_by' => (int) ($actor['user_id'] ?? 0),
            ]);
            (new AuditService())->log('MANAGER', (int) ($actor['user_id'] ?? 0), 'UPDATE_BANK_TYPE_CONFIG',
                'MASTER_UJIAN', 'Ubah komposisi Bank Soal #' . $bankId,
                (string) ($actor['ip'] ?? ''), (string) ($actor['agent'] ?? ''), 'bank_soal', $bankId);
            if ($db->transStatus() === false || $db->transCommit() === false)
                throw new RuntimeException('Commit komposisi Bank gagal.');
        } catch (Throwable $e) {
            $db->transRollback();
            log_message('error', 'Simpan komposisi Bank gagal: {message}', ['message' => $e->getMessage()]);
            return $this->error(409, 'STATE_CONFLICT', 'Komposisi Bank tidak dapat disimpan.');
        }
        return $this->read($bankId);
    }

    private function error(int $status, string $code, string $message, array $fields = []): array
    {
        return compact('status', 'code', 'message', 'fields') + ['ok' => false];
    }
}
