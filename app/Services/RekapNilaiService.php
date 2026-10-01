<?php

namespace App\Services;

use Config\Database;

class RekapNilaiService
{
    public function matrix(array $query): array
    {
        $db = Database::connect();
        $kegiatanId = $this->positive($query['kegiatan_id'] ?? null);
        if ($kegiatanId < 1) {
            return $this->error(422, 'VALIDATION_FAILED', 'Pilih Kegiatan untuk membuka Rekap Nilai.');
        }

        $context = $db->table('kegiatan')
            ->select('id, nama, tahun_pelajaran, semester')
            ->where('id', $kegiatanId)
            ->get()->getRowArray();
        if ($context === null) {
            return $this->error(404, 'NOT_FOUND', 'Kegiatan tidak ditemukan.');
        }

        $rombel = trim(is_scalar($query['rombel'] ?? null) ? (string) $query['rombel'] : '');
        $columnsBuilder = $db->table('official_result_pointer AS orp')
            ->select('orp.root_jadwal_id, m.id AS mapel_id, m.kode_mapel, m.nama_mapel, m.urutan AS mapel_urutan, '
                . 'b.nama_bank, rootj.mulai_at')
            ->join('jadwal AS rootj', 'rootj.id = orp.root_jadwal_id')
            ->join('bank_soal AS b', 'b.id = rootj.bank_soal_id')
            ->join('mata_pelajaran AS m', 'm.id = b.mapel_id')
            ->join('result_snapshot AS rs', 'rs.id = orp.result_snapshot_id')
            ->where('rootj.kegiatan_id', $kegiatanId)
            ->where('rootj.psych_instrument_id', null)
            ->where('rs.result_type', 'ACADEMIC')
            ->groupBy('orp.root_jadwal_id, m.id, m.kode_mapel, m.nama_mapel, m.urutan, b.nama_bank, rootj.mulai_at')
            ->orderBy('m.urutan', 'ASC')
            ->orderBy('m.nama_mapel', 'ASC')
            ->orderBy('rootj.mulai_at', 'ASC');
        $columns = $columnsBuilder->get()->getResultArray();

        foreach ($columns as &$column) {
            $column['root_jadwal_id'] = (int) $column['root_jadwal_id'];
            $column['mapel_id'] = (int) $column['mapel_id'];
        }
        unset($column);

        $builder = $db->table('official_result_pointer AS orp')
            ->select('orp.peserta_kegiatan_id, orp.root_jadwal_id, orp.result_snapshot_id, '
                . 'a.nomor_peserta_snapshot, a.nama_snapshot, a.rombel_snapshot, '
                . 'rs.final_score, rs.click_score, rs.typed_score, rs.scoring_status, rs.is_final')
            ->join('jadwal AS rootj', 'rootj.id = orp.root_jadwal_id')
            ->join('result_snapshot AS rs', 'rs.id = orp.result_snapshot_id')
            ->join('attempt AS a', 'a.id = orp.attempt_id')
            ->where('rootj.kegiatan_id', $kegiatanId)
            ->where('rootj.psych_instrument_id', null)
            ->where('rs.result_type', 'ACADEMIC');

        if ($rombel !== '') {
            $builder->where('a.rombel_snapshot', $rombel);
        }

        $records = $builder
            ->orderBy('a.rombel_snapshot', 'ASC')
            ->orderBy('a.nama_snapshot', 'ASC')
            ->orderBy('orp.root_jadwal_id', 'ASC')
            ->get()->getResultArray();

        $rows = [];
        foreach ($records as $record) {
            $participantKey = (int) $record['peserta_kegiatan_id'];
            if (!isset($rows[$participantKey])) {
                $rows[$participantKey] = [
                    'peserta_kegiatan_id' => $participantKey,
                    'nomor_peserta' => (string) ($record['nomor_peserta_snapshot'] ?? ''),
                    'nama' => (string) $record['nama_snapshot'],
                    'rombel' => (string) $record['rombel_snapshot'],
                    'scores' => [],
                    'final_count' => 0,
                    'result_count' => 0,
                ];
            }

            $rootId = (int) $record['root_jadwal_id'];
            $isFinal = (bool) $record['is_final'];
            $rows[$participantKey]['scores'][(string) $rootId] = [
                'result_snapshot_id' => (int) $record['result_snapshot_id'],
                'final_score' => $isFinal && $record['final_score'] !== null
                    ? (float) $record['final_score']
                    : null,
                'click_score' => $record['click_score'] === null ? null : (float) $record['click_score'],
                'typed_score' => $record['typed_score'] === null ? null : (float) $record['typed_score'],
                'scoring_status' => (string) $record['scoring_status'],
                'is_final' => $isFinal,
            ];
            $rows[$participantKey]['result_count']++;
            if ((bool) $record['is_final']) {
                $rows[$participantKey]['final_count']++;
            }
        }

        $rows = array_values($rows);
        foreach ($rows as &$row) {
            $finalValues = [];
            foreach ($row['scores'] as $score) {
                if ($score['final_score'] !== null) {
                    $finalValues[] = (float) $score['final_score'];
                }
            }
            $row['average'] = $finalValues
                ? round(array_sum($finalValues) / count($finalValues), 2)
                : null;
        }
        unset($row);

        return ['ok' => true, 'status' => 200, 'data' => [
            'context' => $context,
            'columns' => $columns,
            'rows' => $rows,
            'summary' => [
                'participant_count' => count($rows),
                'subject_count' => count($columns),
                'result_count' => count($records),
            ],
        ]];
    }

    public function options(): array
    {
        $db = Database::connect();
        return ['ok' => true, 'status' => 200, 'data' => [
            'kegiatan' => $db->table('official_result_pointer AS orp')
                ->select('k.id, k.nama, k.tahun_pelajaran, k.semester')
                ->join('jadwal AS rootj', 'rootj.id = orp.root_jadwal_id')
                ->join('kegiatan AS k', 'k.id = rootj.kegiatan_id')
                ->join('result_snapshot AS rs', 'rs.id = orp.result_snapshot_id')
                ->where('rootj.psych_instrument_id', null)
                ->where('rs.result_type', 'ACADEMIC')
                ->groupBy('k.id, k.nama, k.tahun_pelajaran, k.semester')
                ->orderBy('k.created_at', 'DESC')
                ->get()->getResultArray(),
            'rombel' => $db->table('official_result_pointer AS orp')
                ->select('a.rombel_snapshot AS nama')
                ->join('attempt AS a', 'a.id = orp.attempt_id')
                ->groupBy('a.rombel_snapshot')
                ->orderBy('a.rombel_snapshot', 'ASC')
                ->get()->getResultArray(),
        ]];
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
