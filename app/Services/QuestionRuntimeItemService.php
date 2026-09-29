<?php

namespace App\Services;

class QuestionRuntimeItemService
{
    public function items($db, int $attemptId, int $assignmentId): array
    {
        $rows = $this->rows($db, $assignmentId);
        $result = [];
        foreach ($rows as $row) {
            $result[] = $this->build($db, $attemptId, $row);
        }
        return $result;
    }

    public function revisionChanges($db, int $attemptId, int $assignmentId): array
    {
        $changes = [];
        foreach ($this->rows($db, $assignmentId) as $row) {
            if ((int) $row['baseline_revision_id'] === (int) $row['current_revision_id']) {
                continue;
            }
            $item = $this->build($db, $attemptId, $row);
            $meta = json_decode((string) ($row['metadata_json'] ?? ''), true);
            $live = is_array($meta) && is_array($meta['live_edit'] ?? null)
                ? $meta['live_edit'] : [];
            $item['change_kind'] = (string) ($row['change_kind'] ?? 'CONTENT');
            $item['change_note'] = (string) ($row['change_note'] ?? '');
            $item['answer_policy'] = (string) ($live['active_answer_policy'] ?? 'PRESERVE');
            $item['voided'] = (string) ($row['change_kind'] ?? '') === 'VOID';
            $item['media_manifest'] = $this->media($db, $attemptId, (int) $row['current_revision_id']);
            $changes[] = $item;
        }
        return $changes;
    }

    private function rows($db, int $assignmentId): array
    {
        return $db->table('prepared_assignment_item AS pai')
            ->select(
                'pai.id AS item_id, pai.sequence_no, pai.soal_revision_id AS baseline_revision_id, '
                . 'pai.option_order_json, pai.mapping_json, pai.soal_id, '
                . 'sr.id AS current_revision_id, sr.question_type, sr.stimulus_html, sr.question_html, '
                . 'sr.max_point, sr.short_answer_mode, sr.change_kind, sr.change_note, sr.metadata_json'
            )
            ->join('soal AS s', 's.id = pai.soal_id')
            ->join(
                'soal_revision AS sr',
                'sr.soal_id = s.id AND sr.revision_no = s.current_revision_no',
                'left',
                false
            )
            ->where('pai.prepared_assignment_id', $assignmentId)
            ->orderBy('pai.sequence_no', 'ASC')
            ->get()
            ->getResultArray();
    }

    private function build($db, int $attemptId, array $row): array
    {
        $revisionId = (int) $row['current_revision_id'];
        $item = [
            'item_id' => (int) $row['item_id'],
            'sequence_no' => (int) $row['sequence_no'],
            'revision_id' => $revisionId,
            'question_type' => (string) $row['question_type'],
            'stimulus_text' => (string) ($row['stimulus_html'] ?? ''),
            'question_text' => (string) $row['question_html'],
            'max_point' => (float) $row['max_point'],
            'voided' => (string) ($row['change_kind'] ?? '') === 'VOID',
        ];

        if (in_array($row['question_type'], ['PG', 'PG_KOMPLEKS', 'PG_BERTINGKAT'], true)) {
            $options = $db->table('soal_opsi')
                ->select('option_key, content_html, sort_order')
                ->where('soal_revision_id', $revisionId)
                ->orderBy('sort_order', 'ASC')->orderBy('id', 'ASC')
                ->get()->getResultArray();
            $byKey = array_column($options, null, 'option_key');
            $order = json_decode((string) ($row['option_order_json'] ?? ''), true);
            if (!is_array($order)) $order = array_keys($byKey);
            foreach (array_keys($byKey) as $key) {
                if (!in_array($key, $order, true)) $order[] = $key;
            }
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
                ->where('soal_revision_id', $revisionId)
                ->orderBy('sort_order', 'ASC')->orderBy('id', 'ASC')
                ->get()->getResultArray();
            $leftMap = []; $rightMap = [];
            foreach ($pairs as $pair) {
                $leftMap[(string) $pair['left_key']] = (string) $pair['left_html'];
                $rightMap[(string) $pair['right_key']] = (string) $pair['right_html'];
            }
            $mapping = json_decode((string) ($row['mapping_json'] ?? ''), true);
            $mapping = is_array($mapping) ? $mapping : [];
            $leftOrder = is_array($mapping['left_order'] ?? null) ? $mapping['left_order'] : array_keys($leftMap);
            $rightOrder = is_array($mapping['right_order'] ?? null) ? $mapping['right_order'] : array_keys($rightMap);
            $leftAlias = is_array($mapping['left_alias'] ?? null) ? $mapping['left_alias'] : [];
            $rightAlias = is_array($mapping['right_alias'] ?? null) ? $mapping['right_alias'] : [];

            foreach (array_keys($leftMap) as $key) {
                if (!in_array($key, $leftOrder, true)) $leftOrder[] = $key;
                if (!isset($leftAlias[$key])) $leftAlias[$key] = 'L_' . substr(hash('sha256', $attemptId . '|L|' . $row['item_id'] . '|' . $key), 0, 16);
            }
            foreach (array_keys($rightMap) as $key) {
                if (!in_array($key, $rightOrder, true)) $rightOrder[] = $key;
                if (!isset($rightAlias[$key])) $rightAlias[$key] = 'R_' . substr(hash('sha256', $attemptId . '|R|' . $row['item_id'] . '|' . $key), 0, 16);
            }

            $item['matching_left'] = [];
            foreach ($leftOrder as $key) {
                if (!isset($leftMap[$key], $leftAlias[$key])) continue;
                $item['matching_left'][] = [
                    'key' => (string) $leftAlias[$key],
                    'content_text' => $leftMap[$key],
                ];
            }
            $item['matching_right'] = [];
            foreach ($rightOrder as $key) {
                if (!isset($rightMap[$key], $rightAlias[$key])) continue;
                $item['matching_right'][] = [
                    'key' => (string) $rightAlias[$key],
                    'content_text' => $rightMap[$key],
                ];
            }
        } elseif ($row['question_type'] === 'ISIAN_SINGKAT') {
            $item['short_answer_mode'] = (string) $row['short_answer_mode'];
        }

        return $item;
    }

    private function media($db, int $attemptId, int $revisionId): array
    {
        $rows = $db->table('soal_revision_media AS srm')
            ->select('ma.id, ma.storage_type, ma.media_kind, ma.provider, ma.external_url, ma.status')
            ->join('media_assets AS ma', 'ma.id = srm.media_asset_id')
            ->where('srm.soal_revision_id', $revisionId)
            ->orderBy('srm.sort_order', 'ASC')
            ->get()->getResultArray();
        $result = [];
        foreach ($rows as $row) {
            if ($row['status'] !== 'ACTIVE') continue;
            $id = (int) $row['id'];
            $result[] = [
                'id' => $id,
                'kind' => (string) $row['media_kind'],
                'provider' => $row['provider'],
                'url' => $row['storage_type'] === 'LOCAL'
                    ? base_url('api/attempt/' . $attemptId . '/media/' . $id)
                    : (string) $row['external_url'],
            ];
        }
        return $result;
    }
}
