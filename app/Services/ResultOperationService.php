<?php

namespace App\Services;

class ResultOperationService
{
    public function validKey(string $key): bool
    {
        return (bool) preg_match('/^[A-Za-z0-9._:-]{8,100}$/D', $key);
    }

    public function begin($db, string $key, int $jadwalId, string $action, string $payloadHash, int $actorId): array
    {
        $row = $db->query(
            'SELECT * FROM jadwal_operations WHERE idempotency_key = ? FOR UPDATE',
            [$key]
        )->getRowArray();

        if ($row !== null) {
            $same = (string) $row['action'] === $action
                && (int) $row['root_jadwal_id'] === $jadwalId
                && (string) $row['payload_hash'] === $payloadHash;
            return ['ok' => $same, 'existing' => $row];
        }

        $db->table('jadwal_operations')->insert([
            'idempotency_key' => $key,
            'action' => $action,
            'root_jadwal_id' => $jadwalId,
            'result_jadwal_id' => $jadwalId,
            'payload_hash' => $payloadHash,
            'status' => 'IN_PROGRESS',
            'created_by' => $actorId > 0 ? $actorId : null,
        ]);

        return ['ok' => true, 'existing' => null];
    }

    public function complete($db, string $key, int $jadwalId): void
    {
        $db->table('jadwal_operations')->where('idempotency_key', $key)->update([
            'status' => 'COMPLETED',
            'result_jadwal_id' => $jadwalId,
            'finished_at' => date('Y-m-d H:i:s'),
        ]);
    }
}
