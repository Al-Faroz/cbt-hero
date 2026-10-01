<?php

namespace App\Services;

use Config\Database;
use RuntimeException;
use Throwable;

class BankReadinessService
{
    public function inspect(int $bankId): array
    {
        $db = Database::connect();
        $bank = $db->table('bank_soal AS b')->select('b.id, b.nama_bank, b.status, b.version_no, b.fingerprint, b.kegiatan_id, k.status AS kegiatan_status')
            ->join('kegiatan AS k', 'k.id = b.kegiatan_id')->where('b.id', $bankId)->get()->getRowArray();
        if ($bank === null) return $this->error(404, 'NOT_FOUND', 'Bank tidak ditemukan.');
        $details = $this->check($db, $bankId);
        return ['ok' => true, 'status' => 200, 'data' => ['bank' => $bank] + $details];
    }

    public function change(int $bankId, string $target, int $expected, array $actor): array
    {
        if (! in_array($target, ['READY', 'DRAFT'], true) || $expected < 1)
            return $this->error(422, 'VALIDATION_FAILED', 'Status atau versi tidak valid.');
        $db = Database::connect(); $db->transBegin();
        try {
            $lookup = $db->table('bank_soal')->select('kegiatan_id')->where('id', $bankId)->get()->getRowArray();
            if ($lookup === null) { $db->transRollback(); return $this->error(404, 'NOT_FOUND', 'Bank tidak ditemukan.'); }
            $bank = $db->query('SELECT * FROM bank_soal WHERE id = ? FOR UPDATE', [$bankId])->getRowArray();
            if ($bank === null) {
                $db->transRollback(); return $this->error(404, 'NOT_FOUND', 'Bank tidak ditemukan.');
            }
            if ((int) $bank['version_no'] !== $expected) {
                $db->transRollback(); return $this->error(409, 'STATE_CONFLICT', 'Versi Bank berubah. Muat ulang.');
            }
            if ($db->table('jadwal')->where('bank_soal_id', $bankId)->countAllResults() > 0) {
                $db->transRollback(); return $this->error(409, 'DEPENDENCY_EXISTS', 'Bank sudah dipakai Jadwal.');
            }
            if ($bank['status'] !== $target) {
                if ($target === 'READY') {
                    $result = $this->check($db, $bankId);
                    if (! $result['pass']) {
                        $db->transRollback(); return $this->error(422, 'PREFLIGHT_FAILED', implode(' ', $result['errors']));
                    }
                    $fingerprint = $result['fingerprint'];
                } else $fingerprint = null;
                $db->table('bank_soal')->where('id', $bankId)->update(['status' => $target,
                    'version_no' => $expected + 1, 'fingerprint' => $fingerprint,
                    'updated_by' => (int) ($actor['user_id'] ?? 0)]);
                (new AuditService())->log('MANAGER', (int) ($actor['user_id'] ?? 0), 'BANK_' . $target,
                    'MASTER_UJIAN', 'Bank #' . $bankId . ' menjadi ' . $target,
                    (string) ($actor['ip'] ?? ''), (string) ($actor['agent'] ?? ''), 'bank_soal', $bankId);
            }
            if ($db->transStatus() === false || $db->transCommit() === false) throw new RuntimeException('Commit gagal.');
            return $this->inspect($bankId);
        } catch (Throwable $e) {
            $db->transRollback();
            log_message('error', 'Status Bank gagal: {message}', ['message' => $e->getMessage()]);
            return $this->error(409, 'STATE_CONFLICT', 'Status Bank tidak dapat diubah.');
        }
    }

    public function validateLiveEdit($db, int $bankId): void
    {
        $result = $this->check($db, $bankId);
        if (!($result['pass'] ?? false)) {
            throw new RuntimeException(
                'Revisi Live Edit membuat Bank tidak valid: ' . implode(' ', $result['errors'] ?? [])
            );
        }

        // Bank version/fingerprint adalah baseline kontrak Preparation saat READY.
        // Live Edit tidak mengubah pool stable question atau komposisi assignment,
        // sehingga baseline tersebut sengaja tidak digeser agar Prepared Assignment
        // yang belum START tidak menjadi STALE secara massal.
    }

    private function check($db, int $bankId): array
    {
        $configs = $db->table('bank_type_config')->select('question_type, question_count, option_count, weight_percent, scoring_mode')
            ->where('bank_soal_id', $bankId)->orderBy('question_type')->get()->getResultArray();
        $questions = $db->table('soal AS s')->select('s.id, s.current_revision_no, sr.id AS revision_id, sr.question_type, sr.question_html, sr.max_point, sr.scoring_mode, sr.short_answer_mode, sr.expected_numeric, sr.numeric_tolerance, sr.rubric_html')
            ->join('soal_revision AS sr', 'sr.soal_id = s.id AND sr.revision_no = s.current_revision_no')
            ->where('s.bank_soal_id', $bankId)->where('s.status', 'ACTIVE')->orderBy('s.id')->get()->getResultArray();
        $errors = []; $counts = []; $weight = 0;
        if (!$configs) $errors[] = 'Komposisi tipe belum diatur.';
        foreach ($configs as $config) {
            $type = $config['question_type']; $count = 0;
            if (!in_array($type, QuestionValidationService::TYPES, true) || (int) $config['question_count'] < 1)
                $errors[] = 'Komposisi ' . $type . ' tidak valid.';
            foreach ($questions as $question) if ($question['question_type'] === $type) $count++;
            $counts[$type] = ['available' => $count, 'planned' => (int) $config['question_count']];
            if ($count !== (int) $config['question_count']) $errors[] = $type . ': tersedia ' . $count . ', rencana Bank ' . $config['question_count'] . '.';
            $choiceType = in_array($type, ['PG', 'PG_KOMPLEKS', 'PG_BERTINGKAT', 'MATCHING'], true);
            $maxOptions = $type === 'PG' ? 6 : ($type === 'MATCHING' ? 12 : 8);
            if (($choiceType && ((int) $config['option_count'] < 2 || (int) $config['option_count'] > $maxOptions))
                || (! $choiceType && $config['option_count'] !== null))
                $errors[] = 'Jumlah pilihan/pasangan komposisi ' . $type . ' tidak valid.';
            $weight += (int) round((float) $config['weight_percent'] * 1000);
        }
        if ($weight !== 100000) $errors[] = 'Total bobot komposisi harus 100%.';
        $types = array_column($configs, 'question_type');
        $configByType = array_column($configs, null, 'question_type');
        foreach ($questions as $question) {
            $type = $question['question_type']; $revisionId = $question['revision_id']; $id = $question['id'];
            if (!in_array($type, $types, true) || trim(strip_tags((string) $question['question_html'])) === '' || (float) $question['max_point'] <= 0) {
                $errors[] = 'Soal #' . $id . ' tidak mempunyai tipe, pertanyaan, atau poin valid.'; continue;
            }
            if (in_array($type, ['PG', 'PG_KOMPLEKS', 'PG_BERTINGKAT'], true)) {
                $options = $db->table('soal_opsi')->select('is_correct, point_value, content_html')->where('soal_revision_id', $revisionId)->get()->getResultArray();
                $correct = count(array_filter($options, fn($o) => (int) $o['is_correct'] === 1));
                if (count($options) !== (int) ($configByType[$type]['option_count'] ?? 0)
                    || count(array_filter($options, fn($o) => trim(strip_tags((string) $o['content_html'])) !== '')) !== count($options)
                    || ($type === 'PG' && $correct !== 1) || ($type === 'PG_KOMPLEKS' && ($correct < 1 || $correct === count($options)))
                    || ($type === 'PG_BERTINGKAT' && !array_filter($options, fn($o) => (float) $o['point_value'] > 0)))
                    $errors[] = 'Opsi/kunci Soal #' . $id . ' belum lengkap.';
            } elseif ($type === 'MATCHING') {
                if ($db->table('soal_matching_pair')->where('soal_revision_id', $revisionId)->countAllResults()
                        !== (int) ($configByType[$type]['option_count'] ?? 0)
                    || !in_array($question['scoring_mode'], ['PARTIAL', 'ALL_OR_NOTHING'], true)
                    || $question['scoring_mode'] !== ($configByType[$type]['scoring_mode'] ?? null))
                    $errors[] = 'Jumlah pasangan atau mode Menjodohkan Soal #' . $id . ' belum sesuai Komposisi.';
            } elseif ($type === 'ISIAN_SINGKAT') {
                if (($question['short_answer_mode'] === 'TEXT' && $db->table('soal_short_answer_text')->where('soal_revision_id', $revisionId)->countAllResults() < 1)
                    || ($question['short_answer_mode'] === 'NUMERIC' && $question['expected_numeric'] === null)
                    || !in_array($question['short_answer_mode'], ['TEXT', 'NUMERIC'], true))
                    $errors[] = 'Jawaban Isian #' . $id . ' belum lengkap.';
            }
            $linkedMedia = $db->table('soal_revision_media AS link')
                ->select('asset.storage_type, asset.media_kind, asset.provider, asset.status')
                ->join('media_assets AS asset', 'asset.id = link.media_asset_id')
                ->where('link.soal_revision_id', $revisionId)->get()->getResultArray();
            foreach ($linkedMedia as $asset) {
                if ($asset['status'] !== 'ACTIVE') {
                    $errors[] = 'Media Soal #' . $id . ' tidak aktif.'; break;
                }
                if (in_array($asset['media_kind'], ['AUDIO', 'VIDEO'], true)
                    && ($asset['storage_type'] !== 'EXTERNAL' || $asset['provider'] !== 'GDRIVE')) {
                    $errors[] = 'Audio/Video Soal #' . $id . ' wajib menggunakan link Google Drive.'; break;
                }
            }
        }
        $fingerprint = hash('sha256', json_encode([$configs, array_map(fn($q) => [$q['id'], $q['current_revision_no']], $questions)]));
        return ['pass' => !$errors, 'errors' => $errors, 'counts' => $counts, 'fingerprint' => $fingerprint];
    }

    private function error(int $status, string $code, string $message): array
    {
        return compact('status', 'code', 'message') + ['ok' => false];
    }
}
