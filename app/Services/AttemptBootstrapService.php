<?php

namespace App\Services;

use Config\Database;

class AttemptBootstrapService
{
    public function bootstrap(int $participantId, int $attemptId, string $clientUuid, int $generation): array
    {
        $db = Database::connect();
        $ownership = new AttemptOwnershipService();
        $attempt = $ownership->owned($db, $participantId, $attemptId);
        if ($attempt === null) return $this->error(404, 'NOT_FOUND', 'Attempt tidak ditemukan.');
        if ($attempt['status'] !== 'ACTIVE') return $this->error(409, 'ATTEMPT_NOT_ACTIVE', 'Attempt sudah tidak aktif.');

        $clientError = $ownership->validateClient($attempt, $clientUuid, $generation);
        if ($clientError !== null)
            return $this->error($clientError['status'], $clientError['code'], $clientError['message']);

        $items = $this->items($db, $attemptId, (int) $attempt['prepared_assignment_id']);
        $responses = $db->table('attempt_response')
            ->select('prepared_assignment_item_id, answer_payload, client_revision, server_revision, is_flagged')
            ->where('attempt_id', $attemptId)->get()->getResultArray();
        $answers = [];
        foreach ($responses as $row) {
            $answers[(int) $row['prepared_assignment_item_id']] = [
                'answer_payload' => $row['answer_payload'] === null ? null : json_decode((string) $row['answer_payload'], true),
                'client_revision' => (int) $row['client_revision'],
                'server_revision' => (int) $row['server_revision'],
                'is_flagged' => (bool) $row['is_flagged'],
            ];
        }

        $media = $db->table('prepared_assignment_media AS pam')
            ->select('pam.media_asset_id, pam.is_critical, pam.prefetch_order, '
                . 'ma.storage_type, ma.media_kind, ma.provider, ma.external_url, ma.status')
            ->join('media_assets AS ma', 'ma.id = pam.media_asset_id')
            ->where('pam.prepared_assignment_id', (int) $attempt['prepared_assignment_id'])
            ->orderBy('pam.is_critical', 'DESC')->orderBy('pam.prefetch_order', 'ASC')
            ->get()->getResultArray();

        $mediaManifest = [];
        foreach ($media as $row) {
            if ($row['status'] !== 'ACTIVE') continue;
            $id = (int) $row['media_asset_id'];
            $mediaManifest[] = [
                'id' => $id,
                'kind' => (string) $row['media_kind'],
                'provider' => $row['provider'],
                'url' => $row['storage_type'] === 'LOCAL'
                    ? base_url('api/attempt/' . $attemptId . '/media/' . $id)
                    : (string) $row['external_url'],
                'critical' => (bool) $row['is_critical'],
                'prefetch_order' => (int) $row['prefetch_order'],
            ];
        }

        $db->table('attempt')->where('id', $attemptId)->update(['last_activity_at' => date('Y-m-d H:i:s')]);

        return ['ok' => true, 'status' => 200, 'data' => [
            'attempt' => [
                'id' => $attemptId,
                'status' => $attempt['status'],
                'client_generation' => (int) $attempt['client_generation'],
                'start_at' => $ownership->iso((string) $attempt['start_at']),
                'deadline_at' => $ownership->iso((string) $attempt['deadline_at']),
                'remaining_seconds' => $ownership->remainingSeconds($attempt),
                'paused' => $attempt['pause_started_at'] !== null,
                'duration_seconds' => (int) $attempt['duration_seconds_snapshot'],
                'added_seconds' => (int) $attempt['added_seconds'],
                'server_sync_revision' => (int) $attempt['server_sync_revision'],
                'last_sync_at' => $attempt['last_sync_at'] === null ? null : $ownership->iso((string) $attempt['last_sync_at']),
            ],
            'participant' => [
                'nomor_peserta' => $attempt['nomor_peserta_snapshot'],
                'username' => $attempt['username_snapshot'],
                'nama' => $attempt['nama_snapshot'],
                'rombel' => $attempt['rombel_snapshot'],
                'ruang' => $attempt['ruang_snapshot'],
            ],
            'exam' => [
                'jadwal_id' => (int) $attempt['jadwal_id'],
                'kegiatan_nama' => $attempt['kegiatan_nama'],
                'nama_ujian' => $attempt['nama_mapel'] ?: $attempt['nama_bank'],
                'tingkat' => $attempt['tingkat'] === null ? null : (int) $attempt['tingkat'],
            ],
            'package' => [
                'mode' => 'FULL',
                'items' => $items,
                'item_count' => count($items),
            ],
            'answers' => $answers,
            'media_manifest' => $mediaManifest,
            'revision_map' => array_column($items, 'revision_id', 'item_id'),
        ]];
    }

    public function media(int $participantId, int $attemptId, int $mediaId): array
    {
        $db = Database::connect();
        $ownership = new AttemptOwnershipService();
        $attempt = $ownership->owned($db, $participantId, $attemptId);
        if ($attempt === null) return $this->error(404, 'NOT_FOUND', 'Attempt tidak ditemukan.');

        $row = $db->table('prepared_assignment_media AS pam')
            ->select('ma.*')->join('media_assets AS ma', 'ma.id = pam.media_asset_id')
            ->where('pam.prepared_assignment_id', (int) $attempt['prepared_assignment_id'])
            ->where('pam.media_asset_id', $mediaId)->where('ma.status', 'ACTIVE')
            ->get()->getRowArray();
        if ($row === null) return $this->error(404, 'NOT_FOUND', 'Media tidak termasuk assignment.');

        return ['ok' => true, 'status' => 200, 'data' => $row];
    }

    private function items($db, int $attemptId, int $assignmentId): array
    {
        $rows = $db->table('prepared_assignment_item AS pai')
            ->select('pai.id AS item_id, pai.sequence_no, pai.soal_revision_id, pai.option_order_json, pai.mapping_json, '
                . 'sr.question_type, sr.stimulus_html, sr.question_html, sr.max_point, sr.short_answer_mode')
            ->join('soal_revision AS sr', 'sr.id = pai.soal_revision_id')
            ->where('pai.prepared_assignment_id', $assignmentId)
            ->orderBy('pai.sequence_no', 'ASC')->get()->getResultArray();

        $result = [];
        foreach ($rows as $row) {
            $item = [
                'item_id' => (int) $row['item_id'],
                'sequence_no' => (int) $row['sequence_no'],
                'revision_id' => (int) $row['soal_revision_id'],
                'question_type' => (string) $row['question_type'],
                'stimulus_text' => (string) ($row['stimulus_html'] ?? ''),
                'question_text' => (string) $row['question_html'],
                'max_point' => (float) $row['max_point'],
            ];

            if (in_array($row['question_type'], ['PG', 'PG_KOMPLEKS', 'PG_BERTINGKAT'], true)) {
                $options = $db->table('soal_opsi')->select('option_key, content_html, sort_order')
                    ->where('soal_revision_id', (int) $row['soal_revision_id'])
                    ->orderBy('sort_order', 'ASC')->orderBy('id', 'ASC')->get()->getResultArray();
                $byKey = array_column($options, null, 'option_key');
                $order = json_decode((string) ($row['option_order_json'] ?? ''), true);
                if (!is_array($order)) $order = array_keys($byKey);
                $item['options'] = [];
                foreach ($order as $key) {
                    if (!is_string($key) || !isset($byKey[$key])) continue;
                    $item['options'][] = [
                        'option_key' => $key,
                        'content_text' => (string) $byKey[$key]['content_html'],
                    ];
                }
            } elseif ($row['question_type'] === 'MATCHING') {
                $pairs = $db->table('soal_matching_pair')
                    ->select('left_key, left_html, right_key, right_html, sort_order')
                    ->where('soal_revision_id', (int) $row['soal_revision_id'])
                    ->orderBy('sort_order', 'ASC')->orderBy('id', 'ASC')->get()->getResultArray();
                $leftMap = []; $rightMap = [];
                foreach ($pairs as $pair) {
                    $leftMap[(string) $pair['left_key']] = (string) $pair['left_html'];
                    $rightMap[(string) $pair['right_key']] = (string) $pair['right_html'];
                }
                $mapping = json_decode((string) ($row['mapping_json'] ?? ''), true);
                $leftOrder = is_array($mapping['left_order'] ?? null) ? $mapping['left_order'] : array_keys($leftMap);
                $rightOrder = is_array($mapping['right_order'] ?? null) ? $mapping['right_order'] : array_keys($rightMap);
                $item['matching_left'] = [];
                foreach ($leftOrder as $key) if (isset($leftMap[$key]))
                    $item['matching_left'][] = ['key' => $key, 'content_text' => $leftMap[$key]];
                $item['matching_right'] = [];
                foreach ($rightOrder as $key) if (isset($rightMap[$key]))
                    $item['matching_right'][] = ['key' => $key, 'content_text' => $rightMap[$key]];
            } elseif ($row['question_type'] === 'ISIAN_SINGKAT') {
                $item['short_answer_mode'] = (string) $row['short_answer_mode'];
            }

            $result[] = $item;
        }
        return $result;
    }

    private function error(int $status, string $code, string $message): array
    {
        return compact('status', 'code', 'message') + ['ok' => false];
    }
}
