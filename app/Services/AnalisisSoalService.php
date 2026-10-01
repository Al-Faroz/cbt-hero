<?php

namespace App\Services;

use Config\Database;

class AnalisisSoalService
{
    public function index(array $query): array
    {
        $db = Database::connect();
        $jadwalId = $this->positive($query['jadwal_id'] ?? null);
        if ($jadwalId < 1) {
            return $this->error(422, 'VALIDATION_FAILED', 'Pilih Jadwal untuk membuka Analisis Soal.');
        }

        $context = $this->context($db, $jadwalId);
        if ($context === null) {
            return $this->error(404, 'NOT_FOUND', 'Jadwal akademik tidak ditemukan.');
        }

        $type = strtoupper(trim(is_scalar($query['question_type'] ?? null)
            ? (string) $query['question_type'] : ''));

        $builder = $db->table('official_result_pointer AS orp')
            ->select(
                's.id AS question_id, s.stable_key, '
                . 'MAX(ris.question_type) AS question_type, '
                . 'COUNT(DISTINCT orp.peserta_kegiatan_id) AS participant_count, '
                . 'SUM(CASE WHEN ris.voided = 0 THEN 1 ELSE 0 END) AS scored_count, '
                . 'SUM(CASE WHEN ris.voided = 1 THEN 1 ELSE 0 END) AS voided_count, '
                . 'SUM(CASE WHEN ris.voided = 0 THEN ris.raw_score ELSE 0 END) AS raw_total, '
                . 'SUM(CASE WHEN ris.voided = 0 THEN ris.max_point ELSE 0 END) AS max_total, '
                . 'AVG(CASE WHEN ris.voided = 0 AND ris.max_point > 0 '
                . 'THEN (ris.raw_score / ris.max_point) * 100 ELSE NULL END) AS average_percent, '
                . 'SUM(CASE WHEN ris.voided = 0 AND ris.max_point > 0 '
                . 'AND ris.raw_score >= ris.max_point THEN 1 ELSE 0 END) AS full_score_count, '
                . 'SUM(CASE WHEN ris.voided = 0 AND COALESCE(ris.raw_score, 0) = 0 THEN 1 ELSE 0 END) AS zero_score_count',
                false
            )
            ->join('result_item_snapshot AS ris', 'ris.result_snapshot_id = orp.result_snapshot_id')
            ->join('prepared_assignment_item AS pai', 'pai.id = ris.prepared_assignment_item_id')
            ->join('soal AS s', 's.id = pai.soal_id')
            ->join('result_snapshot AS rs', 'rs.id = orp.result_snapshot_id')
            ->where('orp.root_jadwal_id', $jadwalId)
            ->where('rs.result_type', 'ACADEMIC');

        if ($type !== '') {
            $builder->where('ris.question_type', $type);
        }

        $items = $builder
            ->groupBy('s.id, s.stable_key')
            ->orderBy('s.id', 'ASC')
            ->get()->getResultArray();

        $revisionMeta = $this->snapshotRevisionMeta($db, $jadwalId);

        foreach ($items as &$item) {
            $scored = (int) ($item['scored_count'] ?? 0);
            $rawTotal = (float) ($item['raw_total'] ?? 0);
            $maxTotal = (float) ($item['max_total'] ?? 0);
            $full = (int) ($item['full_score_count'] ?? 0);
            $zero = (int) ($item['zero_score_count'] ?? 0);

            $item['question_id'] = (int) $item['question_id'];
            $meta = $revisionMeta[$item['question_id']] ?? null;
            $item['question_html'] = is_array($meta) ? (string) ($meta['question_html'] ?? '') : '';
            $item['revision_count'] = is_array($meta) ? (int) ($meta['revision_count'] ?? 0) : 0;
            $item['participant_count'] = (int) $item['participant_count'];
            $item['scored_count'] = $scored;
            $item['voided_count'] = (int) $item['voided_count'];
            $item['average_percent'] = $item['average_percent'] === null
                ? null : round((float) $item['average_percent'], 2);
            $item['difficulty_index'] = $maxTotal > 0
                ? round(($rawTotal / $maxTotal) * 100, 2) : null;
            $item['full_score_rate'] = $scored > 0 ? round(($full / $scored) * 100, 2) : null;
            $item['zero_score_rate'] = $scored > 0 ? round(($zero / $scored) * 100, 2) : null;
            unset($item['raw_total'], $item['max_total'], $item['full_score_count'], $item['zero_score_count']);
        }
        unset($item);

        return ['ok' => true, 'status' => 200, 'data' => [
            'context' => $context,
            'items' => $items,
            'summary' => [
                'question_count' => count($items),
                'participant_count' => $this->participantCount($db, $jadwalId),
                'source' => 'OFFICIAL_RESULT_SNAPSHOT',
            ],
        ]];
    }

    public function show(int $questionId, array $query): array
    {
        $db = Database::connect();
        $jadwalId = $this->positive($query['jadwal_id'] ?? null);
        if ($jadwalId < 1 || $questionId < 1) {
            return $this->error(422, 'VALIDATION_FAILED', 'Jadwal dan soal wajib dipilih.');
        }

        $question = $db->table('soal')
            ->select('id, stable_key')
            ->where('id', $questionId)
            ->get()->getRowArray();
        if ($question === null) {
            return $this->error(404, 'NOT_FOUND', 'Soal tidak ditemukan.');
        }

        $rows = $db->table('official_result_pointer AS orp')
            ->select(
                'a.nomor_peserta_snapshot, a.nama_snapshot, a.rombel_snapshot, '
                . 'ris.question_type, ris.raw_score, ris.max_point, ris.weighted_score, ris.voided, '
                . 'ris.payload_json AS result_payload_json, pai.soal_revision_id AS baseline_revision_id, '
                . 'ar.answer_payload, rs.is_final'
            )
            ->join('result_snapshot AS rs', 'rs.id = orp.result_snapshot_id')
            ->join('attempt AS a', 'a.id = orp.attempt_id')
            ->join('result_item_snapshot AS ris', 'ris.result_snapshot_id = orp.result_snapshot_id')
            ->join('prepared_assignment_item AS pai', 'pai.id = ris.prepared_assignment_item_id')
            ->join(
                'attempt_response AS ar',
                'ar.attempt_id = orp.attempt_id AND ar.prepared_assignment_item_id = ris.prepared_assignment_item_id',
                'left',
                false
            )
            ->where('orp.root_jadwal_id', $jadwalId)
            ->where('pai.soal_id', $questionId)
            ->where('rs.result_type', 'ACADEMIC')
            ->orderBy('a.rombel_snapshot', 'ASC')
            ->orderBy('a.nama_snapshot', 'ASC')
            ->get()->getResultArray();

        $revisionIds = [];
        foreach ($rows as $row) {
            $payload = $this->snapshotItemPayload($row['result_payload_json'] ?? null);
            $revisionId = (int) ($payload['scoring_revision_id'] ?? 0);
            if ($revisionId < 1) {
                $revisionId = (int) ($row['baseline_revision_id'] ?? 0);
            }
            if ($revisionId > 0) {
                $revisionIds[$revisionId] = true;
            }
        }

        $revisions = $this->revisionsById($db, array_keys($revisionIds));
        if ($revisionIds) {
            $selectedRevisionId = max(array_keys($revisionIds));
            $selectedRevision = $revisions[$selectedRevisionId] ?? null;
            if (is_array($selectedRevision)) {
                $question = [
                    'id' => (int) $question['id'],
                    'stable_key' => (string) $question['stable_key'],
                    'question_type' => (string) $selectedRevision['question_type'],
                    'question_html' => (string) $selectedRevision['question_html'],
                    'max_point' => (float) $selectedRevision['max_point'],
                    'scoring_revision_id' => $selectedRevisionId,
                    'revision_count' => count($revisionIds),
                ];
            }
        }

        foreach ($rows as &$row) {
            $payload = $this->snapshotItemPayload($row['result_payload_json'] ?? null);
            $revisionId = (int) ($payload['scoring_revision_id'] ?? 0);
            if ($revisionId < 1) {
                $revisionId = (int) ($row['baseline_revision_id'] ?? 0);
            }
            $row['scoring_revision_id'] = $revisionId > 0 ? $revisionId : null;
            $row['scoring_state'] = (string) ($payload['scoring_state'] ?? '');
            $row['raw_score'] = $row['raw_score'] === null ? null : (float) $row['raw_score'];
            $row['max_point'] = $row['max_point'] === null ? null : (float) $row['max_point'];
            $row['weighted_score'] = $row['weighted_score'] === null ? null : (float) $row['weighted_score'];
            $row['voided'] = (bool) $row['voided'];
            $row['is_final'] = (bool) $row['is_final'];
            $row['answered'] = $this->answered((string) $row['question_type'], $row['answer_payload']);
            unset($row['answer_payload'], $row['result_payload_json'], $row['baseline_revision_id']);
        }
        unset($row);

        return ['ok' => true, 'status' => 200, 'data' => [
            'question' => $question,
            'responses' => $rows,
        ]];
    }

    public function options(): array
    {
        $db = Database::connect();
        return ['ok' => true, 'status' => 200, 'data' => [
            'jadwal' => $db->table('official_result_pointer AS orp')
                ->select('rootj.id, rootj.kegiatan_id, rootj.mulai_at, k.nama AS kegiatan_nama, '
                    . 'b.nama_bank, m.nama_mapel')
                ->join('jadwal AS rootj', 'rootj.id = orp.root_jadwal_id')
                ->join('kegiatan AS k', 'k.id = rootj.kegiatan_id')
                ->join('bank_soal AS b', 'b.id = rootj.bank_soal_id')
                ->join('mata_pelajaran AS m', 'm.id = b.mapel_id')
                ->join('result_snapshot AS rs', 'rs.id = orp.result_snapshot_id')
                ->where('rootj.psych_instrument_id', null)
                ->where('rs.result_type', 'ACADEMIC')
                ->groupBy('rootj.id, rootj.kegiatan_id, rootj.mulai_at, k.nama, b.nama_bank, m.nama_mapel')
                ->orderBy('rootj.mulai_at', 'DESC')
                ->get()->getResultArray(),
            'types' => [
                ['value' => 'PG', 'label' => 'Pilihan Ganda'],
                ['value' => 'PG_KOMPLEKS', 'label' => 'PG Kompleks'],
                ['value' => 'PG_BERTINGKAT', 'label' => 'PG Bertingkat'],
                ['value' => 'MATCHING', 'label' => 'Menjodohkan'],
                ['value' => 'ISIAN_SINGKAT', 'label' => 'Isian Singkat'],
                ['value' => 'URAIAN', 'label' => 'Uraian'],
            ],
        ]];
    }

    private function context($db, int $jadwalId): ?array
    {
        return $db->table('jadwal AS j')
            ->select('j.id, j.kegiatan_id, j.mulai_at, j.results_finalized_at, '
                . 'k.nama AS kegiatan_nama, k.tahun_pelajaran, k.semester, '
                . 'b.nama_bank, m.id AS mapel_id, m.kode_mapel, m.nama_mapel')
            ->join('kegiatan AS k', 'k.id = j.kegiatan_id')
            ->join('bank_soal AS b', 'b.id = j.bank_soal_id')
            ->join('mata_pelajaran AS m', 'm.id = b.mapel_id')
            ->where('j.id', $jadwalId)
            ->where('j.parent_jadwal_id', null)
            ->where('j.psych_instrument_id', null)
            ->get()->getRowArray();
    }

    private function snapshotRevisionMeta($db, int $jadwalId): array
    {
        $rows = $db->table('official_result_pointer AS orp')
            ->select('s.id AS question_id, ris.payload_json, pai.soal_revision_id AS baseline_revision_id')
            ->join('result_snapshot AS rs', 'rs.id = orp.result_snapshot_id')
            ->join('result_item_snapshot AS ris', 'ris.result_snapshot_id = orp.result_snapshot_id')
            ->join('prepared_assignment_item AS pai', 'pai.id = ris.prepared_assignment_item_id')
            ->join('soal AS s', 's.id = pai.soal_id')
            ->where('orp.root_jadwal_id', $jadwalId)
            ->where('rs.result_type', 'ACADEMIC')
            ->get()->getResultArray();

        $questionRevisions = [];
        $allRevisionIds = [];
        foreach ($rows as $row) {
            $payload = $this->snapshotItemPayload($row['payload_json'] ?? null);
            $revisionId = (int) ($payload['scoring_revision_id'] ?? 0);
            if ($revisionId < 1) {
                $revisionId = (int) ($row['baseline_revision_id'] ?? 0);
            }
            $questionId = (int) ($row['question_id'] ?? 0);
            if ($questionId < 1 || $revisionId < 1) {
                continue;
            }
            $questionRevisions[$questionId][$revisionId] = true;
            $allRevisionIds[$revisionId] = true;
        }

        $revisions = $this->revisionsById($db, array_keys($allRevisionIds));
        $result = [];
        foreach ($questionRevisions as $questionId => $revisionSet) {
            $selectedId = max(array_keys($revisionSet));
            $revision = $revisions[$selectedId] ?? null;
            $result[(int) $questionId] = [
                'question_html' => is_array($revision) ? (string) ($revision['question_html'] ?? '') : '',
                'question_type' => is_array($revision) ? (string) ($revision['question_type'] ?? '') : '',
                'scoring_revision_id' => $selectedId,
                'revision_count' => count($revisionSet),
            ];
        }
        return $result;
    }

    private function revisionsById($db, array $revisionIds): array
    {
        $revisionIds = array_values(array_unique(array_filter(
            array_map('intval', $revisionIds),
            static fn(int $id): bool => $id > 0
        )));
        if (!$revisionIds) {
            return [];
        }

        $rows = $db->table('soal_revision')
            ->select('id, question_type, question_html, max_point')
            ->whereIn('id', $revisionIds)
            ->get()->getResultArray();

        $result = [];
        foreach ($rows as $row) {
            $result[(int) $row['id']] = $row;
        }
        return $result;
    }

    private function snapshotItemPayload(mixed $value): array
    {
        if (!is_string($value) || trim($value) === '') {
            return [];
        }
        $decoded = json_decode($value, true);
        return is_array($decoded) ? $decoded : [];
    }

    private function participantCount($db, int $jadwalId): int
    {
        return (int) $db->table('official_result_pointer AS orp')
            ->join('result_snapshot AS rs', 'rs.id = orp.result_snapshot_id')
            ->where('orp.root_jadwal_id', $jadwalId)
            ->where('rs.result_type', 'ACADEMIC')
            ->countAllResults();
    }

    private function answered(string $type, mixed $payloadJson): bool
    {
        if (!is_string($payloadJson) || trim($payloadJson) === '') {
            return false;
        }
        $payload = json_decode($payloadJson, true);
        if (!is_array($payload)) {
            return false;
        }
        if (in_array($type, ['PG', 'PG_BERTINGKAT'], true)) {
            return trim((string) ($payload['selected'] ?? '')) !== '';
        }
        if ($type === 'PG_KOMPLEKS') {
            return is_array($payload['selected'] ?? null) && count($payload['selected']) > 0;
        }
        if ($type === 'MATCHING') {
            return is_array($payload['pairs'] ?? null)
                && count(array_filter($payload['pairs'], static fn($value): bool => $value !== null && $value !== '')) > 0;
        }
        if ($type === 'ISIAN_SINGKAT') {
            return trim((string) ($payload['value'] ?? '')) !== '';
        }
        if ($type === 'URAIAN') {
            return trim((string) ($payload['text'] ?? '')) !== '';
        }
        return false;
    }

    private function positive(mixed $value): int
    {
        $parsed = is_scalar($value)
            ? filter_var($value, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]])
            : false;
        return is_int($parsed) ? $parsed : 0;
    }

    private function error(int $status, string $code, string $message): array
    {
        return compact('status', 'code', 'message') + ['ok' => false];
    }
}
