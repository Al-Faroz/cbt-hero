<?php

namespace App\Services;

class ParticipantOperationService
{
    public function validKey(string $key): bool
    {
        return (bool) preg_match('/^[A-Za-z0-9._:-]{8,100}$/D', $key);
    }

    public function begin($db, string $key, int $pesertaId, string $action, ?int $jadwalId, string $payloadHash): array
    {
        $row = $db->query(
            'SELECT * FROM participant_operations WHERE idempotency_key = ? FOR UPDATE',
            [$key]
        )->getRowArray();

        if ($row !== null) {
            $matches = (int) $row['peserta_id'] === $pesertaId
                && $row['action'] === $action
                && ($jadwalId === null || (int) ($row['jadwal_id'] ?? 0) === $jadwalId)
                && hash_equals((string) $row['payload_hash'], $payloadHash);
            return $matches
                ? ['ok' => true, 'existing' => $row]
                : ['ok' => false, 'code' => 'IDEMPOTENCY_CONFLICT'];
        }

        $db->table('participant_operations')->insert([
            'idempotency_key' => $key,
            'peserta_id' => $pesertaId,
            'action' => $action,
            'jadwal_id' => $jadwalId,
            'payload_hash' => $payloadHash,
            'status' => 'IN_PROGRESS',
        ]);

        return ['ok' => true, 'existing' => null];
    }

    public function complete($db, string $key, int $attemptId): void
    {
        $db->table('participant_operations')->where('idempotency_key', $key)->update([
            'attempt_id' => $attemptId,
            'status' => 'COMPLETED',
            'finished_at' => date('Y-m-d H:i:s'),
        ]);
    }
}
