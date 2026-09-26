<?php

namespace App\Services;

use App\Models\MataPelajaranModel;
use Config\Database;
use Throwable;

class MataPelajaranService
{
    private MataPelajaranModel $model;

    public function __construct()
    {
        $this->model = new MataPelajaranModel();
    }

    public function list(array $query): array
    {
        $page = min($this->positiveInt($query['page'] ?? null, 1), 100000);
        $perPage = $this->positiveInt($query['per_page'] ?? null, 25);
        $perPage = in_array($perPage, [25, 50, 100], true) ? $perPage : 25;
        $search = mb_substr(trim($this->scalar($query['q'] ?? null)), 0, 100);
        $status = strtoupper($this->scalar($query['status'] ?? null));
        $sort = $this->scalar($query['sort'] ?? 'urutan');
        $sortFields = ['urutan' => 'urutan', 'kode' => 'kode_mapel',
            'nama' => 'nama_mapel', 'status' => 'status'];
        $sortField = $sortFields[$sort] ?? 'urutan';
        $order = strtolower($this->scalar($query['order'] ?? 'asc')) === 'desc' ? 'DESC' : 'ASC';

        $db = Database::connect();
        $total = (int) $db->table('mata_pelajaran')->countAllResults();
        $builder = $db->table('mata_pelajaran');
        if ($search !== '') {
            $builder->groupStart()->like('kode_mapel', $search)
                ->orLike('nama_mapel', $search)->orLike('singkatan', $search)->groupEnd();
        }
        if (in_array($status, ['ACTIVE', 'INACTIVE'], true)) {
            $builder->where('status', $status);
        }
        $filtered = $builder->countAllResults(false);
        $pages = max(1, (int) ceil($filtered / $perPage));
        $page = min($page, $pages);
        $items = $builder->orderBy($sortField, $order)
            ->orderBy('id', 'ASC')
            ->limit($perPage, ($page - 1) * $perPage)->get()->getResultArray();
        return ['items' => $items, 'pagination' => [
            'page' => $page, 'per_page' => $perPage, 'pages' => $pages,
            'total' => $total, 'filtered' => $filtered,
        ]];
    }

    public function find(int $id): ?array
    {
        return $id > 0 ? $this->model->find($id) : null;
    }

    public function save(array $payload, ?int $id, array $actor): array
    {
        $old = $id === null ? null : $this->find($id);
        if ($id !== null && $old === null) {
            return $this->error(404, 'NOT_FOUND', 'Mata Pelajaran tidak ditemukan.');
        }
        $kode = strtoupper(trim($this->scalar($payload['kode_mapel'] ?? $old['kode_mapel'] ?? null)));
        $nama = trim($this->scalar($payload['nama_mapel'] ?? $old['nama_mapel'] ?? null));
        $singkatan = strtoupper(trim($this->scalar($payload['singkatan'] ?? $old['singkatan'] ?? null)));
        $urutanRaw = $payload['urutan'] ?? $old['urutan'] ?? 0;
        $urutan = filter_var($urutanRaw, FILTER_VALIDATE_INT, ['options' => ['min_range' => 0, 'max_range' => 99999]]);
        $errors = [];
        if (! preg_match('/^[A-Z0-9_-]{1,50}$/D', $kode)) {
            $errors['kode_mapel'] = 'Kode wajib 1–50 huruf kapital, angka, minus, atau garis bawah.';
        }
        if ($nama === '' || mb_strlen($nama) > 150 || strpbrk($nama, '<>') !== false) {
            $errors['nama_mapel'] = 'Nama wajib 1–150 karakter tanpa tag HTML.';
        }
        if (mb_strlen($singkatan) > 50 || strpbrk($singkatan, '<>') !== false) {
            $errors['singkatan'] = 'Singkatan maksimal 50 karakter tanpa tag HTML.';
        }
        if ($urutan === false) {
            $errors['urutan'] = 'Urutan harus angka bulat 0–99999.';
        }
        if (array_key_exists('status', $payload)) {
            $errors['status'] = 'Ubah status melalui endpoint status.';
        }
        if ($errors !== []) {
            return $this->error(422, 'VALIDATION_FAILED', 'Data Mata Pelajaran belum valid.', $errors);
        }
        $db = Database::connect();
        $duplicate = $db->table('mata_pelajaran')->select('id')->where('kode_mapel', $kode)->get()->getRowArray();
        if ($duplicate !== null && (int) $duplicate['id'] !== $id) {
            return $this->error(409, 'STATE_CONFLICT', 'Kode Mata Pelajaran sudah digunakan.');
        }
        $values = ['kode_mapel' => $kode, 'nama_mapel' => $nama,
            'singkatan' => $singkatan === '' ? null : $singkatan, 'urutan' => $urutan];
        if ($id === null) {
            $values['status'] = 'ACTIVE';
        }
        try {
            if ($id === null) {
                if ($this->model->insert($values) === false) {
                    throw new \RuntimeException('Insert Mata Pelajaran gagal.');
                }
                $id = (int) $this->model->getInsertID();
            } elseif ($this->model->update($id, $values) === false) {
                throw new \RuntimeException('Update Mata Pelajaran gagal.');
            }
        } catch (Throwable $e) {
            log_message('error', 'Simpan Mata Pelajaran gagal: {message}', ['message' => $e->getMessage()]);
            return $this->error(409, 'STATE_CONFLICT', 'Mata Pelajaran tidak dapat disimpan. Periksa kode unik.');
        }
        $saved = $this->find($id);
        $this->audit($id, $old === null ? 'CREATE' : 'UPDATE', $old, $saved, $actor);
        return ['ok' => true, 'status' => $old === null ? 201 : 200, 'item' => $saved];
    }

    public function changeStatus(int $id, array $payload, array $actor): array
    {
        $old = $this->find($id);
        if ($old === null) {
            return $this->error(404, 'NOT_FOUND', 'Mata Pelajaran tidak ditemukan.');
        }
        $status = strtoupper(trim($this->scalar($payload['status'] ?? null)));
        if (! in_array($status, ['ACTIVE', 'INACTIVE'], true)) {
            return $this->error(422, 'VALIDATION_FAILED', 'Status tidak valid.',
                ['status' => 'Status hanya ACTIVE atau INACTIVE.']);
        }
        if ($old['status'] !== $status) {
            try {
                if ($this->model->update($id, ['status' => $status]) === false) {
                    throw new \RuntimeException('Update status gagal.');
                }
            } catch (Throwable $e) {
                log_message('error', 'Status Mata Pelajaran gagal: {message}', ['message' => $e->getMessage()]);
                return $this->error(409, 'STATE_CONFLICT', 'Status tidak dapat diubah.');
            }
            $this->audit($id, 'CHANGE_STATUS', $old, $this->find($id), $actor);
        }
        return ['ok' => true, 'status' => 200, 'item' => $this->find($id)];
    }

    public function delete(int $id, array $actor): array
    {
        $old = $this->find($id);
        if ($old === null) {
            return $this->error(404, 'NOT_FOUND', 'Mata Pelajaran tidak ditemukan.');
        }
        if (Database::connect()->table('bank_soal')->where('mapel_id', $id)->countAllResults() > 0) {
            return $this->error(409, 'DEPENDENCY_EXISTS', 'Mata Pelajaran sudah digunakan Bank Soal.');
        }
        try {
            if ($this->model->delete($id) === false) {
                throw new \RuntimeException('Hapus Mata Pelajaran gagal.');
            }
        } catch (Throwable $e) {
            log_message('error', 'Hapus Mata Pelajaran gagal: {message}', ['message' => $e->getMessage()]);
            return $this->error(409, 'DEPENDENCY_EXISTS', 'Mata Pelajaran tidak dapat dihapus karena masih digunakan.');
        }
        $this->audit($id, 'DELETE', $old, null, $actor);
        return ['ok' => true, 'status' => 200, 'item' => $old];
    }

    private function audit(int $id, string $action, ?array $before, ?array $after, array $actor): void
    {
        (new AuditService())->log('MANAGER', (int) ($actor['user_id'] ?? 0),
            $action . '_MAPEL', 'MASTER_DATA',
            $action . ' Mata Pelajaran ' . ($after['kode_mapel'] ?? $before['kode_mapel'] ?? $id),
            (string) ($actor['ip'] ?? ''), (string) ($actor['agent'] ?? ''),
            'mata_pelajaran', $id, $before, $after);
    }

    private function positiveInt(mixed $value, int $default): int
    {
        $parsed = filter_var($value, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
        return is_int($parsed) ? $parsed : $default;
    }

    private function scalar(mixed $value): string
    {
        return is_scalar($value) ? (string) $value : '';
    }

    private function error(int $status, string $code, string $message, array $fields = []): array
    {
        return compact('status', 'code', 'message', 'fields') + ['ok' => false];
    }
}
