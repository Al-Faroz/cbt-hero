<?php

namespace App\Services;

use Config\Database;
use RuntimeException;
use Throwable;

class NomorPesertaService
{
    public function generate(int $kegiatanId, array $payload, array $actor): array
    {
        $prefix = strtoupper(trim($this->scalar($payload['prefix'] ?? null)));
        $mode = strtoupper(trim($this->scalar($payload['mode'] ?? null)));
        $scope = strtoupper(trim($this->scalar($payload['scope'] ?? null)));
        $start = filter_var($payload['start_sequence'] ?? null, FILTER_VALIDATE_INT,
            ['options' => ['min_range' => 1, 'max_range' => 99999999]]);
        if (! preg_match('/^[A-Z0-9](?:[A-Z0-9-]{0,18}[A-Z0-9])?$/D', $prefix))
            return $this->error(422, 'VALIDATION_FAILED', 'Prefix wajib 1–20 huruf kapital, angka, atau strip (bukan di awal/akhir).');
        if (! in_array($mode, ['FILL_EMPTY', 'REGENERATE'], true))
            return $this->error(422, 'VALIDATION_FAILED', 'Pilih isi nomor kosong atau regenerasi.');
        if (! in_array($scope, ['ALL', 'TINGKAT', 'ROMBEL', 'IDS'], true))
            return $this->error(422, 'VALIDATION_FAILED', 'Pilih cakupan anggota.');
        if (! is_int($start)) return $this->error(422, 'VALIDATION_FAILED', 'Nomor awal harus 1–99999999.');
        $value = $payload['value'] ?? null;
        if ($scope === 'TINGKAT' && (! is_scalar($value) || ! in_array((string) $value, ['7', '8', '9'], true)))
            return $this->error(422, 'VALIDATION_FAILED', 'Tingkat harus 7, 8, atau 9.');
        if ($scope === 'ROMBEL' && (! is_string($value) || ! preg_match('/^[7-9]-[A-Z0-9]{1,20}$/D', $value)))
            return $this->error(422, 'VALIDATION_FAILED', 'Rombel tidak valid.');
        $ids = [];
        if ($scope === 'IDS') {
            if (! is_array($value) || count($value) < 1 || count($value) > 100)
                return $this->error(422, 'VALIDATION_FAILED', 'Pilih 1–100 anggota pada halaman ini.');
            foreach ($value as $raw) {
                $id = filter_var($raw, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
                if (! is_int($id) || isset($ids[$id]))
                    return $this->error(422, 'VALIDATION_FAILED', 'ID anggota tidak valid atau duplikat.');
                $ids[$id] = true;
            }
        }

        $db = Database::connect();
        $db->transBegin();
        try {
            $kegiatan = $db->query('SELECT id, status FROM kegiatan WHERE id = ? FOR UPDATE', [$kegiatanId])->getRowArray();
            if ($kegiatan === null) { $db->transRollback(); return $this->error(404, 'NOT_FOUND', 'Kegiatan tidak ditemukan.'); }
            if ($kegiatan['status'] !== 'DRAFT') {
                $db->transRollback(); return $this->error(423, 'DATA_LOCKED', 'Nomor Peserta hanya dapat diubah saat Kegiatan DRAFT.');
            }
            $rows = $db->query('SELECT id, nomor_peserta, rombel_snapshot FROM peserta_kegiatan WHERE kegiatan_id = ? ORDER BY rombel_snapshot, nama_snapshot, id FOR UPDATE',
                [$kegiatanId])->getResultArray();
            $matched = [];
            foreach ($rows as $row) {
                if ($scope === 'IDS' && ! isset($ids[(int) $row['id']])) continue;
                if ($scope === 'ROMBEL' && $row['rombel_snapshot'] !== $value) continue;
                if ($scope === 'TINGKAT' && ! str_starts_with($row['rombel_snapshot'], (string) $value . '-')) continue;
                $matched[] = $row;
            }
            if ($scope === 'IDS' && count($matched) !== count($ids)) {
                $db->transRollback(); return $this->error(409, 'STATE_CONFLICT', 'Ada anggota yang bukan bagian dari Kegiatan ini. Muat ulang daftar.');
            }
            if ($matched === []) { $db->transRollback(); return $this->error(409, 'STATE_CONFLICT', 'Tidak ada anggota dalam cakupan ini.'); }
            $targets = $mode === 'REGENERATE' ? $matched : array_values(array_filter($matched,
                static fn (array $row): bool => $row['nomor_peserta'] === null || $row['nomor_peserta'] === ''));
            if ($targets === []) { $db->transRollback(); return $this->error(409, 'STATE_CONFLICT', 'Semua anggota dalam cakupan sudah memiliki Nomor Peserta.'); }
            $targetIds = array_fill_keys(array_map(static fn (array $row): int => (int) $row['id'], $targets), true);
            $occupied = [];
            foreach ($rows as $row) {
                if (isset($targetIds[(int) $row['id']]) || $row['nomor_peserta'] === null) continue;
                $occupied[strtoupper((string) $row['nomor_peserta'])] = true;
            }
            $plan = [];
            $sequence = $start;
            foreach ($targets as $row) {
                do {
                    if ($sequence > 99999999) {
                        $db->transRollback(); return $this->error(422, 'VALIDATION_FAILED', 'Rentang nomor tidak cukup untuk cakupan ini.');
                    }
                    $number = $prefix . '-' . str_pad((string) $sequence, 4, '0', STR_PAD_LEFT);
                    $sequence++;
                } while (isset($occupied[$number]));
                $occupied[$number] = true;
                $plan[(int) $row['id']] = $number;
            }
            // Two passes prevent transient UNIQUE collisions when existing numbers swap owners.
            if ($mode === 'REGENERATE') {
                foreach (array_chunk(array_keys($plan), 500) as $chunk) {
                    if ($db->table('peserta_kegiatan')->where('kegiatan_id', $kegiatanId)
                        ->whereIn('id', $chunk)->update(['nomor_peserta' => null]) === false)
                        throw new RuntimeException('Kosongkan nomor lama gagal.');
                }
            }
            foreach (array_chunk($plan, 100, true) as $batch) {
                $cases = []; $bindings = []; $memberIds = [];
                foreach ($batch as $id => $number) {
                    $cases[] = 'WHEN ? THEN ?';
                    $bindings[] = $id; $bindings[] = $number; $memberIds[] = $id;
                }
                $sql = 'UPDATE peserta_kegiatan SET nomor_peserta = CASE id ' . implode(' ', $cases)
                    . ' END WHERE kegiatan_id = ? AND id IN (' . implode(',', array_fill(0, count($memberIds), '?')) . ')';
                if ($db->query($sql, array_merge($bindings, [$kegiatanId], $memberIds)) === false)
                    throw new RuntimeException('Simpan Nomor Peserta gagal.');
            }
            (new AuditService())->log('MANAGER', (int) ($actor['user_id'] ?? 0), 'GENERATE_NOMOR_PESERTA', 'MASTER_UJIAN',
                $mode . ' ' . count($plan) . ' nomor pada Kegiatan #' . $kegiatanId . ', prefix ' . $prefix . ', cakupan ' . $scope,
                (string) ($actor['ip'] ?? ''), (string) ($actor['agent'] ?? ''), 'kegiatan', $kegiatanId);
            if ($db->transStatus() === false || $db->transCommit() === false) throw new RuntimeException('Commit Nomor Peserta gagal.');
            return ['ok' => true, 'status' => 200, 'generated' => count($plan),
                'skipped_existing' => count($matched) - count($plan), 'first' => reset($plan), 'last' => end($plan)];
        } catch (Throwable $e) {
            $db->transRollback(); log_message('error', 'Nomor Peserta gagal: {message}', ['message' => $e->getMessage()]);
            return $this->error(409, 'STATE_CONFLICT', 'Pembuatan Nomor Peserta gagal; tidak ada nomor yang berubah.');
        }
    }

    private function scalar(mixed $raw): string { return is_scalar($raw) ? (string) $raw : ''; }
    private function error(int $status, string $code, string $message): array
    {
        return compact('status', 'code', 'message') + ['ok' => false];
    }
}
