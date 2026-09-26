<?php

namespace App\Services;

use App\Models\RombelModel;
use Config\Database;
use Throwable;

class RombelService
{
    private RombelModel $model;

    public function __construct()
    {
        $this->model = new RombelModel();
    }

    public function list(array $query): array
    {
        $page = $this->positiveInt($query['page'] ?? null, 1);
        $perPage = $this->positiveInt($query['per_page'] ?? null, 25);
        $perPage = in_array($perPage, [25, 50, 100], true) ? $perPage : 25;
        $page = min($page, 100000);
        $search = mb_substr(trim(is_scalar($query['q'] ?? null) ? (string) $query['q'] : ''), 0, 100);
        $tingkat = is_scalar($query['tingkat'] ?? null) ? (string) $query['tingkat'] : '';
        $status = strtoupper(is_scalar($query['status'] ?? null) ? (string) $query['status'] : '');
        $sort = is_scalar($query['sort'] ?? null) ? (string) $query['sort'] : 'tingkat';
        $sortFields = [
            'tingkat' => ['tingkat', 'kode_rombel'],
            'kode' => ['kode_rombel', 'tingkat'],
            'nama' => ['display_name'],
            'status' => ['status', 'tingkat', 'kode_rombel'],
        ];
        if (! isset($sortFields[$sort])) {
            $sort = 'tingkat';
        }

        $db = Database::connect();
        $total = (int) $db->table('rombel')->countAllResults();
        $builder = $db->table('rombel');
        if ($search !== '') {
            $builder->groupStart()
                ->like('display_name', $search)
                ->orLike('kode_rombel', $search)
                ->groupEnd();
        }
        if (in_array($tingkat, ['7', '8', '9'], true)) {
            $builder->where('tingkat', (int) $tingkat);
        }
        if (in_array($status, ['ACTIVE', 'INACTIVE'], true)) {
            $builder->where('status', $status);
        }
        $filtered = $builder->countAllResults(false);
        $pages = max(1, (int) ceil($filtered / $perPage));
        $page = min($page, $pages);
        foreach ($sortFields[$sort] as $field) {
            $builder->orderBy($field, 'ASC');
        }
        $items = $builder->limit($perPage, ($page - 1) * $perPage)->get()->getResultArray();

        return [
            'items' => $items,
            'pagination' => [
                'page' => $page,
                'per_page' => $perPage,
                'pages' => $pages,
                'total' => $total,
                'filtered' => $filtered,
            ],
        ];
    }

    public function find(int $id): ?array
    {
        return $id > 0 ? $this->model->find($id) : null;
    }

    public function save(array $payload, ?int $id, array $actor): array
    {
        $old = $id === null ? null : $this->find($id);
        if ($id !== null && $old === null) {
            return $this->error(404, 'NOT_FOUND', 'Rombel tidak ditemukan.');
        }
        $rawTingkat = $payload['tingkat'] ?? $old['tingkat'] ?? null;
        $tingkat = filter_var($rawTingkat, FILTER_VALIDATE_INT);
        $rawKode = $payload['kode_rombel'] ?? $old['kode_rombel'] ?? '';
        $kode = is_string($rawKode) ? strtoupper(trim($rawKode)) : '';
        $errors = [];
        if (! in_array($tingkat, [7, 8, 9], true)) {
            $errors['tingkat'] = 'Tingkat harus 7, 8, atau 9.';
        }
        if (! preg_match('/^[A-Z0-9]{1,20}$/D', $kode)) {
            $errors['kode_rombel'] = 'Kode Rombel wajib 1–20 huruf/angka tanpa spasi.';
        }
        if (array_key_exists('status', $payload)) {
            $errors['status'] = 'Ubah status melalui endpoint status.';
        }
        if ($errors !== []) {
            return $this->error(422, 'VALIDATION_FAILED', 'Data Rombel belum valid.', $errors);
        }
        $duplicate = Database::connect()->table('rombel')
            ->select('id')
            ->where('tingkat', $tingkat)
            ->where('kode_rombel', $kode)
            ->get()
            ->getRowArray();
        if ($duplicate !== null && (int) $duplicate['id'] !== $id) {
            return $this->error(409, 'STATE_CONFLICT', 'Rombel sudah ada.');
        }
        $values = [
            'tingkat' => $tingkat,
            'kode_rombel' => $kode,
            'display_name' => $tingkat . '-' . $kode,
        ];
        if ($id === null) {
            $values['status'] = 'ACTIVE';
        }
        try {
            if ($id === null) {
                if ($this->model->insert($values) === false) {
                    throw new \RuntimeException('Insert Rombel gagal.');
                }
                $id = (int) $this->model->getInsertID();
            } else {
                if ($this->model->update($id, $values) === false) {
                    throw new \RuntimeException('Update Rombel gagal.');
                }
            }
        } catch (Throwable $e) {
            log_message('error', 'Simpan Rombel gagal: {message}', ['message' => $e->getMessage()]);
            return $this->error(409, 'STATE_CONFLICT', 'Rombel tidak dapat disimpan.');
        }
        $saved = $this->find($id);
        $this->audit($id, $old === null ? 'CREATE' : 'UPDATE', $old, $saved, $actor);
        return ['ok' => true, 'status' => $old === null ? 201 : 200, 'item' => $saved];
    }

    public function changeStatus(int $id, array $payload, array $actor): array
    {
        $old = $this->find($id);
        if ($old === null) {
            return $this->error(404, 'NOT_FOUND', 'Rombel tidak ditemukan.');
        }
        $status = is_string($payload['status'] ?? null) ? strtoupper(trim($payload['status'])) : '';
        if (! in_array($status, ['ACTIVE', 'INACTIVE'], true)) {
            return $this->error(422, 'VALIDATION_FAILED', 'Status tidak valid.', [
                'status' => 'Status hanya ACTIVE atau INACTIVE.',
            ]);
        }
        if ($old['status'] !== $status) {
            try {
                if ($this->model->update($id, ['status' => $status]) === false) {
                    throw new \RuntimeException('Update status Rombel gagal.');
                }
            } catch (Throwable $e) {
                log_message('error', 'Status Rombel gagal: {message}', ['message' => $e->getMessage()]);
                return $this->error(409, 'STATE_CONFLICT', 'Status Rombel tidak dapat diubah.');
            }
            $this->audit($id, 'CHANGE_STATUS', $old, $this->find($id), $actor);
        }
        return ['ok' => true, 'status' => 200, 'item' => $this->find($id)];
    }

    public function delete(int $id, array $actor): array
    {
        $old = $this->find($id);
        if ($old === null) {
            return $this->error(404, 'NOT_FOUND', 'Rombel tidak ditemukan.');
        }
        if (Database::connect()->table('peserta')->where('rombel_id', $id)->countAllResults() > 0) {
            return $this->error(409, 'DEPENDENCY_EXISTS', 'Rombel masih digunakan Peserta.');
        }
        try {
            if ($this->model->delete($id) === false) {
                throw new \RuntimeException('Hapus Rombel gagal.');
            }
        } catch (Throwable $e) {
            log_message('error', 'Hapus Rombel gagal: {message}', ['message' => $e->getMessage()]);
            return $this->error(409, 'DEPENDENCY_EXISTS', 'Rombel tidak dapat dihapus karena masih digunakan.');
        }
        $this->audit($id, 'DELETE', $old, null, $actor);
        return ['ok' => true, 'status' => 200, 'item' => $old];
    }

    private function audit(int $id, string $action, ?array $before, ?array $after, array $actor): void
    {
        (new AuditService())->log(
            'MANAGER',
            (int) ($actor['user_id'] ?? 0),
            $action . '_ROMBEL',
            'MASTER_DATA',
            $action . ' Rombel ' . ($after['display_name'] ?? $before['display_name'] ?? $id),
            (string) ($actor['ip'] ?? ''),
            (string) ($actor['agent'] ?? ''),
            'rombel',
            $id,
            $before,
            $after
        );
    }

    private function positiveInt(mixed $value, int $default): int
    {
        $parsed = filter_var($value, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
        return $parsed === false ? $default : $parsed;
    }

    private function error(int $status, string $code, string $message, array $fields = []): array
    {
        return compact('status', 'code', 'message', 'fields') + ['ok' => false];
    }
}
