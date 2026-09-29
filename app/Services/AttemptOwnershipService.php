<?php

namespace App\Services;

use DateTimeImmutable;
use DateTimeZone;

class AttemptOwnershipService
{
    public function owned($db, int $participantId, int $attemptId, bool $lock = false): ?array
    {
        if ($participantId < 1 || $attemptId < 1) return null;
        $sql = "SELECT a.*, j.access_state, j.tampilkan_nilai_saat_selesai,
                       j.kegiatan_id, j.bank_soal_id, j.psych_instrument_id, j.jenis_jadwal,
                       k.nama AS kegiatan_nama, k.exam_browser_required,
                       b.nama_bank, b.tingkat, m.nama_mapel
                FROM attempt a
                JOIN jadwal j ON j.id = a.jadwal_id
                JOIN kegiatan k ON k.id = j.kegiatan_id
                LEFT JOIN bank_soal b ON b.id = j.bank_soal_id
                LEFT JOIN mata_pelajaran m ON m.id = b.mapel_id
                WHERE a.id = ? AND a.peserta_id = ?";
        if ($lock) $sql .= ' FOR UPDATE';
        return $db->query($sql, [$attemptId, $participantId])->getRowArray() ?: null;
    }

    public function validateClient(array $attempt, string $clientUuid, int $generation): ?array
    {
        if (!$this->validClientUuid($clientUuid))
            return ['code' => 'CLIENT_ID_INVALID', 'message' => 'Identitas client tidak valid.', 'status' => 422];
        if ($generation < 1 || $generation !== (int) $attempt['client_generation'])
            return ['code' => 'CLIENT_GENERATION_MISMATCH', 'message' => 'Akses ujian sudah berubah. Silakan masuk kembali.', 'status' => 409];
        if ((string) ($attempt['client_uuid'] ?? '') === ''
            || !hash_equals((string) $attempt['client_uuid'], $clientUuid))
            return ['code' => 'CLIENT_MISMATCH', 'message' => 'Ujian aktif pada client lain. Hubungi pengawas bila perlu Reset Akses.', 'status' => 409];
        return null;
    }

    public function validClientUuid(string $value): bool
    {
        return (bool) preg_match('/^[A-Za-z0-9._:-]{16,128}$/D', $value);
    }

    public function remainingSeconds(array $attempt): int
    {
        $deadline = $this->timestamp((string) $attempt['deadline_at']);
        if (!empty($attempt['pause_started_at'])) {
            $at = $this->timestamp((string) $attempt['pause_started_at']);
            return max(0, $deadline - $at);
        }
        return max(0, $deadline - time());
    }

    public function iso(string $value): string
    {
        return (new DateTimeImmutable($value, $this->timezone()))->format(DATE_ATOM);
    }

    public function now(): DateTimeImmutable
    {
        return new DateTimeImmutable('now', $this->timezone());
    }

    public function timezone(): DateTimeZone
    {
        return new DateTimeZone((string) config('App')->appTimezone);
    }

    private function timestamp(string $value): int
    {
        return (new DateTimeImmutable($value, $this->timezone()))->getTimestamp();
    }
}
