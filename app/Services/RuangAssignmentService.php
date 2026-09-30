<?php

namespace App\Services;

use Config\Database;
use RuntimeException;
use Throwable;

class RuangAssignmentService
{
    public function assign(int $kegiatanId, array $payload, array $actor): array
    {
        $scope = strtoupper(is_string($payload['scope'] ?? null) ? trim($payload['scope']) : '');
        if (! in_array($scope, ['ALL', 'TINGKAT', 'ROMBEL', 'IDS'], true))
            return $this->error(422, 'VALIDATION_FAILED', 'Pilih cakupan anggota.');
        if (! array_key_exists('ruang_id', $payload)) return $this->error(422, 'VALIDATION_FAILED', 'Pilih Ruang atau Tanpa Ruang.');
        $roomId = null;
        if ($payload['ruang_id'] !== null) {
            $roomId = filter_var($payload['ruang_id'], FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
            if (! is_int($roomId)) return $this->error(422, 'VALIDATION_FAILED', 'Ruang tidak valid.');
        }
        $value = $payload['value'] ?? null;
        $ids = [];
        if ($scope === 'TINGKAT' && (! is_scalar($value) || ! in_array((string) $value, ['7', '8', '9'], true)))
            return $this->error(422, 'VALIDATION_FAILED', 'Tingkat harus 7, 8, atau 9.');
        if ($scope === 'ROMBEL' && (! is_string($value) || ! preg_match('/^[7-9]-[A-Z0-9]{1,20}$/D', $value)))
            return $this->error(422, 'VALIDATION_FAILED', 'Rombel tidak valid.');
        if ($scope === 'IDS') {
            if (! is_array($value) || count($value) < 1 || count($value) > 100)
                return $this->error(422, 'VALIDATION_FAILED', 'Pilih 1–100 anggota pada halaman ini.');
            foreach ($value as $raw) {
                $id = filter_var($raw, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
                if (! is_int($id) || isset($ids[$id])) return $this->error(422, 'VALIDATION_FAILED', 'Pilihan anggota tidak valid atau duplikat.');
                $ids[$id] = $id;
            }
        }
        $db = Database::connect();
        $db->transBegin();
        try {
            $kegiatan = $db->query('SELECT id, status FROM kegiatan WHERE id = ? FOR UPDATE', [$kegiatanId])->getRowArray();
            if ($kegiatan === null) { $db->transRollback(); return $this->error(404, 'NOT_FOUND', 'Kegiatan tidak ditemukan.'); }
            if ((new ExecutionDependencyService())->activityStructureLocked($db, $kegiatanId)) {
                $db->transRollback(); return $this->error(423, 'DATA_LOCKED', 'Penempatan Ruang terkunci karena pelaksanaan ujian sudah pernah dimulai.');
            }
            if ($roomId !== null) {
                $room = $db->query('SELECT id, status FROM ruang WHERE id = ? FOR UPDATE', [$roomId])->getRowArray();
                if ($room === null || $room['status'] !== 'ACTIVE') {
                    $db->transRollback(); return $this->error(422, 'VALIDATION_FAILED', 'Pilih Ruang aktif.');
                }
            }
            $builder = $db->table('peserta_kegiatan')->where('kegiatan_id', $kegiatanId);
            if ($scope === 'IDS') $builder->whereIn('id', array_values($ids));
            if ($scope === 'ROMBEL') $builder->where('rombel_snapshot', $value);
            if ($scope === 'TINGKAT') $builder->like('rombel_snapshot', (string) $value . '-', 'after');
            $selected = (int) $builder->countAllResults(false);
            if ($scope === 'IDS' && $selected !== count($ids)) {
                $db->transRollback(); return $this->error(409, 'STATE_CONFLICT', 'Ada anggota yang bukan bagian dari Kegiatan ini. Muat ulang daftar.');
            }
            if ($selected === 0) { $db->transRollback(); return $this->error(409, 'STATE_CONFLICT', 'Tidak ada anggota dalam cakupan ini.'); }
            if ($builder->update(['ruang_id' => $roomId]) === false)
                throw new RuntimeException('Update penempatan Ruang gagal.');
            $updated = $db->affectedRows();
            (new AuditService())->log('MANAGER', (int) ($actor['user_id'] ?? 0), 'ASSIGN_RUANG', 'MASTER_UJIAN',
                'Penempatan Ruang Kegiatan #' . $kegiatanId . ', cakupan ' . $scope . ', anggota ' . $selected . ', berubah ' . $updated,
                (string) ($actor['ip'] ?? ''), (string) ($actor['agent'] ?? ''), 'kegiatan', $kegiatanId);
            if ($db->transStatus() === false || $db->transCommit() === false) throw new RuntimeException('Commit penempatan Ruang gagal.');
            return ['ok' => true, 'status' => 200, 'selected' => $selected, 'updated' => $updated];
        } catch (Throwable $e) {
            $db->transRollback(); log_message('error', 'Penempatan Ruang gagal: {message}', ['message' => $e->getMessage()]);
            return $this->error(409, 'STATE_CONFLICT', 'Penempatan Ruang gagal; tidak ada perubahan yang disimpan.');
        }
    }

    private function error(int $status, string $code, string $message): array
    {
        return compact('status', 'code', 'message') + ['ok' => false];
    }
}
