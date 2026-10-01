<?php

namespace App\Services;

class ExecutionDependencyService
{
    public function activityStructureLocked($db, int $kegiatanId): bool
    {
        if ($kegiatanId < 1) {
            return true;
        }

        if ($db->table('jadwal')
            ->where('kegiatan_id', $kegiatanId)
            ->groupStart()
                ->where('first_attempt_started_at IS NOT NULL', null, false)
                ->orWhere('results_finalized_at IS NOT NULL', null, false)
            ->groupEnd()
            ->countAllResults() > 0) {
            return true;
        }

        if ($db->table('prepared_assignment AS pa')
            ->join('jadwal AS j', 'j.id = pa.generated_for_jadwal_id')
            ->where('j.kegiatan_id', $kegiatanId)
            ->countAllResults() > 0) {
            return true;
        }

        return $db->table('attempt AS a')
            ->join('jadwal AS j', 'j.id = a.jadwal_id')
            ->where('j.kegiatan_id', $kegiatanId)
            ->countAllResults() > 0;
    }

    public function membershipsHavePreparation($db, array $membershipIds): bool
    {
        $ids = array_values(array_unique(array_filter(array_map('intval', $membershipIds))));
        if ($ids === []) {
            return false;
        }

        return $db->table('prepared_assignment')
            ->whereIn('peserta_kegiatan_id', $ids)
            ->countAllResults() > 0;
    }

    public function participantHasActiveAttempt($db, int $participantId): bool
    {
        return $db->table('attempt')
            ->where('peserta_id', $participantId)
            ->where('status', 'ACTIVE')
            ->countAllResults() > 0;
    }
}
