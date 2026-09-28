<?php

namespace App\Services;

use Config\Database;
use DateTimeImmutable;
use DateTimeZone;
use RuntimeException;
use Throwable;

class JadwalOperationalService
{
    public function extendStartWindow(int $jadwalId, mixed $value, array $actor): array
    {
        $newLimit = $this->dateTime($value);
        if ($newLimit === null)
            return $this->error(422, 'VALIDATION_FAILED', 'Batas Mulai baru tidak valid.');

        $db = Database::connect(); $db->transBegin();
        try {
            $old = $db->query('SELECT * FROM jadwal WHERE id = ? FOR UPDATE', [$jadwalId])->getRowArray();
            if ($old === null) {
                $db->transRollback(); return $this->error(404, 'NOT_FOUND', 'Jadwal tidak ditemukan.');
            }
            if ($old['results_finalized_at'] !== null) {
                $db->transRollback(); return $this->error(423, 'DATA_LOCKED', 'Jadwal yang hasilnya sudah difinalkan tidak dapat diubah.');
            }
            if ($this->timestamp($newLimit) <= $this->timestamp((string) $old['batas_mulai_at'])) {
                $db->transRollback();
                return $this->error(422, 'VALIDATION_FAILED', 'Batas Mulai hanya boleh diperpanjang.');
            }
            $db->table('jadwal')->where('id', $jadwalId)->update(['batas_mulai_at' => $newLimit]);
            $after = $db->table('jadwal')->where('id', $jadwalId)->get()->getRowArray();
            $this->audit($jadwalId, 'EXTEND_START_WINDOW', $old, $after, $actor);
            if ($db->transStatus() === false || $db->transCommit() === false)
                throw new RuntimeException('Commit perpanjangan Batas Mulai gagal.');
            return ['ok' => true, 'status' => 200, 'item' => $after];
        } catch (Throwable $e) {
            $db->transRollback();
            log_message('error', 'Perpanjang Batas Mulai gagal: {message}', ['message' => $e->getMessage()]);
            return $this->error(409, 'STATE_CONFLICT', 'Batas Mulai tidak dapat diperpanjang.');
        }
    }

    public function resultVisibility(int $jadwalId, mixed $value, array $actor): array
    {
        $visible = $this->boolean($value);
        if ($visible === null)
            return $this->error(422, 'VALIDATION_FAILED', 'Pilihan tampilkan nilai tidak valid.');

        $db = Database::connect(); $db->transBegin();
        try {
            $old = $db->query('SELECT * FROM jadwal WHERE id = ? FOR UPDATE', [$jadwalId])->getRowArray();
            if ($old === null) {
                $db->transRollback(); return $this->error(404, 'NOT_FOUND', 'Jadwal tidak ditemukan.');
            }
            if ($old['psych_instrument_id'] !== null) {
                $db->transRollback(); return $this->error(422, 'VALIDATION_FAILED', 'Hasil Psikologis tidak ditampilkan kepada peserta melalui pengaturan ini.');
            }
            if ($old['first_attempt_started_at'] !== null) {
                $db->transRollback(); return $this->error(423, 'RESULT_VISIBILITY_LOCKED', 'Pengaturan tampilkan nilai sudah terkunci karena ujian telah dimulai.');
            }
            $db->table('jadwal')->where('id', $jadwalId)->update([
                'tampilkan_nilai_saat_selesai' => $visible ? 1 : 0,
            ]);
            $after = $db->table('jadwal')->where('id', $jadwalId)->get()->getRowArray();
            $this->audit($jadwalId, 'RESULT_VISIBILITY', $old, $after, $actor);
            if ($db->transStatus() === false || $db->transCommit() === false)
                throw new RuntimeException('Commit visibilitas hasil gagal.');
            return ['ok' => true, 'status' => 200, 'item' => $after];
        } catch (Throwable $e) {
            $db->transRollback();
            log_message('error', 'Ubah tampilkan nilai gagal: {message}', ['message' => $e->getMessage()]);
            return $this->error(409, 'STATE_CONFLICT', 'Pengaturan tampilkan nilai tidak dapat diubah.');
        }
    }

    public function addTime(int $jadwalId, array $payload, array $actor): array
    {
        $seconds = $this->positive($payload['seconds'] ?? null, 0);
        $scope = strtoupper(trim($this->scalar($payload['scope'] ?? 'ALL_ACTIVE')));
        $ids = $payload['attempt_ids'] ?? [];
        if ($seconds < 1)
            return $this->error(422, 'VALIDATION_FAILED', 'Tambahan waktu harus lebih dari 0 detik.');
        if (!in_array($scope, ['ALL_ACTIVE', 'IDS'], true))
            return $this->error(422, 'VALIDATION_FAILED', 'Scope Tambah Waktu tidak valid.');
        if ($scope === 'IDS' && (!is_array($ids) || !$ids))
            return $this->error(422, 'VALIDATION_FAILED', 'Pilih Attempt aktif yang akan diberi tambahan waktu.');

        $db = Database::connect(); $db->transBegin();
        try {
            $jadwal = $db->query('SELECT * FROM jadwal WHERE id = ? FOR UPDATE', [$jadwalId])->getRowArray();
            if ($jadwal === null) {
                $db->transRollback(); return $this->error(404, 'NOT_FOUND', 'Jadwal tidak ditemukan.');
            }
            if ($jadwal['results_finalized_at'] !== null) {
                $db->transRollback(); return $this->error(423, 'DATA_LOCKED', 'Hasil Jadwal sudah difinalkan.');
            }

            $builder = $db->table('attempt')->select('id, jadwal_id, status, deadline_at, added_seconds')
                ->where('jadwal_id', $jadwalId)->where('status', 'ACTIVE');
            if ($scope === 'IDS') {
                $normalized = array_values(array_unique(array_filter(array_map(
                    static fn($value): int => is_scalar($value) ? (int) $value : 0,
                    $ids
                ), static fn(int $value): bool => $value > 0)));
                if (!$normalized) {
                    $db->transRollback(); return $this->error(422, 'VALIDATION_FAILED', 'Attempt aktif belum dipilih.');
                }
                $builder->whereIn('id', $normalized);
            }
            $attempts = $builder->get()->getResultArray();
            if (!$attempts) {
                $db->transRollback(); return $this->error(409, 'NO_ACTIVE_ATTEMPT', 'Tidak ada Attempt aktif yang dapat diberi tambahan waktu.');
            }
            if ($scope === 'IDS' && count($attempts) !== count($normalized)) {
                $db->transRollback(); return $this->error(422, 'VALIDATION_FAILED', 'Sebagian Attempt yang dipilih bukan Attempt aktif pada Jadwal ini.');
            }

            $attemptIds = [];
            foreach ($attempts as $attempt) {
                $attemptId = (int) $attempt['id'];
                $db->query(
                    'UPDATE attempt SET added_seconds = added_seconds + ?, deadline_at = DATE_ADD(deadline_at, INTERVAL ? SECOND) WHERE id = ? AND status = ?',
                    [$seconds, $seconds, $attemptId, 'ACTIVE']
                );
                $attemptIds[] = $attemptId;
            }

            (new AuditService())->log('MANAGER', (int) ($actor['user_id'] ?? 0),
                'ADD_TIME_JADWAL', 'PELAKSANAAN',
                'Tambah waktu ' . $seconds . ' detik pada ' . count($attemptIds) . ' Attempt Jadwal #' . $jadwalId,
                (string) ($actor['ip'] ?? ''), (string) ($actor['agent'] ?? ''),
                'jadwal', $jadwalId, null, ['seconds' => $seconds, 'attempt_ids' => $attemptIds]);

            if ($db->transStatus() === false || $db->transCommit() === false)
                throw new RuntimeException('Commit Tambah Waktu gagal.');
            return ['ok' => true, 'status' => 200, 'item' => [
                'jadwal_id' => $jadwalId,
                'seconds' => $seconds,
                'affected_count' => count($attemptIds),
                'attempt_ids' => $attemptIds,
            ]];
        } catch (Throwable $e) {
            $db->transRollback();
            log_message('error', 'Tambah Waktu gagal: {message}', ['message' => $e->getMessage()]);
            return $this->error(409, 'STATE_CONFLICT', 'Tambahan waktu tidak dapat diterapkan.');
        }
    }

    private function dateTime(mixed $value): ?string
    {
        $text = trim($this->scalar($value));
        if ($text === '') return null;
        $text = str_replace('T', ' ', $text);
        if (preg_match('/^\d{4}-\d{2}-\d{2} \d{2}:\d{2}$/D', $text)) $text .= ':00';
        if (!preg_match('/^\d{4}-\d{2}-\d{2} \d{2}:\d{2}:\d{2}$/D', $text)) return null;
        $date = DateTimeImmutable::createFromFormat('!Y-m-d H:i:s', $text, $this->timezone());
        $errors = DateTimeImmutable::getLastErrors();
        if ($date === false || ($errors !== false && ($errors['warning_count'] > 0 || $errors['error_count'] > 0))) return null;
        return $date->format('Y-m-d H:i:s');
    }

    private function timestamp(string $value): int
    {
        return (new DateTimeImmutable($value, $this->timezone()))->getTimestamp();
    }

    private function timezone(): DateTimeZone
    {
        return new DateTimeZone((string) config('App')->appTimezone);
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

    private function boolean(mixed $value): ?bool
    {
        if (in_array($value, [true, 1, '1'], true)) return true;
        if (in_array($value, [false, 0, '0'], true)) return false;
        return null;
    }

    private function audit(int $id, string $action, ?array $before, ?array $after, array $actor): void
    {
        (new AuditService())->log('MANAGER', (int) ($actor['user_id'] ?? 0),
            $action, 'MASTER_UJIAN', $action . ' Jadwal #' . $id,
            (string) ($actor['ip'] ?? ''), (string) ($actor['agent'] ?? ''),
            'jadwal', $id, $before, $after);
    }

    private function error(int $status, string $code, string $message): array
    {
        return compact('status', 'code', 'message') + ['ok' => false];
    }
}
