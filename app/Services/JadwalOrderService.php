<?php

namespace App\Services;

class JadwalOrderService
{
    /**
     * Return the first lower-order exam that the participant must finish
     * before START on the supplied MAIN schedule. Null means order gate passes.
     *
     * Slot identity is intentionally strict: same Kegiatan, same Mulai,
     * same Batas Mulai, MAIN schedule.
     */
    public function blockerForStart(
        $db,
        array $schedule,
        int $participantId,
        int $pesertaKegiatanId,
        int $participantLevel
    ): ?array {
        $order = max(1, (int) ($schedule['urutan_ujian'] ?? 1));

        if (($schedule['jenis_jadwal'] ?? '') !== 'MAIN' || $order <= 1) {
            return null;
        }

        $rows = $db->query(
            "SELECT j.id, j.urutan_ujian, b.tingkat, b.nama_bank, m.nama_mapel,
                    (SELECT COUNT(*) FROM jadwal_peserta_target jt
                     WHERE jt.jadwal_id = j.id AND jt.status = 'TARGETED') AS target_count,
                    (SELECT COUNT(*) FROM jadwal_peserta_target jo
                     WHERE jo.jadwal_id = j.id
                       AND jo.peserta_kegiatan_id = ?
                       AND jo.status = 'TARGETED') AS own_target_count
             FROM jadwal j
             LEFT JOIN bank_soal b ON b.id = j.bank_soal_id
             LEFT JOIN mata_pelajaran m ON m.id = b.mapel_id
             WHERE j.kegiatan_id = ?
               AND j.jenis_jadwal = 'MAIN'
               AND j.parent_jadwal_id IS NULL
               AND j.mulai_at = ?
               AND j.batas_mulai_at = ?
               AND j.urutan_ujian < ?
             ORDER BY j.urutan_ujian ASC, j.id ASC",
            [
                $pesertaKegiatanId,
                (int) $schedule['kegiatan_id'],
                (string) $schedule['mulai_at'],
                (string) $schedule['batas_mulai_at'],
                $order,
            ]
        )->getResultArray();

        foreach ($rows as $row) {
            // Academic participant is only bound by lower-order schedules
            // that actually belong to their level.
            if ($row['tingkat'] !== null && (int) $row['tingkat'] !== $participantLevel) {
                continue;
            }

            $targetCount = (int) ($row['target_count'] ?? 0);
            $ownTargetCount = (int) ($row['own_target_count'] ?? 0);
            if ($targetCount > 0 && $ownTargetCount < 1) {
                continue;
            }

            $attempt = $db->table('attempt')
                ->select('id, status')
                ->where('jadwal_id', (int) $row['id'])
                ->where('peserta_id', $participantId)
                ->orderBy('id', 'DESC')
                ->get()
                ->getRowArray();

            if ($attempt !== null && in_array((string) $attempt['status'], ['FINISHED', 'SUPERSEDED'], true)) {
                continue;
            }

            return [
                'jadwal_id' => (int) $row['id'],
                'urutan_ujian' => (int) $row['urutan_ujian'],
                'nama_ujian' => (string) ($row['nama_mapel'] ?: $row['nama_bank'] ?: ('Jadwal #' . $row['id'])),
                'attempt_status' => $attempt['status'] ?? null,
            ];
        }

        return null;
    }
}
