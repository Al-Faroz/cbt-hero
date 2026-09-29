<?php

namespace App\Services;

use Config\Database;

class AttemptRuntimeService
{
    public function status(int $participantId, int $attemptId, string $clientUuid, int $generation): array
    {
        $db = Database::connect();
        $ownership = new AttemptOwnershipService();
        $attempt = $ownership->owned($db, $participantId, $attemptId);
        if ($attempt === null) return $this->error(404, 'NOT_FOUND', 'Attempt tidak ditemukan.');

        if ($attempt['status'] === 'ACTIVE') {
            $clientError = $ownership->validateClient($attempt, $clientUuid, $generation);
            if ($clientError !== null)
                return $this->error($clientError['status'], $clientError['code'], $clientError['message']);
        }

        return ['ok' => true, 'status' => 200, 'data' => [
            'attempt_id' => $attemptId,
            'status' => (string) $attempt['status'],
            'start_at' => $ownership->iso((string) $attempt['start_at']),
            'deadline_at' => $ownership->iso((string) $attempt['deadline_at']),
            'remaining_seconds' => $ownership->remainingSeconds($attempt),
            'paused' => $attempt['pause_started_at'] !== null,
            'client_generation' => (int) $attempt['client_generation'],
            'server_sync_revision' => (int) $attempt['server_sync_revision'],
            'last_sync_at' => $attempt['last_sync_at'] === null
                ? null : $ownership->iso((string) $attempt['last_sync_at']),
            'scoring_status' => (string) $attempt['scoring_status'],
            'revision_changes' => $attempt['status'] === 'ACTIVE'
                ? (new QuestionRuntimeItemService())->revisionChanges(
                    $db,
                    $attemptId,
                    (int) $attempt['prepared_assignment_id']
                )
                : [],
        ]];
    }

    public function revisions(
        int $participantId,
        int $attemptId,
        string $clientUuid,
        int $generation,
        int $since
    ): array {
        $db = Database::connect();
        $ownership = new AttemptOwnershipService();
        $attempt = $ownership->owned($db, $participantId, $attemptId);
        if ($attempt === null) return $this->error(404, 'NOT_FOUND', 'Attempt tidak ditemukan.');
        if ($attempt['status'] !== 'ACTIVE') {
            return ['ok' => true, 'status' => 200, 'data' => [
                'attempt_id' => $attemptId,
                'server_sync_revision' => (int) $attempt['server_sync_revision'],
                'revision_changes' => [],
            ]];
        }

        $clientError = $ownership->validateClient($attempt, $clientUuid, $generation);
        if ($clientError !== null) {
            return $this->error($clientError['status'], $clientError['code'], $clientError['message']);
        }

        $current = (int) $attempt['server_sync_revision'];
        $changes = $current > max(0, $since)
            ? (new QuestionRuntimeItemService())->revisionChanges(
                $db,
                $attemptId,
                (int) $attempt['prepared_assignment_id']
            )
            : [];

        return ['ok' => true, 'status' => 200, 'data' => [
            'attempt_id' => $attemptId,
            'server_sync_revision' => $current,
            'revision_changes' => $changes,
        ]];
    }

    private function error(int $status, string $code, string $message): array
    {
        return compact('status', 'code', 'message') + ['ok' => false];
    }
}
