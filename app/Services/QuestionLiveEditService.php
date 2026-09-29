<?php

namespace App\Services;

use Config\Database;
use RuntimeException;
use Throwable;

class QuestionLiveEditService
{
    private const KINDS = ['CONTENT', 'KEY_WEIGHT', 'STRUCTURAL'];
    private const POLICIES = ['PRESERVE', 'REANSWER'];

    public function revisions(int $bankId, int $questionId): array
    {
        $db = Database::connect();
        $question = $db->table('soal')
            ->select('id, bank_soal_id, current_revision_no, status')
            ->where('id', $questionId)->where('bank_soal_id', $bankId)
            ->get()->getRowArray();
        if ($question === null) {
            return $this->error(404, 'NOT_FOUND', 'Soal tidak ditemukan pada Bank ini.');
        }

        $rows = $db->table('soal_revision')
            ->select('id, revision_no, question_type, max_point, scoring_mode, short_answer_mode, '
                . 'change_kind, change_note, metadata_json, created_by, created_at')
            ->where('soal_id', $questionId)
            ->orderBy('revision_no', 'DESC')
            ->get()->getResultArray();
        foreach ($rows as &$row) {
            $meta = json_decode((string) ($row['metadata_json'] ?? ''), true);
            $live = is_array($meta) && is_array($meta['live_edit'] ?? null) ? $meta['live_edit'] : [];
            $row['is_current'] = (int) $row['revision_no'] === (int) $question['current_revision_no'];
            $row['active_answer_policy'] = $live['active_answer_policy'] ?? null;
            unset($row['metadata_json']);
        }
        unset($row);

        return ['ok' => true, 'status' => 200, 'data' => [
            'question' => $question,
            'items' => $rows,
        ]];
    }

    public function revise(int $bankId, int $questionId, array $payload, array $actor): array
    {
        $kind = strtoupper(trim($this->scalar($payload['change_kind'] ?? '')));
        $note = trim($this->scalar($payload['change_note'] ?? ''));
        $policy = strtoupper(trim($this->scalar($payload['active_answer_policy'] ?? '')));
        $revisionPayload = $payload['revision'] ?? null;

        if (!in_array($kind, self::KINDS, true)) {
            return $this->error(422, 'VALIDATION_FAILED', 'Jenis perubahan harus CONTENT, KEY_WEIGHT, atau STRUCTURAL.');
        }
        if ($note === '' || mb_strlen($note) > 500) {
            return $this->error(422, 'VALIDATION_FAILED', 'Catatan perubahan wajib diisi, maksimal 500 karakter.');
        }
        if (!is_array($revisionPayload)) {
            return $this->error(422, 'VALIDATION_FAILED', 'Data revisi soal wajib dikirim.');
        }

        $validated = (new QuestionValidationService())->validate($revisionPayload);
        if (!($validated['ok'] ?? false)) {
            return $this->error(422, 'VALIDATION_FAILED', (string) ($validated['message'] ?? 'Data revisi tidak valid.'));
        }
        $data = $validated['data'];

        $expected = $payload['expected_revision'] ?? ($revisionPayload['expected_revision'] ?? null);
        $expected = is_scalar($expected)
            ? filter_var($expected, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]])
            : false;
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
                return $this->error(409, 'USE_DRAFT_EDITOR', 'Live Edit hanya digunakan untuk Bank READY. Gunakan editor biasa saat Bank DRAFT.');
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
            if ((string) $current['question_type'] !== (string) $data['question_type']) {
                $db->transRollback();
                return $this->error(422, 'STRUCTURAL_TYPE_CHANGE_FORBIDDEN', 'Tipe soal tidak boleh diganti melalui Live Edit.');
            }

            $config = $db->table('bank_type_config')
                ->where('bank_soal_id', $bankId)
                ->where('question_type', (string) $data['question_type'])
                ->get()->getRowArray();
            if ($config === null) {
                $db->transRollback();
                return $this->error(409, 'STATE_CONFLICT', 'Komposisi tipe soal tidak tersedia.');
            }
            $configError = $this->configError($data, $config);
            if ($configError !== null) {
                $db->transRollback();
                return $this->error(422, 'VALIDATION_FAILED', $configError);
            }

            $old = $this->definition($db, $current);
            $oldShape = $this->shapeSignature($old);
            $newShape = $this->shapeSignature($data);
            $oldScoring = $this->scoringSignature($old);
            $newScoring = $this->scoringSignature($data);

            if ($kind === 'CONTENT' && ($oldShape !== $newShape || $oldScoring !== $newScoring)) {
                $db->transRollback();
                return $this->error(422, 'CHANGE_KIND_MISMATCH', 'CONTENT hanya boleh mengubah isi/tampilan tanpa mengubah struktur, kunci, atau bobot.');
            }
            if ($kind === 'KEY_WEIGHT' && $oldShape !== $newShape) {
                $db->transRollback();
                return $this->error(422, 'CHANGE_KIND_MISMATCH', 'KEY_WEIGHT tidak boleh mengubah struktur jawaban.');
            }

            $activeCount = (int) $db->table('attempt AS a')
                ->join('prepared_assignment_item AS pai', 'pai.prepared_assignment_id = a.prepared_assignment_id')
                ->where('pai.soal_id', $questionId)
                ->where('a.status', 'ACTIVE')
                ->countAllResults();
            if ($kind === 'STRUCTURAL' && $activeCount > 0 && !in_array($policy, self::POLICIES, true)) {
                $db->transRollback();
                return $this->error(422, 'ACTIVE_ANSWER_POLICY_REQUIRED', 'Pilih PRESERVE atau REANSWER untuk perubahan struktur saat ada Attempt aktif.');
            }
            if ($policy === '') $policy = 'PRESERVE';
            if (!in_array($policy, self::POLICIES, true)) {
                $db->transRollback();
                return $this->error(422, 'VALIDATION_FAILED', 'Kebijakan jawaban aktif tidak valid.');
            }

            $next = $expected + 1;
            $previousMeta = json_decode((string) ($current['metadata_json'] ?? ''), true);
            $meta = is_array($previousMeta) ? $previousMeta : [];
            $meta['live_edit'] = [
                'previous_revision_id' => (int) $current['id'],
                'active_answer_policy' => $policy,
                'change_kind' => $kind,
            ];

            $revision = $data;
            unset($revision['options'], $revision['pairs'], $revision['accepted']);
            $revision += [
                'soal_id' => $questionId,
                'revision_no' => $next,
                'rubric_json' => $current['rubric_json'] ?? null,
                'metadata_json' => json_encode($meta, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
                'change_kind' => $kind,
                'change_note' => $note,
                'created_by' => (int) ($actor['user_id'] ?? 0) ?: null,
            ];

            $db->table('soal_revision')->insert($revision);
            $revisionId = (int) $db->insertID();
            foreach ($data['options'] as $row) {
                $db->table('soal_opsi')->insert(['soal_revision_id' => $revisionId] + $row);
            }
            foreach ($data['pairs'] as $row) {
                $db->table('soal_matching_pair')->insert(['soal_revision_id' => $revisionId] + $row);
            }
            foreach ($data['accepted'] as $row) {
                $db->table('soal_short_answer_text')->insert(['soal_revision_id' => $revisionId] + $row);
            }
            (new QuestionMediaService())->attach($db, $revisionId, $revisionPayload, $actor);

            $db->table('soal')->where('id', $questionId)->update(['current_revision_no' => $next]);
            $this->refreshAssignments($db, $questionId, $revisionId, $data, $kind);
            if ($kind === 'STRUCTURAL' && $policy === 'REANSWER') {
                $this->resetActiveAnswers($db, $questionId, $actor);
            }

            (new BankReadinessService())->refreshFingerprintAfterLiveEdit(
                $db,
                $bankId,
                (int) ($actor['user_id'] ?? 0)
            );

            (new AuditService())->log(
                'MANAGER',
                (int) ($actor['user_id'] ?? 0),
                'LIVE_EDIT_' . $kind,
                'SCORING',
                'Live Edit Soal #' . $questionId . ' revisi ' . $expected . ' → ' . $next . ': ' . $note,
                (string) ($actor['ip'] ?? ''),
                (string) ($actor['agent'] ?? ''),
                'soal',
                $questionId,
                ['revision_no' => $expected],
                ['revision_no' => $next, 'change_kind' => $kind, 'active_answer_policy' => $policy]
            );

            if ($db->transStatus() === false || $db->transCommit() === false) {
                throw new RuntimeException('Commit Live Edit gagal.');
            }

            return ['ok' => true, 'status' => 201, 'data' => [
                'bank_id' => $bankId,
                'question_id' => $questionId,
                'revision_id' => $revisionId,
                'revision_no' => $next,
                'change_kind' => $kind,
                'active_answer_policy' => $policy,
                'active_attempt_count' => $activeCount,
            ]];
        } catch (Throwable $e) {
            $db->transRollback();
            log_message('error', 'Live Edit Soal gagal: {message}', ['message' => $e->getMessage()]);
            return $this->error(409, 'STATE_CONFLICT', 'Revisi Live Edit belum dapat disimpan.');
        }
    }

    private function definition($db, array $revision): array
    {
        $id = (int) $revision['id'];
        $data = [
            'question_type' => (string) $revision['question_type'],
            'max_point' => (string) $revision['max_point'],
            'scoring_mode' => $revision['scoring_mode'],
            'short_answer_mode' => $revision['short_answer_mode'],
            'expected_numeric' => $revision['expected_numeric'],
            'numeric_tolerance' => $revision['numeric_tolerance'],
            'rubric_html' => $revision['rubric_html'],
            'options' => [],
            'pairs' => [],
            'accepted' => [],
        ];
        $data['options'] = $db->table('soal_opsi')
            ->select('option_key, is_correct, point_value, sort_order')
            ->where('soal_revision_id', $id)->orderBy('sort_order')->get()->getResultArray();
        $data['pairs'] = $db->table('soal_matching_pair')
            ->select('left_key, right_key, sort_order')
            ->where('soal_revision_id', $id)->orderBy('sort_order')->get()->getResultArray();
        $data['accepted'] = $db->table('soal_short_answer_text')
            ->select('accepted_value, normalized_value, sort_order')
            ->where('soal_revision_id', $id)->orderBy('sort_order')->get()->getResultArray();
        return $data;
    }

    private function shapeSignature(array $data): string
    {
        $type = (string) $data['question_type'];
        $shape = ['type' => $type];
        if (in_array($type, ['PG', 'PG_KOMPLEKS', 'PG_BERTINGKAT'], true)) {
            $shape['option_count'] = count($data['options'] ?? []);
        } elseif ($type === 'MATCHING') {
            $shape['pair_count'] = count($data['pairs'] ?? []);
        } elseif ($type === 'ISIAN_SINGKAT') {
            $shape['short_answer_mode'] = (string) ($data['short_answer_mode'] ?? '');
        }
        return hash('sha256', json_encode($shape, JSON_UNESCAPED_SLASHES));
    }

    private function scoringSignature(array $data): string
    {
        $type = (string) $data['question_type'];
        $score = ['type' => $type, 'max_point' => (string) $data['max_point']];
        if (in_array($type, ['PG', 'PG_KOMPLEKS'], true)) {
            $score['correct'] = array_map(
                static fn(array $row): int => (int) ($row['is_correct'] ?? 0),
                $data['options'] ?? []
            );
        } elseif ($type === 'PG_BERTINGKAT') {
            $score['points'] = array_map(
                static fn(array $row): string => (string) ($row['point_value'] ?? '0'),
                $data['options'] ?? []
            );
        } elseif ($type === 'MATCHING') {
            $score['scoring_mode'] = (string) ($data['scoring_mode'] ?? '');
        } elseif ($type === 'ISIAN_SINGKAT') {
            $score['mode'] = (string) ($data['short_answer_mode'] ?? '');
            $score['expected_numeric'] = (string) ($data['expected_numeric'] ?? '');
            $score['numeric_tolerance'] = (string) ($data['numeric_tolerance'] ?? '');
            $score['accepted'] = array_map(
                static fn(array $row): string => (string) ($row['normalized_value'] ?? $row['accepted_value'] ?? ''),
                $data['accepted'] ?? []
            );
        } elseif ($type === 'URAIAN') {
            $score['rubric'] = (string) ($data['rubric_html'] ?? '');
        }
        return hash('sha256', json_encode($score, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));
    }

    private function configError(array $data, array $config): ?string
    {
        $type = (string) $data['question_type'];
        if (in_array($type, ['PG', 'PG_KOMPLEKS', 'PG_BERTINGKAT'], true)
            && count($data['options']) !== (int) $config['option_count']) {
            return 'Jumlah pilihan harus tetap sesuai Komposisi Bank (' . $config['option_count'] . ').';
        }
        if ($type === 'MATCHING') {
            if (count($data['pairs']) !== (int) $config['option_count']) {
                return 'Jumlah pasangan harus tetap sesuai Komposisi Bank (' . $config['option_count'] . ').';
            }
            if ((string) $data['scoring_mode'] !== (string) $config['scoring_mode']) {
                return 'Mode Menjodohkan harus tetap sesuai Komposisi Bank.';
            }
        }
        return null;
    }

    private function refreshAssignments($db, int $questionId, int $revisionId, array $data, string $kind): void
    {
        $items = $db->table('prepared_assignment_item AS pai')
            ->select('pai.id, pai.prepared_assignment_id, pai.option_order_json, pai.mapping_json')
            ->join('prepared_assignment AS pa', 'pa.id = pai.prepared_assignment_id')
            ->join('jadwal AS j', 'j.id = pa.generated_for_jadwal_id')
            ->where('pai.soal_id', $questionId)
            ->where('j.results_finalized_at', null)
            ->get()->getResultArray();

        if ($kind === 'STRUCTURAL') {
            foreach ($items as $item) {
                $update = [];
                if (in_array($data['question_type'], ['PG', 'PG_KOMPLEKS', 'PG_BERTINGKAT'], true)) {
                    $keys = array_column($data['options'], 'option_key');
                    $order = json_decode((string) ($item['option_order_json'] ?? ''), true);
                    $order = is_array($order) ? array_values(array_intersect($order, $keys)) : [];
                    foreach ($keys as $key) if (!in_array($key, $order, true)) $order[] = $key;
                    $update['option_order_json'] = json_encode($order, JSON_UNESCAPED_SLASHES);
                } elseif ($data['question_type'] === 'MATCHING') {
                    $left = array_column($data['pairs'], 'left_key');
                    $right = array_column($data['pairs'], 'right_key');
                    $map = json_decode((string) ($item['mapping_json'] ?? ''), true);
                    $map = is_array($map) ? $map : [];
                    $leftOrder = is_array($map['left_order'] ?? null)
                        ? array_values(array_intersect($map['left_order'], $left)) : [];
                    $rightOrder = is_array($map['right_order'] ?? null)
                        ? array_values(array_intersect($map['right_order'], $right)) : [];
                    $leftAlias = is_array($map['left_alias'] ?? null) ? array_intersect_key($map['left_alias'], array_flip($left)) : [];
                    $rightAlias = is_array($map['right_alias'] ?? null) ? array_intersect_key($map['right_alias'], array_flip($right)) : [];
                    foreach ($left as $key) {
                        if (!in_array($key, $leftOrder, true)) $leftOrder[] = $key;
                        if (!isset($leftAlias[$key])) $leftAlias[$key] = 'L_' . bin2hex(random_bytes(8));
                    }
                    foreach ($right as $key) {
                        if (!in_array($key, $rightOrder, true)) $rightOrder[] = $key;
                        if (!isset($rightAlias[$key])) $rightAlias[$key] = 'R_' . bin2hex(random_bytes(8));
                    }
                    $update['mapping_json'] = json_encode([
                        'left_order' => $leftOrder,
                        'right_order' => $rightOrder,
                        'left_alias' => $leftAlias,
                        'right_alias' => $rightAlias,
                    ], JSON_UNESCAPED_SLASHES);
                }
                if ($update) $db->table('prepared_assignment_item')->where('id', (int) $item['id'])->update($update);
            }
        }

        $mediaRows = $db->table('soal_revision_media AS srm')
            ->select('srm.media_asset_id, ma.media_kind')
            ->join('media_assets AS ma', 'ma.id = srm.media_asset_id')
            ->where('srm.soal_revision_id', $revisionId)
            ->get()->getResultArray();
        foreach ($items as $item) {
            $assignmentId = (int) $item['prepared_assignment_id'];
            foreach ($mediaRows as $media) {
                $exists = $db->table('prepared_assignment_media')
                    ->where('prepared_assignment_id', $assignmentId)
                    ->where('media_asset_id', (int) $media['media_asset_id'])
                    ->countAllResults() > 0;
                if ($exists) continue;
                $max = $db->table('prepared_assignment_media')->selectMax('prefetch_order', 'max_order')
                    ->where('prepared_assignment_id', $assignmentId)->get()->getRowArray();
                $db->table('prepared_assignment_media')->insert([
                    'prepared_assignment_id' => $assignmentId,
                    'media_asset_id' => (int) $media['media_asset_id'],
                    'is_critical' => $media['media_kind'] === 'IMAGE' ? 1 : 0,
                    'prefetch_order' => ((int) ($max['max_order'] ?? 0)) + 1,
                ]);
            }
        }
    }

    private function resetActiveAnswers($db, int $questionId, array $actor): void
    {
        $responses = $db->table('attempt_response AS ar')
            ->select('ar.*, a.id AS attempt_id')
            ->join('attempt AS a', 'a.id = ar.attempt_id')
            ->join('prepared_assignment_item AS pai', 'pai.id = ar.prepared_assignment_item_id')
            ->where('pai.soal_id', $questionId)
            ->where('a.status', 'ACTIVE')
            ->orderBy('a.id', 'ASC')
            ->get()->getResultArray();

        foreach ($responses as $row) {
            $attemptId = (int) $row['attempt_id'];
            $attempt = $db->query('SELECT server_sync_revision FROM attempt WHERE id = ? FOR UPDATE', [$attemptId])
                ->getRowArray();
            $nextServer = ((int) ($attempt['server_sync_revision'] ?? 0)) + 1;

            (new AuditService())->log(
                'MANAGER',
                (int) ($actor['user_id'] ?? 0),
                'LIVE_EDIT_REANSWER_RESET',
                'SCORING',
                'Jawaban lama disimpan di audit sebelum reanswer response #' . $row['id'],
                (string) ($actor['ip'] ?? ''),
                (string) ($actor['agent'] ?? ''),
                'attempt_response',
                (int) $row['id'],
                [
                    'answer_payload' => $row['answer_payload'],
                    'auto_score' => $row['auto_score'],
                    'manual_score' => $row['manual_score'],
                    'effective_score' => $row['effective_score'],
                    'scoring_state' => $row['scoring_state'],
                ],
                ['answer_payload' => null, 'scoring_state' => 'PENDING']
            );

            $db->table('attempt_response')->where('id', (int) $row['id'])->update([
                'answer_payload' => null,
                'auto_score' => null,
                'manual_score' => null,
                'effective_score' => null,
                'scoring_state' => 'PENDING',
                'server_revision' => $nextServer,
                'last_mutation_id' => null,
            ]);
            $db->table('attempt')->where('id', $attemptId)->update([
                'server_sync_revision' => $nextServer,
                'last_sync_at' => date('Y-m-d H:i:s'),
            ]);
        }
    }

    private function scalar(mixed $value): string
    {
        return is_scalar($value) ? (string) $value : '';
    }

    private function error(int $status, string $code, string $message): array
    {
        return compact('status', 'code', 'message') + ['ok' => false];
    }
}
