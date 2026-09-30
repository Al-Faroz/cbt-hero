<?php

namespace App\Services;

use Config\Database;
use RuntimeException;
use Throwable;

class PesertaKegiatanService
{
    public function list(int $kegiatanId, array $query): array
    {
        $kegiatan = $this->kegiatan($kegiatanId);
        if ($kegiatan === null) return $this->error(404, 'NOT_FOUND', 'Kegiatan tidak ditemukan.');
        $page = min($this->positiveInt($query['page'] ?? null, 1), 100000);
        $perPage = $this->perPage($query['per_page'] ?? null);
        $q = mb_substr(trim($this->scalar($query['q'] ?? null)), 0, 100);
        $db = Database::connect();
        $total = (int) $db->table('peserta_kegiatan')->where('kegiatan_id', $kegiatanId)->countAllResults();
        $unassigned = (int) $db->table('peserta_kegiatan')->where('kegiatan_id', $kegiatanId)
            ->where('ruang_id IS NULL', null, false)->countAllResults();
        $withoutNumber = (int) $db->table('peserta_kegiatan')->where('kegiatan_id', $kegiatanId)
            ->groupStart()->where('nomor_peserta IS NULL', null, false)
            ->orWhere('nomor_peserta', '')->groupEnd()->countAllResults();
        $builder = $db->table('peserta_kegiatan AS pk')->join('ruang AS r', 'r.id = pk.ruang_id', 'left')
            ->where('pk.kegiatan_id', $kegiatanId);
        if ($q !== '') {
            $builder->groupStart()->like('pk.nisn_snapshot', $q)
                ->orLike('pk.nama_snapshot', $q)->orLike('pk.rombel_snapshot', $q)->groupEnd();
        }
        $filtered = (int) $builder->countAllResults(false);
        $pages = max(1, (int) ceil($filtered / $perPage));
        $page = min($page, $pages);
        $items = $builder->select('pk.id, pk.peserta_id, pk.nisn_snapshot, pk.nama_snapshot, pk.jenis_kelamin_snapshot, pk.rombel_snapshot, pk.assignment_source, pk.assignment_scope, pk.nomor_peserta, pk.ruang_id, r.kode AS ruang_kode, r.nama AS ruang_nama, pk.status, pk.created_at')
            ->orderBy('pk.rombel_snapshot', 'ASC')->orderBy('pk.nama_snapshot', 'ASC')
            ->orderBy('pk.id', 'ASC')->limit($perPage, ($page - 1) * $perPage)
            ->get()->getResultArray();
        $summaryRows = $db->table('peserta_kegiatan')->select('assignment_source, assignment_scope, COUNT(*) AS jumlah', false)
            ->where('kegiatan_id', $kegiatanId)
            ->groupBy(['assignment_source', 'assignment_scope'])
            ->orderBy('assignment_source', 'ASC')->orderBy('assignment_scope', 'ASC')
            ->get()->getResultArray();
        $rombelOptions = $db->table('peserta_kegiatan')->distinct()->select('rombel_snapshot')
            ->where('kegiatan_id', $kegiatanId)->orderBy('rombel_snapshot')->get()->getResultArray();
        $assignRombelOptions = $db->table('rombel')->select('id, display_name')
            ->where('status', 'ACTIVE')->orderBy('tingkat', 'ASC')->orderBy('kode_rombel', 'ASC')
            ->get()->getResultArray();
        return ['ok' => true, 'status' => 200, 'kegiatan' => $kegiatan, 'items' => $items,
            'summary' => $summaryRows, 'rombel_options' => array_column($rombelOptions, 'rombel_snapshot'),
            'assign_rombel_options' => $assignRombelOptions,
            'unassigned_room' => $unassigned,
            'without_number' => $withoutNumber,
            'pagination' => $this->pagination($page, $perPage, $pages, $total, $filtered)];
    }

    public function candidates(int $kegiatanId, array $query): array
    {
        $kegiatan = $this->kegiatan($kegiatanId);
        if ($kegiatan === null) return $this->error(404, 'NOT_FOUND', 'Kegiatan tidak ditemukan.');
        $page = min($this->positiveInt($query['page'] ?? null, 1), 100000);
        $perPage = $this->perPage($query['per_page'] ?? null);
        $q = mb_substr(trim($this->scalar($query['q'] ?? null)), 0, 100);
        $rombelId = $this->positiveInt($query['rombel_id'] ?? null, 0);
        $db = Database::connect();
        $builder = $db->table('peserta AS p')->join('rombel AS r', 'r.id = p.rombel_id')
            ->join('peserta_kegiatan AS pk', 'pk.peserta_id = p.id AND pk.kegiatan_id = ' . (int) $kegiatanId, 'left')
            ->where('p.status', 'ACTIVE')->where('r.status', 'ACTIVE')->where('pk.id IS NULL', null, false);
        if ($rombelId > 0) $builder->where('p.rombel_id', $rombelId);
        if ($q !== '') $builder->groupStart()->like('p.nisn', $q)->orLike('p.nama', $q)->groupEnd();
        $filtered = (int) $builder->countAllResults(false);
        $pages = max(1, (int) ceil($filtered / $perPage));
        $page = min($page, $pages);
        $items = $builder->select('p.id, p.nisn, p.nama, p.jenis_kelamin, r.display_name AS rombel')
            ->orderBy('r.tingkat', 'ASC')->orderBy('r.kode_rombel', 'ASC')
            ->orderBy('p.nama', 'ASC')->orderBy('p.id', 'ASC')
            ->limit($perPage, ($page - 1) * $perPage)->get()->getResultArray();
        return ['ok' => true, 'status' => 200, 'items' => $items,
            'pagination' => $this->pagination($page, $perPage, $pages, $filtered, $filtered)];
    }

    public function assign(int $kegiatanId, array $payload, array $actor): array
    {
        $selector = strtoupper($this->scalar($payload['selector'] ?? null));
        $value = $payload['value'] ?? null;
        if (! in_array($selector, ['ALL', 'TINGKAT', 'ROMBEL', 'IDS'], true)) {
            return $this->error(422, 'VALIDATION_FAILED', 'Pilih cakupan Peserta.');
        }
        if ($selector === 'TINGKAT' && (! is_scalar($value)
            || ! in_array((string) $value, ['7', '8', '9'], true))) {
            return $this->error(422, 'VALIDATION_FAILED', 'Tingkat harus 7, 8, atau 9.');
        }
        if ($selector === 'ROMBEL' && $this->positiveInt($value, 0) === 0) {
            return $this->error(422, 'VALIDATION_FAILED', 'Pilih Rombel.');
        }
        $ids = [];
        if ($selector === 'IDS') {
            if (! is_array($value) || count($value) < 1 || count($value) > 100) {
                return $this->error(422, 'VALIDATION_FAILED', 'Pilih 1–100 Peserta pada halaman aktif.');
            }
            foreach ($value as $raw) {
                $id = filter_var($raw, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
                if (! is_int($id) || in_array($id, $ids, true)) {
                    return $this->error(422, 'VALIDATION_FAILED', 'Pilihan Peserta tidak valid atau duplikat.');
                }
                $ids[] = $id;
            }
        }
        $db = Database::connect();
        $db->transBegin();
        try {
            $kegiatan = $db->query('SELECT id, status FROM kegiatan WHERE id = ? FOR UPDATE', [$kegiatanId])->getRowArray();
            if ($kegiatan === null) {
                $db->transRollback();
                return $this->error(404, 'NOT_FOUND', 'Kegiatan tidak ditemukan.');
            }
            if ((new ExecutionDependencyService())->activityStructureLocked($db, $kegiatanId)) {
                $db->transRollback();
                return $this->error(423, 'DATA_LOCKED', 'Keanggotaan terkunci karena pelaksanaan ujian pada Kegiatan ini sudah pernah dimulai.');
            }
            $builder = $db->table('peserta AS p')->join('rombel AS r', 'r.id = p.rombel_id')
                ->select('p.id, p.nisn, p.nama, p.jenis_kelamin, r.display_name')
                ->where('p.status', 'ACTIVE')->where('r.status', 'ACTIVE');
            if ($selector === 'TINGKAT') $builder->where('r.tingkat', (int) $value);
            if ($selector === 'ROMBEL') $builder->where('r.id', (int) $value);
            if ($selector === 'IDS') $builder->whereIn('p.id', $ids);
            $rows = $builder->orderBy('p.id', 'ASC')->get()->getResultArray();
            if ($selector === 'IDS' && count($rows) !== count($ids)) {
                $db->transRollback();
                return $this->error(422, 'VALIDATION_FAILED', 'Ada Peserta yang tidak aktif atau Rombelnya tidak aktif.');
            }
            if ($rows === []) {
                $db->transRollback();
                return $this->error(409, 'STATE_CONFLICT', 'Tidak ada Peserta aktif dalam cakupan ini.');
            }
            $already = [];
            foreach (array_chunk(array_column($rows, 'id'), 500) as $chunk) {
                foreach ($db->table('peserta_kegiatan')->select('peserta_id')
                    ->where('kegiatan_id', $kegiatanId)->whereIn('peserta_id', $chunk)
                    ->get()->getResultArray() as $member) {
                    $already[(int) $member['peserta_id']] = true;
                }
            }
            $scope = null;
            if ($selector === 'TINGKAT') $scope = (string) $value;
            if ($selector === 'ROMBEL') $scope = $rows[0]['display_name'];
            $inserts = [];
            foreach ($rows as $row) {
                if (isset($already[(int) $row['id']])) continue;
                $inserts[] = [
                    'kegiatan_id' => $kegiatanId, 'peserta_id' => (int) $row['id'],
                    'status' => 'ACTIVE', 'nisn_snapshot' => $row['nisn'],
                    'nama_snapshot' => $row['nama'], 'jenis_kelamin_snapshot' => $row['jenis_kelamin'],
                    'rombel_snapshot' => $row['display_name'],
                    'assignment_source' => $selector, 'assignment_scope' => $scope,
                ];
            }
            $added = count($inserts);
            foreach (array_chunk($inserts, 200) as $chunk) {
                $db->table('peserta_kegiatan')->insertBatch($chunk);
            }
            if ($added > 0) $this->audit($kegiatanId, 'ASSIGN', $added, $actor);
            if ($db->transStatus() === false || $db->transCommit() === false) {
                throw new RuntimeException('Commit keanggotaan gagal.');
            }
            return ['ok' => true, 'status' => 200, 'added' => $added,
                'skipped' => count($rows) - $added, 'selected' => count($rows)];
        } catch (Throwable $e) {
            $db->transRollback();
            log_message('error', 'Assign Peserta Kegiatan gagal: {message}', ['message' => $e->getMessage()]);
            return $this->error(409, 'STATE_CONFLICT', 'Penugasan gagal; tidak ada keanggotaan baru yang disimpan.');
        }
    }

    public function remove(int $kegiatanId, int $membershipId, array $actor): array
    {
        $db = Database::connect();
        $db->transBegin();
        try {
            $kegiatan = $db->query('SELECT id, status FROM kegiatan WHERE id = ? FOR UPDATE', [$kegiatanId])->getRowArray();
            if ($kegiatan === null) {
                $db->transRollback(); return $this->error(404, 'NOT_FOUND', 'Kegiatan tidak ditemukan.');
            }
            if ((new ExecutionDependencyService())->activityStructureLocked($db, $kegiatanId)) {
                $db->transRollback(); return $this->error(423, 'DATA_LOCKED', 'Keanggotaan terkunci karena pelaksanaan ujian sudah pernah dimulai.');
            }
            $row = $db->query('SELECT id FROM peserta_kegiatan WHERE id = ? AND kegiatan_id = ? FOR UPDATE',
                [$membershipId, $kegiatanId])->getRowArray();
            if ($row === null) {
                $db->transRollback(); return $this->error(404, 'NOT_FOUND', 'Keanggotaan tidak ditemukan.');
            }
            if ((new ExecutionDependencyService())->membershipsHavePreparation($db, [$membershipId])) {
                $db->transRollback();
                return $this->error(409, 'DEPENDENCY_EXISTS', 'Keanggotaan sudah mempunyai Prepared Assignment. Batalkan/rebuild dependency sebelum menghapus anggota.');
            }
            $db->table('peserta_kegiatan')->where('id', $membershipId)->delete();
            $this->audit($kegiatanId, 'REMOVE', 1, $actor);
            if ($db->transStatus() === false || $db->transCommit() === false) {
                throw new RuntimeException('Commit hapus keanggotaan gagal.');
            }
            return ['ok' => true, 'status' => 200, 'removed' => $membershipId];
        } catch (Throwable $e) {
            $db->transRollback();
            log_message('error', 'Hapus Peserta Kegiatan gagal: {message}', ['message' => $e->getMessage()]);
            return $this->error(409, 'DEPENDENCY_EXISTS', 'Keanggotaan tidak dapat dihapus karena sudah digunakan.');
        }
    }

    public function bulkRemove(int $kegiatanId, array $payload, array $actor): array
    {
        $rawIds = $payload['ids'] ?? null;
        if (! is_array($rawIds) || count($rawIds) < 1 || count($rawIds) > 100) {
            return $this->error(422, 'VALIDATION_FAILED', 'Pilih 1–100 anggota pada halaman aktif.');
        }
        $ids = [];
        foreach ($rawIds as $raw) {
            $id = filter_var($raw, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
            if (! is_int($id) || in_array($id, $ids, true)) {
                return $this->error(422, 'VALIDATION_FAILED', 'Pilihan anggota tidak valid atau duplikat.');
            }
            $ids[] = $id;
        }
        sort($ids, SORT_NUMERIC);
        $db = Database::connect();
        $db->transBegin();
        try {
            $kegiatan = $db->query('SELECT id, status FROM kegiatan WHERE id = ? FOR UPDATE', [$kegiatanId])->getRowArray();
            if ($kegiatan === null) {
                $db->transRollback(); return $this->error(404, 'NOT_FOUND', 'Kegiatan tidak ditemukan.');
            }
            if ((new ExecutionDependencyService())->activityStructureLocked($db, $kegiatanId)) {
                $db->transRollback(); return $this->error(423, 'DATA_LOCKED', 'Keanggotaan terkunci karena pelaksanaan ujian sudah pernah dimulai.');
            }
            $placeholders = implode(',', array_fill(0, count($ids), '?'));
            $rows = $db->query('SELECT id FROM peserta_kegiatan WHERE kegiatan_id = ? AND id IN (' . $placeholders . ') ORDER BY id FOR UPDATE',
                array_merge([$kegiatanId], $ids))->getResultArray();
            if (count($rows) !== count($ids)) {
                $db->transRollback();
                return $this->error(409, 'STATE_CONFLICT', 'Ada anggota yang sudah tidak tersedia pada Kegiatan ini. Muat ulang daftar.');
            }
            if ((new ExecutionDependencyService())->membershipsHavePreparation($db, $ids)) {
                $db->transRollback();
                return $this->error(409, 'DEPENDENCY_EXISTS', 'Sebagian anggota sudah mempunyai Prepared Assignment dan tidak dapat dihapus langsung.');
            }
            $db->table('peserta_kegiatan')->where('kegiatan_id', $kegiatanId)->whereIn('id', $ids)->delete();
            $this->audit($kegiatanId, 'BULK_REMOVE', count($ids), $actor);
            if ($db->transStatus() === false || $db->transCommit() === false) {
                throw new RuntimeException('Commit hapus massal gagal.');
            }
            return ['ok' => true, 'status' => 200, 'removed' => count($ids)];
        } catch (Throwable $e) {
            $db->transRollback();
            log_message('error', 'Hapus massal Peserta Kegiatan gagal: {message}', ['message' => $e->getMessage()]);
            return $this->error(409, 'DEPENDENCY_EXISTS', 'Hapus massal gagal; keanggotaan tidak berubah.');
        }
    }

    private function kegiatan(int $id): ?array
    {
        return $id > 0 ? Database::connect()->table('kegiatan')->select('id, nama, jenis, tahun_pelajaran, semester, status')
            ->where('id', $id)->get()->getRowArray() : null;
    }

    private function audit(int $kegiatanId, string $action, int $count, array $actor): void
    {
        (new AuditService())->log('MANAGER', (int) ($actor['user_id'] ?? 0),
            $action . '_PESERTA_KEGIATAN', 'MASTER_UJIAN',
            $action . ' ' . $count . ' keanggotaan Kegiatan #' . $kegiatanId,
            (string) ($actor['ip'] ?? ''), (string) ($actor['agent'] ?? ''),
            'kegiatan', $kegiatanId);
    }

    private function pagination(int $page, int $perPage, int $pages, int $total, int $filtered): array
    {
        return ['page' => $page, 'per_page' => $perPage, 'pages' => $pages,
            'total' => $total, 'filtered' => $filtered];
    }

    private function perPage(mixed $raw): int
    {
        $value = $this->positiveInt($raw, 25);
        return in_array($value, [25, 50, 100], true) ? $value : 25;
    }

    private function positiveInt(mixed $raw, int $default): int
    {
        $parsed = filter_var($raw, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
        return is_int($parsed) ? $parsed : $default;
    }

    private function scalar(mixed $raw): string
    {
        return is_scalar($raw) ? (string) $raw : '';
    }

    private function error(int $status, string $code, string $message): array
    {
        return compact('status', 'code', 'message') + ['ok' => false];
    }
}
