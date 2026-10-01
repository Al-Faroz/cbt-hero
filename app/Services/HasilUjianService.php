<?php

namespace App\Services;

use Config\Database;

class HasilUjianService
{
    public function index(array $query): array
    {
        $db = Database::connect();

        $page = max(1, (int) ($query['page'] ?? 1));
        $perPage = (int) ($query['per_page'] ?? 25);
        if ($perPage < 10) $perPage = 10;
        if ($perPage > 100) $perPage = 100;
        $offset = ($page - 1) * $perPage;

        $builder = $this->baseBuilder($db);
        $this->applyFilters($builder, $query);

        $countBuilder = clone $builder;
        $total = (int) $countBuilder->countAllResults(false);

        $rows = $builder
            ->orderBy('k.nama', 'ASC')
            ->orderBy('m.nama_mapel', 'ASC')
            ->orderBy('a.rombel_snapshot', 'ASC')
            ->orderBy('a.nama_snapshot', 'ASC')
            ->limit($perPage, $offset)
            ->get()
            ->getResultArray();

        foreach ($rows as &$row) {
            $row['result_snapshot_id'] = (int) $row['result_snapshot_id'];
            $row['attempt_id'] = (int) $row['attempt_id'];
            $row['root_jadwal_id'] = (int) $row['root_jadwal_id'];
            $row['jadwal_id'] = (int) $row['jadwal_id'];
            $row['is_final'] = (bool) $row['is_final'];
        }
        unset($row);

        return ['ok' => true, 'status' => 200, 'data' => [
            'items' => $rows,
            'pagination' => [
                'page' => $page,
                'per_page' => $perPage,
                'total' => $total,
                'pages' => max(1, (int) ceil($total / $perPage)),
            ],
        ]];
    }

    public function show(int $snapshotId): array
    {
        $db = Database::connect();

        $header = $this->baseBuilder($db)
            ->where('orp.result_snapshot_id', $snapshotId)
            ->get()
            ->getRowArray();

        if ($header === null) {
            return $this->error(404, 'NOT_FOUND', 'Hasil resmi tidak ditemukan.');
        }

        $items = $db->table('result_item_snapshot AS ris')
            ->select('ris.id, ris.prepared_assignment_item_id, ris.question_type, ris.raw_score, ris.max_point, '
                . 'ris.type_weight_percent, ris.weighted_score, ris.voided, ris.payload_json, '
                . 'pai.sequence_no, pai.soal_revision_id AS baseline_revision_id, s.stable_key')
            ->join('prepared_assignment_item AS pai', 'pai.id = ris.prepared_assignment_item_id')
            ->join('soal AS s', 's.id = pai.soal_id', 'left')
            ->where('ris.result_snapshot_id', $snapshotId)
            ->orderBy('pai.sequence_no', 'ASC')
            ->get()
            ->getResultArray();

        $revisionIds = [];
        foreach ($items as $item) {
            $payload = $item['payload_json']
                ? (json_decode((string) $item['payload_json'], true) ?: [])
                : [];
            $revisionId = (int) ($payload['scoring_revision_id'] ?? 0);
            if ($revisionId < 1) {
                $revisionId = (int) ($item['baseline_revision_id'] ?? 0);
            }
            if ($revisionId > 0) {
                $revisionIds[$revisionId] = true;
            }
        }

        $revisions = $this->revisionsById($db, array_keys($revisionIds));
        foreach ($items as &$item) {
            $item['voided'] = (bool) $item['voided'];
            $item['payload'] = $item['payload_json']
                ? (json_decode((string) $item['payload_json'], true) ?: null)
                : null;
            $revisionId = (int) (is_array($item['payload'])
                ? ($item['payload']['scoring_revision_id'] ?? 0)
                : 0);
            if ($revisionId < 1) {
                $revisionId = (int) ($item['baseline_revision_id'] ?? 0);
            }
            $revision = $revisionId > 0 ? ($revisions[$revisionId] ?? null) : null;
            $item['scoring_revision_id'] = $revisionId > 0 ? $revisionId : null;
            $item['question_html'] = is_array($revision)
                ? (string) ($revision['question_html'] ?? '')
                : '';
            unset($item['payload_json'], $item['baseline_revision_id']);
        }
        unset($item);

        $payload = $header['payload_json']
            ? (json_decode((string) $header['payload_json'], true) ?: null)
            : null;
        unset($header['payload_json']);

        return ['ok' => true, 'status' => 200, 'data' => [
            'result' => $header,
            'payload' => $payload,
            'items' => $items,
        ]];
    }

    public function options(): array
    {
        $db = Database::connect();

        return ['ok' => true, 'status' => 200, 'data' => [
            'kegiatan' => $db->table('official_result_pointer AS orp')
                ->distinct()
                ->select('k.id, k.nama, k.tahun_pelajaran, k.semester')
                ->join('result_snapshot AS rs', 'rs.id = orp.result_snapshot_id')
                ->join('jadwal AS rootj', 'rootj.id = orp.root_jadwal_id')
                ->join('kegiatan AS k', 'k.id = rootj.kegiatan_id')
                ->where('rs.result_type', 'ACADEMIC')
                ->orderBy('k.id', 'DESC')
                ->get()->getResultArray(),
            'jadwal' => $db->table('official_result_pointer AS orp')
                ->distinct()
                ->select('rootj.id, rootj.kegiatan_id, rootj.jenis_jadwal, rootj.mulai_at, rootj.results_finalized_at, '
                    . 'b.nama_bank, m.nama_mapel')
                ->join('result_snapshot AS rs', 'rs.id = orp.result_snapshot_id')
                ->join('jadwal AS rootj', 'rootj.id = orp.root_jadwal_id')
                ->join('bank_soal AS b', 'b.id = rootj.bank_soal_id', 'left')
                ->join('mata_pelajaran AS m', 'm.id = b.mapel_id', 'left')
                ->where('rs.result_type', 'ACADEMIC')
                ->orderBy('rootj.mulai_at', 'DESC')
                ->get()->getResultArray(),
            'mapel' => $db->table('official_result_pointer AS orp')
                ->distinct()
                ->select('m.id, m.kode_mapel, m.nama_mapel, m.urutan')
                ->join('result_snapshot AS rs', 'rs.id = orp.result_snapshot_id')
                ->join('jadwal AS rootj', 'rootj.id = orp.root_jadwal_id')
                ->join('bank_soal AS b', 'b.id = rootj.bank_soal_id')
                ->join('mata_pelajaran AS m', 'm.id = b.mapel_id')
                ->where('rs.result_type', 'ACADEMIC')
                ->orderBy('m.urutan', 'ASC')
                ->orderBy('m.nama_mapel', 'ASC')
                ->get()->getResultArray(),
            'rombel' => $db->table('official_result_pointer AS orp')
                ->distinct()
                ->select('a.rombel_snapshot AS nama')
                ->join('result_snapshot AS rs', 'rs.id = orp.result_snapshot_id')
                ->join('attempt AS a', 'a.id = orp.attempt_id')
                ->where('rs.result_type', 'ACADEMIC')
                ->orderBy('a.rombel_snapshot', 'ASC')
                ->get()->getResultArray(),
        ]];
    }

    private function baseBuilder($db)
    {
        return $db->table('official_result_pointer AS orp')
            ->select('orp.root_jadwal_id, orp.attempt_id, orp.result_snapshot_id, '
                . 'rs.snapshot_version, rs.click_score, rs.typed_score, rs.final_score, rs.scoring_status, '
                . 'rs.is_final, rs.finalized_at, rs.created_at AS snapshot_created_at, rs.payload_json, '
                . 'a.jadwal_id, a.finish_reason, a.finish_at, a.nomor_peserta_snapshot, a.nama_snapshot, '
                . 'a.rombel_snapshot, a.ruang_snapshot, '
                . 'k.id AS kegiatan_id, k.nama AS kegiatan_nama, k.tahun_pelajaran, k.semester, '
                . 'b.id AS bank_soal_id, b.nama_bank, b.tingkat, '
                . 'm.id AS mapel_id, m.kode_mapel, m.nama_mapel')
            ->join('result_snapshot AS rs', 'rs.id = orp.result_snapshot_id')
            ->join('attempt AS a', 'a.id = orp.attempt_id')
            ->join('jadwal AS rootj', 'rootj.id = orp.root_jadwal_id')
            ->join('kegiatan AS k', 'k.id = rootj.kegiatan_id')
            ->join('bank_soal AS b', 'b.id = rootj.bank_soal_id', 'left')
            ->join('mata_pelajaran AS m', 'm.id = b.mapel_id', 'left')
            ->where('rs.result_type', 'ACADEMIC');
    }

    private function applyFilters($builder, array $query): void
    {
        $positive = static function ($value): int {
            $parsed = is_scalar($value)
                ? filter_var($value, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]])
                : false;
            return is_int($parsed) ? $parsed : 0;
        };

        $kegiatanId = $positive($query['kegiatan_id'] ?? null);
        $jadwalId = $positive($query['jadwal_id'] ?? null);
        $mapelId = $positive($query['mapel_id'] ?? null);

        if ($kegiatanId > 0) $builder->where('k.id', $kegiatanId);
        if ($jadwalId > 0) $builder->where('orp.root_jadwal_id', $jadwalId);
        if ($mapelId > 0) $builder->where('m.id', $mapelId);

        $rombel = trim(is_scalar($query['rombel'] ?? null) ? (string) $query['rombel'] : '');
        if ($rombel !== '') $builder->where('a.rombel_snapshot', $rombel);

        $scoring = strtoupper(trim(is_scalar($query['scoring_status'] ?? null) ? (string) $query['scoring_status'] : ''));
        if ($scoring !== '') $builder->where('rs.scoring_status', $scoring);

        $final = trim(is_scalar($query['final'] ?? null) ? (string) $query['final'] : '');
        if ($final === '1') $builder->where('rs.is_final', 1);
        if ($final === '0') $builder->where('rs.is_final', 0);

        $q = trim(is_scalar($query['q'] ?? null) ? (string) $query['q'] : '');
        if ($q !== '') {
            $builder->groupStart()
                ->like('a.nomor_peserta_snapshot', $q)
                ->orLike('a.nama_snapshot', $q)
                ->orLike('a.rombel_snapshot', $q)
                ->groupEnd();
        }
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
            ->select('id, question_html')
            ->whereIn('id', $revisionIds)
            ->get()->getResultArray();

        $result = [];
        foreach ($rows as $row) {
            $result[(int) $row['id']] = $row;
        }
        return $result;
    }

    private function error(int $status, string $code, string $message): array
    {
        return compact('status', 'code', 'message') + ['ok' => false];
    }
}
