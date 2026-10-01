<?php

namespace App\Services;

use App\Models\RuangModel;
use Config\Database;
use RuntimeException;
use Throwable;

class RuangService
{
    public function list(array $query): array
    {
        $page = min($this->positiveInt($query['page'] ?? null, 1), 100000);
        $perPage = $this->positiveInt($query['per_page'] ?? null, 25);
        $perPage = in_array($perPage, [25, 50, 100], true) ? $perPage : 25;
        $search = mb_substr(trim($this->scalar($query['q'] ?? null)), 0, 100);
        $status = strtoupper($this->scalar($query['status'] ?? null));
        $db = Database::connect();
        $total = (int) $db->table('ruang')->countAllResults();
        $builder = $db->table('ruang');
        if ($search !== '') $builder->groupStart()->like('kode', $search)->orLike('nama', $search)->groupEnd();
        if (in_array($status, ['ACTIVE', 'INACTIVE'], true)) $builder->where('status', $status);
        $filtered = (int) $builder->countAllResults(false);
        $pages = max(1, (int) ceil($filtered / $perPage));
        $page = min($page, $pages);
        $items = $builder->orderBy('kode', 'ASC')->orderBy('id', 'ASC')
            ->limit($perPage, ($page - 1) * $perPage)->get()->getResultArray();
        return ['items' => $items, 'pagination' => [
            'page' => $page, 'per_page' => $perPage, 'pages' => $pages,
            'total' => $total, 'filtered' => $filtered,
        ]];
    }

    public function options(): array
    {
        return ['items' => Database::connect()->table('ruang')->select('id, kode, nama')
            ->where('status', 'ACTIVE')->orderBy('kode')->get()->getResultArray()];
    }

    public function save(array $payload, ?int $id, array $actor): array
    {
        if (array_key_exists('status', $payload)) return $this->error(422, 'VALIDATION_FAILED', 'Status diubah melalui aksi status.');
        $kode = strtoupper(trim($this->scalar($payload['kode'] ?? null)));
        $nama = trim($this->scalar($payload['nama'] ?? null));
        $errors = [];
        if (! preg_match('/^[A-Z0-9][A-Z0-9._-]{0,49}$/D', $kode)) $errors['kode'] = 'Kode 1–50 huruf/angka; boleh titik, strip, garis bawah.';
        if ($nama === '' || mb_strlen($nama) > 150 || strpbrk($nama, '<>') !== false) $errors['nama'] = 'Nama wajib 1–150 karakter tanpa tag HTML.';
        if ($errors !== []) return $this->error(422, 'VALIDATION_FAILED', 'Data Ruang belum valid.', $errors);
        $db = Database::connect();
        $db->transBegin();
        try {
            $old = $id === null ? null : $db->query('SELECT * FROM ruang WHERE id = ? FOR UPDATE', [$id])->getRowArray();
            if ($id !== null && $old === null) { $db->transRollback(); return $this->error(404, 'NOT_FOUND', 'Ruang tidak ditemukan.'); }
            if ($old !== null && $this->usedByLockedKegiatan($db, $id)) {
                $db->transRollback(); return $this->error(423, 'DATA_LOCKED', 'Ruang dipakai Kegiatan yang pelaksanaannya sudah pernah dimulai.');
            }
            $duplicate = $db->table('ruang')->select('id')->where('kode', $kode)->get()->getRowArray();
            if ($duplicate !== null && (int) $duplicate['id'] !== $id) {
                $db->transRollback(); return $this->error(409, 'STATE_CONFLICT', 'Kode Ruang sudah digunakan.');
            }
            $model = new RuangModel();
            if ($id === null) {
                if ($model->insert(['kode' => $kode, 'nama' => $nama, 'status' => 'ACTIVE']) === false) throw new RuntimeException('Insert Ruang gagal.');
                $id = (int) $model->getInsertID();
            } elseif ($model->update($id, ['kode' => $kode, 'nama' => $nama]) === false) throw new RuntimeException('Update Ruang gagal.');
            $saved = $model->find($id);
            $this->audit($id, $old === null ? 'CREATE' : 'UPDATE', $actor);
            if ($db->transStatus() === false || $db->transCommit() === false) throw new RuntimeException('Commit Ruang gagal.');
            return ['ok' => true, 'status' => $old === null ? 201 : 200, 'item' => $saved];
        } catch (Throwable $e) {
            $db->transRollback(); log_message('error', 'Simpan Ruang gagal: {message}', ['message' => $e->getMessage()]);
            return $this->error(409, 'STATE_CONFLICT', 'Ruang tidak dapat disimpan.');
        }
    }

    public function changeStatus(int $id, array $payload, array $actor): array
    {
        $status = strtoupper($this->scalar($payload['status'] ?? null));
        if (! in_array($status, ['ACTIVE', 'INACTIVE'], true)) return $this->error(422, 'VALIDATION_FAILED', 'Status Ruang tidak valid.');
        $db = Database::connect(); $db->transBegin();
        try {
            $old = $db->query('SELECT * FROM ruang WHERE id = ? FOR UPDATE', [$id])->getRowArray();
            if ($old === null) { $db->transRollback(); return $this->error(404, 'NOT_FOUND', 'Ruang tidak ditemukan.'); }
            if ($old['status'] !== $status) {
                if ($this->usedByLockedKegiatan($db, $id)) { $db->transRollback(); return $this->error(423, 'DATA_LOCKED', 'Ruang dipakai Kegiatan yang pelaksanaannya sudah pernah dimulai.'); }
                if ($status === 'INACTIVE' && $db->table('peserta_kegiatan')->where('ruang_id', $id)->countAllResults() > 0) {
                    $db->transRollback(); return $this->error(409, 'DEPENDENCY_EXISTS', 'Lepaskan penempatan anggota sebelum menonaktifkan Ruang.');
                }
                if ((new RuangModel())->update($id, ['status' => $status]) === false) throw new RuntimeException('Status Ruang gagal.');
                $this->audit($id, 'CHANGE_STATUS', $actor);
            }
            if ($db->transStatus() === false || $db->transCommit() === false) throw new RuntimeException('Commit status Ruang gagal.');
            return ['ok' => true, 'status' => 200, 'item' => (new RuangModel())->find($id)];
        } catch (Throwable $e) {
            $db->transRollback(); log_message('error', 'Status Ruang gagal: {message}', ['message' => $e->getMessage()]);
            return $this->error(409, 'STATE_CONFLICT', 'Status Ruang tidak dapat diubah.');
        }
    }

    public function delete(int $id, array $actor): array
    {
        $db = Database::connect(); $db->transBegin();
        try {
            $old = $db->query('SELECT * FROM ruang WHERE id = ? FOR UPDATE', [$id])->getRowArray();
            if ($old === null) { $db->transRollback(); return $this->error(404, 'NOT_FOUND', 'Ruang tidak ditemukan.'); }
            if ($db->table('peserta_kegiatan')->where('ruang_id', $id)->countAllResults() > 0) {
                $db->transRollback(); return $this->error(409, 'DEPENDENCY_EXISTS', 'Ruang masih dipakai anggota Kegiatan.');
            }
            if ((new RuangModel())->delete($id) === false) throw new RuntimeException('Hapus Ruang gagal.');
            $this->audit($id, 'DELETE', $actor);
            if ($db->transStatus() === false || $db->transCommit() === false) throw new RuntimeException('Commit hapus Ruang gagal.');
            return ['ok' => true, 'status' => 200, 'item' => $old];
        } catch (Throwable $e) {
            $db->transRollback(); log_message('error', 'Hapus Ruang gagal: {message}', ['message' => $e->getMessage()]);
            return $this->error(409, 'DEPENDENCY_EXISTS', 'Ruang tidak dapat dihapus karena masih dipakai.');
        }
    }

    private function usedByLockedKegiatan($db, int $id): bool
    {
        $rows = $db->table('peserta_kegiatan')
            ->select('kegiatan_id')
            ->where('ruang_id', $id)
            ->groupBy('kegiatan_id')
            ->get()
            ->getResultArray();

        $dependency = new ExecutionDependencyService();
        foreach ($rows as $row) {
            if ($dependency->activityStructureLocked($db, (int) ($row['kegiatan_id'] ?? 0))) {
                return true;
            }
        }
        return false;
    }

    private function audit(int $id, string $action, array $actor): void
    {
        (new AuditService())->log('MANAGER', (int) ($actor['user_id'] ?? 0), $action . '_RUANG', 'MASTER_UJIAN',
            $action . ' Ruang #' . $id, (string) ($actor['ip'] ?? ''), (string) ($actor['agent'] ?? ''), 'ruang', $id);
    }

    private function positiveInt(mixed $raw, int $default): int
    {
        $value = filter_var($raw, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
        return is_int($value) ? $value : $default;
    }

    private function scalar(mixed $raw): string { return is_scalar($raw) ? (string) $raw : ''; }

    private function error(int $status, string $code, string $message, array $fields = []): array
    {
        return compact('status', 'code', 'message', 'fields') + ['ok' => false];
    }
}
