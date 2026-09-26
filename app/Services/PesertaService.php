<?php

namespace App\Services;

use App\Models\ParticipantModel;
use Config\Database;
use Throwable;

class PesertaService
{
    private ParticipantModel $model;

    public function __construct()
    {
        $this->model = new ParticipantModel();
    }

    public function list(array $query): array
    {
        $page = min($this->positiveInt($query['page'] ?? null, 1), 100000);
        $perPage = $this->positiveInt($query['per_page'] ?? null, 50);
        $perPage = in_array($perPage, [25, 50, 100], true) ? $perPage : 50;
        $q = mb_substr(trim($this->scalar($query['q'] ?? null)), 0, 100);
        $status = strtoupper($this->scalar($query['status'] ?? null));
        $rombelId = $this->positiveInt($query['rombel_id'] ?? null, 0);
        $sort = $this->scalar($query['sort'] ?? 'nama');
        $sortFields = [
            'nama' => 'p.nama',
            'nisn' => 'p.nisn',
            'rombel' => 'r.display_name',
            'keterangan' => 'p.keterangan',
        ];
        $sortField = $sortFields[$sort] ?? $sortFields['nama'];
        $order = strtolower($this->scalar($query['order'] ?? 'asc')) === 'desc' ? 'DESC' : 'ASC';
        $db = Database::connect();
        $total = (int) $db->table('peserta')->countAllResults();
        $builder = $db->table('peserta AS p')->join('rombel AS r', 'r.id = p.rombel_id');
        if ($q !== '') {
            $builder->groupStart()
                ->like('p.nisn', $q)
                ->orLike('p.nama', $q)
                ->orLike('p.keterangan', $q)
                ->groupEnd();
        }
        if (in_array($status, ['ACTIVE', 'INACTIVE'], true)) {
            $builder->where('p.status', $status);
        }
        if ($rombelId > 0) {
            $builder->where('p.rombel_id', $rombelId);
        }
        $filtered = $builder->countAllResults(false);
        $pages = max(1, (int) ceil($filtered / $perPage));
        $page = min($page, $pages);
        $items = $builder->select([
            'p.id', 'p.nisn', 'p.nama', 'p.jenis_kelamin', 'p.rombel_id',
            'r.display_name AS rombel', 'r.status AS rombel_status',
            'p.status', 'p.keterangan', 'p.credential_status',
            'p.created_at', 'p.updated_at',
        ])->orderBy($sortField, $order)
            ->orderBy('p.id', 'ASC')
            ->limit($perPage, ($page - 1) * $perPage)
            ->get()->getResultArray();
        return ['items' => $items, 'pagination' => [
            'page' => $page, 'per_page' => $perPage, 'pages' => $pages,
            'total' => $total, 'filtered' => $filtered,
        ]];
    }

    public function find(int $id): ?array
    {
        if ($id <= 0) {
            return null;
        }
        return Database::connect()->table('peserta AS p')
            ->select([
                'p.id', 'p.nisn', 'p.nama', 'p.jenis_kelamin', 'p.rombel_id',
                'r.display_name AS rombel', 'r.status AS rombel_status',
                'p.status', 'p.keterangan', 'p.credential_status',
                'p.created_at', 'p.updated_at',
            ])->join('rombel AS r', 'r.id = p.rombel_id')
            ->where('p.id', $id)->get()->getRowArray();
    }

    public function rombelOptions(): array
    {
        return Database::connect()->table('rombel')
            ->select(['id', 'display_name', 'status'])
            ->orderBy('tingkat', 'ASC')->orderBy('kode_rombel', 'ASC')
            ->get()->getResultArray();
    }

    public function save(array $payload, ?int $id, array $actor): array
    {
        $old = $id === null ? null : $this->find($id);
        if ($id !== null && $old === null) {
            return $this->error(404, 'NOT_FOUND', 'Peserta tidak ditemukan.');
        }
        if ($old !== null && $this->locked($id)) {
            return $this->error(423, 'DATA_LOCKED', 'Data Peserta terkunci oleh Kegiatan berjalan.');
        }
        $nisn = trim($this->scalar($payload['nisn'] ?? $old['nisn'] ?? null));
        $nama = trim($this->scalar($payload['nama'] ?? $old['nama'] ?? null));
        $jenisKelamin = strtoupper(trim($this->scalar($payload['jenis_kelamin'] ?? $old['jenis_kelamin'] ?? null)));
        $rombelId = $this->positiveInt($payload['rombel_id'] ?? $old['rombel_id'] ?? null, 0);
        $keterangan = trim($this->scalar($payload['keterangan'] ?? $old['keterangan'] ?? null));
        $errors = [];
        if (! preg_match('/^[0-9]{1,30}$/D', $nisn)) {
            $errors['nisn'] = 'NISN wajib angka, maksimal 30 digit.';
        }
        if ($nama === '' || mb_strlen($nama) > 180 || strpbrk($nama, '<>') !== false) {
            $errors['nama'] = 'Nama wajib 1–180 karakter tanpa tag HTML.';
        }
        if (! in_array($jenisKelamin, ['L', 'P'], true)) {
            $errors['jenis_kelamin'] = 'Jenis kelamin hanya L atau P.';
        }
        if (mb_strlen($keterangan) > 255 || strpbrk($keterangan, '<>') !== false) {
            $errors['keterangan'] = 'Keterangan maksimal 255 karakter tanpa tag HTML.';
        }
        $rombel = $rombelId > 0
            ? Database::connect()->table('rombel')->where('id', $rombelId)->get()->getRowArray()
            : null;
        if ($rombel === null || ($rombel['status'] !== 'ACTIVE'
            && (int) ($old['rombel_id'] ?? 0) !== $rombelId)) {
            $errors['rombel_id'] = 'Pilih Rombel aktif yang tersedia.';
        }
        if (array_key_exists('status', $payload)) {
            $errors['status'] = 'Ubah status melalui endpoint status.';
        }
        if ($errors !== []) {
            return $this->error(422, 'VALIDATION_FAILED', 'Data Peserta belum valid.', $errors);
        }
        $duplicate = Database::connect()->table('peserta')
            ->select('id')->where('nisn', $nisn)->get()->getRowArray();
        if ($duplicate !== null && (int) $duplicate['id'] !== $id) {
            return $this->error(409, 'STATE_CONFLICT', 'NISN sudah digunakan.');
        }
        $values = compact('nisn', 'nama') + [
            'jenis_kelamin' => $jenisKelamin,
            'rombel_id' => $rombelId,
            'keterangan' => $keterangan === '' ? null : $keterangan,
        ];
        if ($id === null) {
            $values['status'] = 'ACTIVE';
            $values['credential_status'] = 'PENDING';
        }
        try {
            if ($id === null) {
                if ($this->model->insert($values) === false) {
                    throw new \RuntimeException('Insert Peserta gagal.');
                }
                $id = (int) $this->model->getInsertID();
            } elseif ($this->model->update($id, $values) === false) {
                throw new \RuntimeException('Update Peserta gagal.');
            }
        } catch (Throwable $e) {
            log_message('error', 'Simpan Peserta gagal: {message}', ['message' => $e->getMessage()]);
            return $this->error(409, 'STATE_CONFLICT', 'Peserta tidak dapat disimpan.');
        }
        $saved = $this->find($id);
        $this->audit($id, $old === null ? 'CREATE' : 'UPDATE', $old, $saved, $actor);
        return ['ok' => true, 'status' => $old === null ? 201 : 200, 'item' => $saved];
    }

    public function changeStatus(int $id, array $payload, array $actor): array
    {
        $old = $this->find($id);
        if ($old === null) {
            return $this->error(404, 'NOT_FOUND', 'Peserta tidak ditemukan.');
        }
        if ($this->locked($id)) {
            return $this->error(423, 'DATA_LOCKED', 'Data Peserta terkunci oleh Kegiatan berjalan.');
        }
        $status = strtoupper(trim($this->scalar($payload['status'] ?? null)));
        if (! in_array($status, ['ACTIVE', 'INACTIVE'], true)) {
            return $this->error(422, 'VALIDATION_FAILED', 'Status Peserta tidak valid.', [
                'status' => 'Status hanya ACTIVE atau INACTIVE.',
            ]);
        }
        if ($old['status'] !== $status) {
            try {
                if ($this->model->update($id, ['status' => $status]) === false) {
                    throw new \RuntimeException('Update status Peserta gagal.');
                }
            } catch (Throwable $e) {
                log_message('error', 'Status Peserta gagal: {message}', ['message' => $e->getMessage()]);
                return $this->error(409, 'STATE_CONFLICT', 'Status Peserta tidak dapat diubah.');
            }
            $this->audit($id, 'CHANGE_STATUS', $old, $this->find($id), $actor);
        }
        return ['ok' => true, 'status' => 200, 'item' => $this->find($id)];
    }

    private function locked(int $id): bool
    {
        return Database::connect()->table('peserta_kegiatan AS pk')
            ->join('kegiatan AS k', 'k.id = pk.kegiatan_id')
            ->where('pk.peserta_id', $id)
            ->where('k.status', 'BERJALAN')
            ->countAllResults() > 0;
    }

    private function audit(int $id, string $action, ?array $before, ?array $after, array $actor): void
    {
        (new AuditService())->log(
            'MANAGER', (int) ($actor['user_id'] ?? 0),
            $action . '_PESERTA', 'MASTER_DATA',
            $action . ' Peserta ' . ($after['nisn'] ?? $before['nisn'] ?? $id),
            (string) ($actor['ip'] ?? ''), (string) ($actor['agent'] ?? ''),
            'peserta', $id, $before, $after
        );
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
