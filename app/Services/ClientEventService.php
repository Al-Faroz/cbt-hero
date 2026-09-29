<?php

namespace App\Services;

use Config\Database;

class ClientEventService
{
    private const EVENTS = ['CACHE_RECOVERED', 'PACKAGE_READY', 'SYNC_RECOVERED', 'CLIENT_WARNING'];

    public function record(int $participantId, int $attemptId, array $payload, string $clientUuid, int $generation): array
    {
        $type = strtoupper(trim(is_scalar($payload['event_type'] ?? null) ? (string) $payload['event_type'] : ''));
        if (!in_array($type, self::EVENTS, true))
            return $this->error(422, 'EVENT_INVALID', 'Jenis event tidak didukung.');

        $metadata = $payload['metadata'] ?? null;
        if ($metadata !== null && !is_array($metadata))
            return $this->error(422, 'EVENT_INVALID', 'Metadata event tidak valid.');
        $encoded = $metadata === null ? null : json_encode($metadata, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        if ($encoded !== null && strlen($encoded) > 4096)
            return $this->error(422, 'EVENT_TOO_LARGE', 'Metadata event terlalu besar.');

        $db = Database::connect();
        $ownership = new AttemptOwnershipService();
        $attempt = $ownership->owned($db, $participantId, $attemptId);
        if ($attempt === null) return $this->error(404, 'NOT_FOUND', 'Attempt tidak ditemukan.');
        $clientError = $ownership->validateClient($attempt, $clientUuid, $generation);
        if ($clientError !== null)
            return $this->error($clientError['status'], $clientError['code'], $clientError['message']);

        $db->table('attempt_client_event')->insert([
            'attempt_id' => $attemptId,
            'client_generation' => $generation,
            'event_type' => $type,
            'metadata_json' => $encoded,
        ]);
        return ['ok' => true, 'status' => 201, 'data' => ['recorded' => true]];
    }

    private function error(int $status, string $code, string $message): array
    {
        return compact('status', 'code', 'message') + ['ok' => false];
    }
}
