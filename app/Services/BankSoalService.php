<?php

namespace App\Services;

use Config\Database;
use RuntimeException;
use Throwable;

class BankSoalService
{
    public function list(array $query): array
    {
        $db = Database::connect();
        $page = min($this->positive($query['page'] ?? null, 1), 100000);
        $size = $this->positive($query['per_page'] ?? null, 25);
        $size = in_array($size, [25, 50, 100], true) ? $size : 25;
        $kegiatanId = $this->positive($query['kegiatan_id'] ?? null, 0);
        $q = mb_substr(trim($this->scalar($query['q'] ?? null)), 0, 100);
        $total = (int) $db->table('bank_soal')->countAllResults();
        $builder = $db->table('bank_soal AS b')
            ->join('kegiatan AS k', 'k.id = b.kegiatan_id')
            ->join('mata_pelajaran AS m', 'm.id = b.mapel_id');
        if ($kegiatanId > 0) $builder->where('b.kegiatan_id', $kegiatanId);
        if ($q !== '') $builder->groupStart()->like('b.nama_bank', $q)
            ->orLike('m.nama_mapel', $q)->orLike('k.nama', $q)->groupEnd();
        $filtered = (int) $builder->countAllResults(false);
        $pages = max(1, (int) ceil($filtered / $size));
        $page = min($page, $pages);
        $items = $builder->select('b.id, b.kegiatan_id, b.mapel_id, b.tingkat, b.nama_bank, b.status, '
            . 'b.version_no, k.nama AS kegiatan_nama, k.status AS kegiatan_status, '
            . 'm.nama_mapel AS mapel_nama, m.status AS mapel_status')
            ->orderBy('b.id', 'DESC')->limit($size, ($page - 1) * $size)->get()->getResultArray();
        return ['items' => $items, 'pagination' => compact('page', 'pages', 'total', 'filtered') + ['per_page' => $size]];
    }

    public function options(): array
    {
        $db = Database::connect();
        return [
            'kegiatan' => $db->table('kegiatan')->select('id, nama, tahun_pelajaran, status')
                ->where('jenis', 'AKADEMIK')->orderBy('id', 'DESC')->get()->getResultArray(),
            'mapel' => $db->table('mata_pelajaran')->select('id, nama_mapel, status')
                ->orderBy('urutan', 'ASC')->orderBy('nama_mapel', 'ASC')->get()->getResultArray(),
        ];
    }

    public function find(int $id): ?array
    {
        return $id > 0 ? Database::connect()->table('bank_soal')->where('id', $id)->get()->getRowArray() : null;
    }

    public function save(array $payload, ?int $id, array $actor): array
    {
        if (array_intersect(['status', 'version_no', 'fingerprint', 'created_by', 'updated_by'], array_keys($payload)))
            return $this->error(422, 'VALIDATION_FAILED', 'Status dan versi Bank tidak dapat diubah melalui form.');
        $kegiatanId = $this->positive($payload['kegiatan_id'] ?? null, 0);
        $mapelId = $this->positive($payload['mapel_id'] ?? null, 0);
        $tingkat = $this->positive($payload['tingkat'] ?? null, 0);
        $nama = trim($this->scalar($payload['nama_bank'] ?? null));
        $errors = [];
        if ($kegiatanId < 1) $errors['kegiatan_id'] = 'Pilih Kegiatan Akademik.';
        if ($mapelId < 1) $errors['mapel_id'] = 'Pilih Mata Pelajaran.';
        if (! in_array($tingkat, [7, 8, 9], true)) $errors['tingkat'] = 'Tingkat hanya 7, 8, atau 9.';
        if ($nama === '' || mb_strlen($nama) > 180 || strpbrk($nama, '<>') !== false)
            $errors['nama_bank'] = 'Nama Bank wajib 1–180 karakter tanpa tag HTML.';
        if ($errors !== []) return $this->error(422, 'VALIDATION_FAILED', 'Bank Soal belum valid.', $errors);

        $db = Database::connect();
        $db->transBegin();
        try {
            $kegiatan = $db->query('SELECT id, jenis, status FROM kegiatan WHERE id = ? FOR UPDATE',
                [$kegiatanId])->getRowArray();
            if ($kegiatan === null || $kegiatan['jenis'] !== 'AKADEMIK') {
                $db->transRollback(); return $this->error(422, 'VALIDATION_FAILED', 'Kegiatan Akademik tidak tersedia.');
            }
            $old = $id === null ? null : $db->query('SELECT * FROM bank_soal WHERE id = ? FOR UPDATE',
                [$id])->getRowArray();
            if ($id !== null && $old === null) {
                $db->transRollback(); return $this->error(404, 'NOT_FOUND', 'Bank Soal tidak ditemukan.');
            }
            if ($old !== null && (int) $old['kegiatan_id'] !== $kegiatanId) {
                $db->transRollback(); return $this->error(422, 'VALIDATION_FAILED', 'Bank tidak dapat dipindah ke Kegiatan lain.');
            }
            if ($kegiatan['status'] !== 'DRAFT' || ($old !== null && $old['status'] !== 'DRAFT')) {
                $db->transRollback(); return $this->error(423, 'DATA_LOCKED', 'Bank/Kegiatan sudah terkunci.');
            }
            $mapel = $db->table('mata_pelajaran')->select('id, status')->where('id', $mapelId)->get()->getRowArray();
            if ($mapel === null || ($mapel['status'] !== 'ACTIVE' && (int) ($old['mapel_id'] ?? 0) !== $mapelId)) {
                $db->transRollback(); return $this->error(422, 'VALIDATION_FAILED', 'Pilih Mata Pelajaran aktif.');
            }
            if ($old !== null && ((int) $old['mapel_id'] !== $mapelId || (int) $old['tingkat'] !== $tingkat)
                && $db->table('soal')->where('bank_soal_id', $id)->countAllResults() > 0) {
                $db->transRollback(); return $this->error(409, 'DEPENDENCY_EXISTS', 'Bank sudah berisi soal; Mapel/Tingkat tidak dapat diganti.');
            }
            $values = ['kegiatan_id' => $kegiatanId, 'mapel_id' => $mapelId,
                'tingkat' => $tingkat, 'nama_bank' => $nama, 'updated_by' => (int) ($actor['user_id'] ?? 0)];
            if ($old === null) {
                $values['status'] = 'DRAFT'; $values['version_no'] = 1;
                $values['created_by'] = (int) ($actor['user_id'] ?? 0);
                $db->table('bank_soal')->insert($values);
                $id = (int) $db->insertID();
            } else $db->table('bank_soal')->where('id', $id)->update($values);
            $saved = $db->table('bank_soal')->where('id', $id)->get()->getRowArray();
            $this->audit($id, $old === null ? 'CREATE' : 'UPDATE', $old, $saved, $actor);
            if ($db->transStatus() === false || $db->transCommit() === false) throw new RuntimeException('Commit Bank Soal gagal.');
            return ['ok' => true, 'status' => $old === null ? 201 : 200, 'item' => $saved];
        } catch (Throwable $e) {
            $db->transRollback();
            log_message('error', 'Simpan Bank Soal gagal: {message}', ['message' => $e->getMessage()]);
            return $this->error(409, 'STATE_CONFLICT', 'Bank Soal tidak dapat disimpan.');
        }
    }

    public function delete(int $id, array $actor): array
    {
        $db = Database::connect();
        $db->transBegin();
        try {
            $bank = $id > 0 ? $db->table('bank_soal')->select('kegiatan_id')->where('id', $id)->get()->getRowArray() : null;
            if ($bank === null) { $db->transRollback(); return $this->error(404, 'NOT_FOUND', 'Bank tidak ditemukan.'); }
            $kegiatan = $db->query('SELECT status FROM kegiatan WHERE id = ? FOR UPDATE',
                [$bank['kegiatan_id']])->getRowArray();
            $old = $db->query('SELECT * FROM bank_soal WHERE id = ? FOR UPDATE', [$id])->getRowArray();
            if ($old === null || $kegiatan === null) {
                $db->transRollback(); return $this->error(404, 'NOT_FOUND', 'Bank tidak ditemukan.');
            }
            if ($kegiatan['status'] !== 'DRAFT' || $old['status'] !== 'DRAFT') {
                $db->transRollback(); return $this->error(423, 'DATA_LOCKED', 'Bank/Kegiatan sudah terkunci.');
            }
            foreach (['soal', 'jadwal'] as $table) {
                if ($db->table($table)->where('bank_soal_id', $id)->countAllResults() > 0) {
                    $db->transRollback(); return $this->error(409, 'DEPENDENCY_EXISTS', 'Bank sudah dipakai Soal atau Jadwal.');
                }
            }
            $db->table('bank_soal')->where('id', $id)->delete();
            $this->audit($id, 'DELETE', $old, null, $actor);
            if ($db->transStatus() === false || $db->transCommit() === false) throw new RuntimeException('Commit hapus Bank gagal.');
            return ['ok' => true, 'status' => 200, 'item' => $old];
        } catch (Throwable $e) {
            $db->transRollback();
            log_message('error', 'Hapus Bank Soal gagal: {message}', ['message' => $e->getMessage()]);
            return $this->error(409, 'DEPENDENCY_EXISTS', 'Bank Soal tidak dapat dihapus.');
        }
    }

    private function audit(int $id, string $action, ?array $before, ?array $after, array $actor): void
    {
        (new AuditService())->log('MANAGER', (int) ($actor['user_id'] ?? 0),
            $action . '_BANK_SOAL', 'MASTER_UJIAN', $action . ' Bank Soal #' . $id,
            (string) ($actor['ip'] ?? ''), (string) ($actor['agent'] ?? ''), 'bank_soal', $id, $before, $after);
    }

    private function positive(mixed $value, int $default): int
    {
        $parsed = is_scalar($value) ? filter_var($value, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]) : false;
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
