<?php

namespace App\Services;

use App\Models\KegiatanModel;
use Config\Database;
use RuntimeException;
use Throwable;

class KegiatanService
{
    private KegiatanModel $model;

    public function __construct()
    {
        $this->model = new KegiatanModel();
    }

    public function defaults(): array
    {
        return (new AcademicSettingsService())->read();
    }

    public function list(array $query): array
    {
        $page = min($this->positiveInt($query['page'] ?? null, 1), 100000);
        $perPage = $this->positiveInt($query['per_page'] ?? null, 25);
        $perPage = in_array($perPage, [25, 50, 100], true) ? $perPage : 25;
        $q = mb_substr(trim($this->scalar($query['q'] ?? null)), 0, 100);
        $status = strtoupper($this->scalar($query['status'] ?? null));
        $jenis = strtoupper($this->scalar($query['jenis'] ?? null));
        $db = Database::connect();
        $total = (int) $db->table('kegiatan')->countAllResults();
        $builder = $db->table('kegiatan');
        if ($q !== '') {
            $builder->groupStart()->like('nama', $q)->orLike('tahun_pelajaran', $q)->groupEnd();
        }
        if (in_array($status, ['DRAFT', 'BERJALAN', 'SELESAI'], true)) {
            $builder->where('status', $status);
        }
        if (in_array($jenis, ['AKADEMIK', 'PSIKOLOGIS'], true)) {
            $builder->where('jenis', $jenis);
        }
        $filtered = (int) $builder->countAllResults(false);
        $pages = max(1, (int) ceil($filtered / $perPage));
        $page = min($page, $pages);
        $items = $builder->orderBy('created_at', 'DESC')->orderBy('id', 'DESC')
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
        if (array_key_exists('status', $payload) || array_key_exists('created_by', $payload)) {
            return $this->error(422, 'VALIDATION_FAILED', 'Status dan pembuat tidak dapat diubah melalui form.');
        }
        $nama = trim($this->scalar($payload['nama'] ?? null));
        $jenis = strtoupper(trim($this->scalar($payload['jenis'] ?? null)));
        $year = trim($this->scalar($payload['tahun_pelajaran'] ?? null));
        $semester = strtoupper(trim($this->scalar($payload['semester'] ?? null)));
        $keterangan = trim($this->scalar($payload['keterangan'] ?? null));
        $browser = $payload['exam_browser_required'] ?? null;
        $errors = [];
        if ($nama === '' || mb_strlen($nama) > 180 || strpbrk($nama, '<>') !== false) {
            $errors['nama'] = 'Nama wajib 1–180 karakter tanpa tag HTML.';
        }
        if (! in_array($jenis, ['AKADEMIK', 'PSIKOLOGIS'], true)) {
            $errors['jenis'] = 'Pilih AKADEMIK atau PSIKOLOGIS.';
        }
        if (! preg_match('/^(20[0-9]{2})\/(20[0-9]{2})$/D', $year, $parts)
            || (int) ($parts[2] ?? 0) !== (int) ($parts[1] ?? 0) + 1) {
            $errors['tahun_pelajaran'] = 'Gunakan format 2026/2027 dengan tahun berurutan.';
        }
        if (! in_array($semester, ['GANJIL', 'GENAP'], true)) {
            $errors['semester'] = 'Pilih GANJIL atau GENAP.';
        }
        if (mb_strlen($keterangan) > 500 || strpbrk($keterangan, '<>') !== false) {
            $errors['keterangan'] = 'Keterangan maksimal 500 karakter tanpa tag HTML.';
        }
        if (! in_array($browser, [true, false, 0, 1, '0', '1'], true)) {
            $errors['exam_browser_required'] = 'Pilihan Exam Browser tidak valid.';
        }
        if ($errors !== []) {
            return $this->error(422, 'VALIDATION_FAILED', 'Data Kegiatan belum valid.', $errors);
        }
        $values = ['nama' => $nama, 'jenis' => $jenis, 'tahun_pelajaran' => $year,
            'semester' => $semester, 'keterangan' => $keterangan === '' ? null : $keterangan,
            'exam_browser_required' => (int) (bool) $browser];
        $db = Database::connect();
        $db->transBegin();
        try {
            $old = $id === null ? null : $this->lockedRow($id);
            if ($id !== null && $old === null) {
                $db->transRollback();
                return $this->error(404, 'NOT_FOUND', 'Kegiatan tidak ditemukan.');
            }
            if ($old !== null && $old['status'] !== 'DRAFT') {
                $db->transRollback();
                return $this->error(423, 'DATA_LOCKED', 'Kegiatan yang sudah berjalan tidak dapat diubah.');
            }
            if ($old !== null && $old['jenis'] !== $jenis
                && $db->table('bank_soal')->where('kegiatan_id', $id)->countAllResults() > 0) {
                $db->transRollback();
                return $this->error(409, 'DEPENDENCY_EXISTS', 'Jenis Kegiatan tidak dapat diubah karena sudah memiliki Bank Soal.');
            }
            if ($id === null) {
                $values['status'] = 'DRAFT';
                $values['created_by'] = (int) ($actor['user_id'] ?? 0);
                if ($this->model->insert($values) === false) {
                    throw new RuntimeException('Insert Kegiatan gagal.');
                }
                $id = (int) $this->model->getInsertID();
            } elseif ($this->model->update($id, $values) === false) {
                throw new RuntimeException('Update Kegiatan gagal.');
            }
            $saved = $this->find($id);
            $this->audit($id, $old === null ? 'CREATE' : 'UPDATE', $old, $saved, $actor);
            if ($db->transStatus() === false || $db->transCommit() === false) {
                throw new RuntimeException('Commit Kegiatan gagal.');
            }
            return ['ok' => true, 'status' => $old === null ? 201 : 200, 'item' => $saved];
        } catch (Throwable $e) {
            $db->transRollback();
            log_message('error', 'Simpan Kegiatan gagal: {message}', ['message' => $e->getMessage()]);
            return $this->error(409, 'STATE_CONFLICT', 'Kegiatan tidak dapat disimpan.');
        }
    }

    public function delete(int $id, array $actor): array
    {
        $db = Database::connect();
        $db->transBegin();
        try {
            $old = $this->lockedRow($id);
            if ($old === null) {
                $db->transRollback();
                return $this->error(404, 'NOT_FOUND', 'Kegiatan tidak ditemukan.');
            }
            if ($old['status'] !== 'DRAFT') {
                $db->transRollback();
                return $this->error(423, 'DATA_LOCKED', 'Hanya Kegiatan DRAFT yang dapat dihapus.');
            }
            foreach (['peserta_kegiatan', 'bank_soal', 'psych_instrument', 'jadwal'] as $table) {
                if ($db->table($table)->where('kegiatan_id', $id)->countAllResults() > 0) {
                    $db->transRollback();
                    return $this->error(409, 'DEPENDENCY_EXISTS',
                        'Kegiatan sudah memiliki Peserta, Bank/Instrumen, atau Jadwal.');
                }
            }
            if ($this->model->delete($id) === false) {
                throw new RuntimeException('Delete Kegiatan gagal.');
            }
            $this->audit($id, 'DELETE', $old, null, $actor);
            if ($db->transStatus() === false || $db->transCommit() === false) {
                throw new RuntimeException('Commit hapus Kegiatan gagal.');
            }
            return ['ok' => true, 'status' => 200, 'item' => $old];
        } catch (Throwable $e) {
            $db->transRollback();
            log_message('error', 'Hapus Kegiatan gagal: {message}', ['message' => $e->getMessage()]);
            return $this->error(409, 'DEPENDENCY_EXISTS', 'Kegiatan tidak dapat dihapus.');
        }
    }

    public function changeStatus(int $id, string $targetStatus, array $actor): array
    {
        $targetStatus = strtoupper(trim($targetStatus));
        if (! in_array($targetStatus, ['BERJALAN', 'SELESAI'], true)) {
            return $this->error(422, 'STATUS_INVALID', 'Status Kegiatan tidak valid.');
        }

        $db = Database::connect();
        $db->transBegin();

        try {
            $old = $this->lockedRow($id);
            if ($old === null) {
                $db->transRollback();
                return $this->error(404, 'NOT_FOUND', 'Kegiatan tidak ditemukan.');
            }

            $current = strtoupper((string) $old['status']);

            if ($targetStatus === 'BERJALAN') {
                if ($current !== 'DRAFT') {
                    $db->transRollback();
                    return $this->error(409, 'STATE_CONFLICT', 'Hanya Kegiatan DRAFT yang dapat dijalankan.');
                }

                $preflight = (new KegiatanPreflightService())->inspect($id);
                if (! is_array($preflight) || ! ($preflight['administrativeReady'] ?? false)) {
                    $db->transRollback();
                    return $this->error(
                        409,
                        'PREFLIGHT_NOT_READY',
                        'Kegiatan belum siap dijalankan. Selesaikan Pemeriksaan Kesiapan terlebih dahulu.'
                    );
                }
            }

            if ($targetStatus === 'SELESAI' && $current !== 'BERJALAN') {
                $db->transRollback();
                return $this->error(409, 'STATE_CONFLICT', 'Hanya Kegiatan BERJALAN yang dapat diselesaikan.');
            }

            if ($this->model->update($id, ['status' => $targetStatus]) === false) {
                throw new RuntimeException('Perubahan status Kegiatan gagal.');
            }

            $saved = $this->find($id);
            $this->audit($id, 'STATUS_' . $targetStatus, $old, $saved, $actor);

            if ($db->transStatus() === false || $db->transCommit() === false) {
                throw new RuntimeException('Commit status Kegiatan gagal.');
            }

            return ['ok' => true, 'status' => 200, 'item' => $saved];
        } catch (Throwable $e) {
            $db->transRollback();
            log_message('error', 'Ubah status Kegiatan gagal: {message}', ['message' => $e->getMessage()]);
            return $this->error(409, 'STATE_CONFLICT', 'Status Kegiatan tidak dapat diubah.');
        }
    }

    private function lockedRow(int $id): ?array
    {
        return $id > 0 ? Database::connect()->query('SELECT * FROM kegiatan WHERE id = ? FOR UPDATE',
            [$id])->getRowArray() : null;
    }

    private function audit(int $id, string $action, ?array $before, ?array $after, array $actor): void
    {
        (new AuditService())->log('MANAGER', (int) ($actor['user_id'] ?? 0),
            $action . '_KEGIATAN', 'MASTER_UJIAN',
            $action . ' Kegiatan ' . ($after['nama'] ?? $before['nama'] ?? $id),
            (string) ($actor['ip'] ?? ''), (string) ($actor['agent'] ?? ''),
            'kegiatan', $id, $before, $after);
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
