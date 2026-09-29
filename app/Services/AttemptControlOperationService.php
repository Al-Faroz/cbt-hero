<?php

namespace App\Services;

class AttemptControlOperationService
{
    public function validKey(string $key): bool
    {
        return (bool) preg_match('/^[A-Za-z0-9._:-]{8,100}$/D', $key);
    }

    public function begin($db, string $key, string $action, string $payloadHash, int $actorId): array
    {
        $row = $db->query(
            'SELECT * FROM attempt_control_operations WHERE idempotency_key = ? FOR UPDATE',
            [$key]
        )->getRowArray();

        if ($row !== null) {
            if ((string) $row['action'] !== $action || (string) $row['payload_hash'] !== $payloadHash)
                return ['ok' => false, 'existing' => $row];
            return ['ok' => true, 'existing' => $row];
        }

        $db->table('attempt_control_operations')->insert([
            'idempotency_key' => $key,
            'action' => $action,
            'payload_hash' => $payloadHash,
            'status' => 'IN_PROGRESS',
            'affected_count' => 0,
            'created_by' => $actorId > 0 ? $actorId : null,
        ]);
        return ['ok' => true, 'existing' => null];
    }

    public function complete($db, string $key, int $affected): void
    {
        $db->table('attempt_control_operations')->where('idempotency_key', $key)->update([
            'status' => 'COMPLETED',
            'affected_count' => max(0, $affected),
            'finished_at' => date('Y-m-d H:i:s'),
        ]);
    }
}
