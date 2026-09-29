<?php

namespace App\Services;

use Config\Database;

class ScoringQueryService
{
    public function attemptDetail(int $attemptId): array
    {
        $db = Database::connect();
        $attempt = $db->table('attempt AS a')
            ->select('a.*, j.results_finalized_at, j.results_finalized_by')
            ->join('jadwal AS j', 'j.id = a.jadwal_id')
            ->where('a.id', $attemptId)->get()->getRowArray();

        if ($attempt === null) {
            return $this->error(404, 'NOT_FOUND', 'Attempt tidak ditemukan.');
        }

        $snapshot = (new ResultSnapshotService())->latest($db, $attemptId);
        $items = [];
        if ($snapshot !== null) {
            $items = $db->table('result_item_snapshot AS ris')
                ->select('ris.*, pai.sequence_no, pai.soal_id, s.stable_key, sr.question_html, '
                    . 'ar.id AS response_id, ar.answer_payload, ar.auto_score, ar.manual_score, ar.effective_score, ar.scoring_state')
                ->join('prepared_assignment_item AS pai', 'pai.id = ris.prepared_assignment_item_id')
                ->join('soal AS s', 's.id = pai.soal_id', 'left')
                ->join('soal_revision AS sr', 'sr.id = pai.soal_revision_id', 'left')
                ->join('attempt_response AS ar',
                    'ar.attempt_id = ' . $attemptId . ' AND ar.prepared_assignment_item_id = pai.id',
                    'left', false)
                ->where('ris.result_snapshot_id', (int) $snapshot['id'])
                ->orderBy('pai.sequence_no', 'ASC')->get()->getResultArray();
        }

        return ['ok' => true, 'status' => 200, 'data' => [
            'attempt' => $attempt,
            'snapshot' => $snapshot,
            'items' => $items,
        ]];
    }

    public function questions(array $query): array
    {
        $db = Database::connect();
        $jadwalId = $this->positive($query['jadwal_id'] ?? null);
        $bankId = $this->positive($query['bank_id'] ?? null);
        $type = strtoupper(trim(is_scalar($query['question_type'] ?? null) ? (string) $query['question_type'] : ''));

        if ($jadwalId < 1 && $bankId < 1) {
            return $this->error(422, 'VALIDATION_FAILED', 'Pilih Jadwal atau Bank Soal untuk membuka daftar koreksi.');
        }

        $builder = $db->table('prepared_assignment_item AS pai')
            ->select('s.id AS question_id, s.stable_key, s.status AS question_status, sr.question_type, sr.question_html')
            ->join('prepared_assignment AS pa', 'pa.id = pai.prepared_assignment_id')
            ->join('soal AS s', 's.id = pai.soal_id')
            ->join('soal_revision AS sr', 'sr.soal_id = s.id AND sr.revision_no = s.current_revision_no', 'left', false);

        if ($jadwalId > 0) $builder->where('pa.generated_for_jadwal_id', $jadwalId);
        if ($bankId > 0) $builder->where('s.bank_soal_id', $bankId);
        if ($type !== '') $builder->where('sr.question_type', $type);

        $items = $builder->groupBy('s.id, s.stable_key, s.status, sr.question_type, sr.question_html')
            ->orderBy('s.id', 'ASC')->get()->getResultArray();

        foreach ($items as &$item) {
            $counts = $db->table('attempt_response AS ar')
                ->select("COUNT(ar.id) AS response_count, SUM(CASE WHEN ar.scoring_state IN ('PENDING_MANUAL','NEEDS_REVIEW') THEN 1 ELSE 0 END) AS pending_count", false)
                ->join('attempt AS a', 'a.id = ar.attempt_id')
                ->join('prepared_assignment_item AS pai2', 'pai2.id = ar.prepared_assignment_item_id')
                ->where('pai2.soal_id', (int) $item['question_id']);
            if ($jadwalId > 0) $counts->where('a.jadwal_id', $jadwalId);
            $countRow = $counts->get()->getRowArray() ?? [];
            $item['response_count'] = (int) ($countRow['response_count'] ?? 0);
            $item['pending_count'] = (int) ($countRow['pending_count'] ?? 0);
        }
        unset($item);

        return ['ok' => true, 'status' => 200, 'data' => $items];
    }

    public function responses(int $questionId, array $query): array
    {
        $db = Database::connect();
        $jadwalId = $this->positive($query['jadwal_id'] ?? null);
        $state = strtoupper(trim(is_scalar($query['scoring_state'] ?? null) ? (string) $query['scoring_state'] : ''));

        $builder = $db->table('attempt_response AS ar')
            ->select('ar.id AS response_id, ar.answer_payload, ar.auto_score, ar.manual_score, ar.effective_score, '
                . 'ar.scoring_state, ar.updated_at, a.id AS attempt_id, a.jadwal_id, a.status AS attempt_status, '
                . 'a.nomor_peserta_snapshot, a.nama_snapshot, a.rombel_snapshot, j.results_finalized_at, '
                . 'sr.question_type, sr.max_point, sr.rubric_html')
            ->join('attempt AS a', 'a.id = ar.attempt_id')
            ->join('jadwal AS j', 'j.id = a.jadwal_id')
            ->join('prepared_assignment_item AS pai', 'pai.id = ar.prepared_assignment_item_id')
            ->join('soal AS s', 's.id = pai.soal_id')
            ->join('soal_revision AS sr', 'sr.soal_id = s.id AND sr.revision_no = s.current_revision_no', 'left', false)
            ->where('pai.soal_id', $questionId);

        if ($jadwalId > 0) $builder->where('a.jadwal_id', $jadwalId);
        if ($state !== '') $builder->where('ar.scoring_state', $state);

        return ['ok' => true, 'status' => 200, 'data' =>
            $builder->orderBy('a.nama_snapshot', 'ASC')->orderBy('a.id', 'ASC')->get()->getResultArray()
        ];
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
