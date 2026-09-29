<?php

namespace App\Services;

use Config\Database;
use DateTimeImmutable;
use Throwable;

class AnswerSyncService
{
    private const MAX_BATCH = 50;

    public function sync(int $participantId, int $attemptId, array $payload, string $clientUuid, int $generation): array
    {
        $mutations = $payload['mutations'] ?? null;
        if (!is_array($mutations) || count($mutations) < 1 || count($mutations) > self::MAX_BATCH)
            return $this->error(422, 'SYNC_BATCH_INVALID', 'Sync harus berisi 1–' . self::MAX_BATCH . ' perubahan jawaban.');

        $db = Database::connect(); $db->transBegin();
        try {
            $ownership = new AttemptOwnershipService();
            $attempt = $ownership->owned($db, $participantId, $attemptId, true);
            if ($attempt === null) {
                $db->transRollback(); return $this->error(404, 'NOT_FOUND', 'Attempt tidak ditemukan.');
            }
            if ($attempt['status'] !== 'ACTIVE') {
                $db->transRollback(); return $this->error(409, 'ATTEMPT_NOT_ACTIVE', 'Attempt sudah tidak aktif.');
            }
            if ($attempt['pause_started_at'] !== null) {
                $db->transRollback(); return $this->error(423, 'ATTEMPT_PAUSED', 'Akses Attempt sedang di-reset.');
            }
            $clientError = $ownership->validateClient($attempt, $clientUuid, $generation);
            if ($clientError !== null) {
                $db->transRollback();
                return $this->error($clientError['status'], $clientError['code'], $clientError['message']);
            }

            $baseRevision = is_scalar($payload['base_server_revision'] ?? null)
                ? max(0, (int) $payload['base_server_revision']) : 0;
            if ($baseRevision > (int) $attempt['server_sync_revision']) {
                $db->transRollback();
                return $this->error(409, 'SYNC_REVISION_AHEAD', 'Revisi sync client lebih baru daripada server.');
            }

            $itemIds = [];
            foreach ($mutations as $mutation) {
                if (is_array($mutation) && is_scalar($mutation['item_id'] ?? null))
                    $itemIds[] = (int) $mutation['item_id'];
            }
            $itemIds = array_values(array_unique(array_filter($itemIds, static fn(int $id): bool => $id > 0)));
            $itemRows = $itemIds ? $db->table('prepared_assignment_item AS pai')
                ->select('pai.id AS item_id, sr.id AS soal_revision_id, pai.mapping_json, sr.question_type, sr.max_point, '
                    . 'COALESCE(btc.scoring_mode, sr.scoring_mode) AS scoring_mode, '
                    . 'sr.short_answer_mode, sr.expected_numeric, sr.numeric_tolerance', false)
                ->join('soal AS s', 's.id = pai.soal_id')
                ->join('soal_revision AS sr', 'sr.soal_id = s.id AND sr.revision_no = s.current_revision_no', 'left', false)
                ->join(
                    'bank_type_config AS btc',
                    'btc.bank_soal_id = s.bank_soal_id AND btc.question_type = sr.question_type',
                    'left',
                    false
                )
                ->where('pai.prepared_assignment_id', (int) $attempt['prepared_assignment_id'])
                ->whereIn('pai.id', $itemIds)->get()->getResultArray() : [];
            $items = array_column($itemRows, null, 'item_id');

            $serverRevision = (int) $attempt['server_sync_revision'];
            $acks = [];
            $answerService = new AcademicAnswerService();

            foreach ($mutations as $mutation) {
                $ack = $this->processMutation(
                    $db, $attempt, $items, $mutation, $answerService, $serverRevision, $baseRevision
                );
                $serverRevision = $ack['server_revision_after'];
                unset($ack['server_revision_after']);
                $acks[] = $ack;
            }

            $now = date('Y-m-d H:i:s');
            $db->table('attempt')->where('id', $attemptId)->update([
                'server_sync_revision' => $serverRevision,
                'last_sync_at' => $now,
                'last_activity_at' => $now,
            ]);
            if ($db->transStatus() === false || $db->transCommit() === false)
                throw new \RuntimeException('Commit sync gagal.');

            $fresh = $ownership->owned($db, $participantId, $attemptId);
            return ['ok' => true, 'status' => 200, 'data' => [
                'attempt_status' => 'ACTIVE',
                'deadline_at' => $ownership->iso((string) $fresh['deadline_at']),
                'remaining_seconds' => $ownership->remainingSeconds($fresh),
                'server_sync_revision' => $serverRevision,
                'acks' => $acks,
                'revision_changes' => (new QuestionRuntimeItemService())->revisionChanges(
                    $db,
                    $attemptId,
                    (int) $attempt['prepared_assignment_id']
                ),
            ]];
        } catch (Throwable $e) {
            $db->transRollback();
            log_message('error', 'Answer Sync gagal: {message}', ['message' => $e->getMessage()]);
            return $this->error(409, 'SYNC_FAILED', 'Jawaban belum dapat disinkronkan. Coba kembali.');
        }
    }

    private function processMutation(
        $db,
        array $attempt,
        array $items,
        mixed $mutation,
        AcademicAnswerService $answerService,
        int $serverRevision,
        int $baseRevision
    ): array
    {
        $baseAck = [
            'mutation_id' => is_array($mutation) ? (string) ($mutation['mutation_id'] ?? '') : '',
            'item_id' => is_array($mutation) ? (int) ($mutation['item_id'] ?? 0) : 0,
            'client_revision' => is_array($mutation) ? (int) ($mutation['client_revision'] ?? 0) : 0,
            'accepted' => false,
            'server_revision' => $serverRevision,
            'reason' => null,
        ];
        if (!is_array($mutation)) return $baseAck + ['reason' => 'INVALID_MUTATION', 'server_revision_after' => $serverRevision];

        $mutationId = trim((string) ($mutation['mutation_id'] ?? ''));
        $itemId = is_scalar($mutation['item_id'] ?? null) ? (int) $mutation['item_id'] : 0;
        $clientRevision = is_scalar($mutation['client_revision'] ?? null) ? (int) $mutation['client_revision'] : 0;
        $isFlagged = $mutation['is_flagged'] ?? false;
        if (!preg_match('/^[A-Za-z0-9._:-]{8,100}$/D', $mutationId) || $itemId < 1 || $clientRevision < 1 || !is_bool($isFlagged))
            return $baseAck + ['reason' => 'INVALID_MUTATION', 'server_revision_after' => $serverRevision];

        $item = $items[$itemId] ?? null;
        if ($item === null)
            return $baseAck + ['reason' => 'ITEM_NOT_ASSIGNED', 'server_revision_after' => $serverRevision];

        $existing = $db->table('attempt_response')->where('attempt_id', (int) $attempt['id'])
            ->where('prepared_assignment_item_id', $itemId)->get()->getRowArray();
        if ($existing !== null
            && (int) $existing['client_revision'] === 0
            && $existing['answer_payload'] === null
            && $existing['last_mutation_id'] === null
            && (int) $existing['server_revision'] > $baseRevision) {
            $baseAck['server_revision'] = (int) $existing['server_revision'];
            $baseAck['reason'] = 'REANSWER_REQUIRED';
            return $baseAck + ['server_revision_after' => $serverRevision];
        }
        if ($existing !== null && (string) ($existing['last_mutation_id'] ?? '') === $mutationId) {
            $baseAck['accepted'] = true;
            $baseAck['server_revision'] = (int) $existing['server_revision'];
            $baseAck['reason'] = 'DUPLICATE_ACK';
            return $baseAck + ['server_revision_after' => $serverRevision];
        }
        if ($existing !== null && $clientRevision <= (int) $existing['client_revision']) {
            $baseAck['server_revision'] = (int) $existing['server_revision'];
            $baseAck['reason'] = 'STALE_REVISION';
            return $baseAck + ['server_revision_after' => $serverRevision];
        }

        if (!$this->withinAuthority($attempt, $mutation)) {
            $baseAck['reason'] = 'TIME_EXPIRED';
            return $baseAck + ['server_revision_after' => $serverRevision];
        }

        $normalizedPayload = $this->normalizeClientPayload($item, $mutation['answer_payload'] ?? null);
        if (!($normalizedPayload['ok'] ?? false)) {
            $baseAck['reason'] = 'ANSWER_INVALID';
            return $baseAck + ['server_revision_after' => $serverRevision];
        }

        $validated = $answerService->validate($db, $item, $normalizedPayload['payload']);
        if (!($validated['ok'] ?? false)) {
            $baseAck['reason'] = 'ANSWER_INVALID';
            return $baseAck + ['server_revision_after' => $serverRevision];
        }

        $serverRevision++;
        $answerJson = $validated['payload'] === null
            ? null
            : json_encode($validated['payload'], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        $answeredAt = $this->clientDate($mutation['answered_at_client'] ?? null);
        $elapsed = is_scalar($mutation['client_elapsed_ms'] ?? null)
            ? max(0, (int) $mutation['client_elapsed_ms']) : null;

        $values = [
            'answer_payload' => $answerJson,
            'client_revision' => $clientRevision,
            'server_revision' => $serverRevision,
            'client_elapsed_ms' => $elapsed,
            'answered_at_client' => $answeredAt,
            'received_at' => date('Y-m-d H:i:s'),
            'is_flagged' => $isFlagged ? 1 : 0,
            'auto_score' => $validated['auto_score'],
            'effective_score' => $validated['auto_score'],
            'scoring_state' => $validated['scoring_state'],
            'last_mutation_id' => $mutationId,
        ];
        if ($existing === null) {
            $db->table('attempt_response')->insert([
                'attempt_id' => (int) $attempt['id'],
                'prepared_assignment_item_id' => $itemId,
            ] + $values);
        } else {
            $db->table('attempt_response')->where('id', (int) $existing['id'])->update($values);
        }

        $baseAck['accepted'] = true;
        $baseAck['server_revision'] = $serverRevision;
        return $baseAck + ['server_revision_after' => $serverRevision];
    }

    private function withinAuthority(array $attempt, array $mutation): bool
    {
        $ownership = new AttemptOwnershipService();
        $deadline = new DateTimeImmutable((string) $attempt['deadline_at'], $ownership->timezone());
        if ($ownership->now()->getTimestamp() <= $deadline->getTimestamp()) return true;

        $elapsed = is_scalar($mutation['client_elapsed_ms'] ?? null)
            ? (int) $mutation['client_elapsed_ms'] : -1;
        if ($elapsed < 0) return false;
        $limitMs = ((int) $attempt['duration_seconds_snapshot']
            + (int) $attempt['added_seconds']
            + (int) $attempt['paused_seconds']) * 1000;
        if ($elapsed > $limitMs + 5000) return false;

        $answeredAt = $mutation['answered_at_client'] ?? null;
        if (!is_string($answeredAt) || trim($answeredAt) === '') return false;
        try {
            $answered = new DateTimeImmutable($answeredAt);
            $start = new DateTimeImmutable((string) $attempt['start_at'], $ownership->timezone());
        } catch (\Throwable) {
            return false;
        }
        if ($answered->getTimestamp() < $start->getTimestamp() - 5) return false;
        return $answered->getTimestamp() <= $deadline->getTimestamp() + 5;
    }

    private function normalizeClientPayload(array $item, mixed $payload): array
    {
        if ((string) ($item['question_type'] ?? '') !== 'MATCHING')
            return ['ok' => true, 'payload' => $payload];
        if ($payload === null) return ['ok' => true, 'payload' => null];
        if (!is_array($payload) || !is_array($payload['pairs'] ?? null)
            || array_diff(array_keys($payload), ['pairs']) !== [])
            return ['ok' => false];

        $mapping = json_decode((string) ($item['mapping_json'] ?? ''), true);
        if (!is_array($mapping)) return ['ok' => false];
        $leftAlias = is_array($mapping['left_alias'] ?? null) ? $mapping['left_alias'] : [];
        $rightAlias = is_array($mapping['right_alias'] ?? null) ? $mapping['right_alias'] : [];
        if (!$leftAlias || !$rightAlias) return ['ok' => false];

        $leftRaw = array_flip(array_map('strval', $leftAlias));
        $rightRaw = array_flip(array_map('strval', $rightAlias));
        $pairs = [];
        foreach ($payload['pairs'] as $publicLeft => $publicRight) {
            if (!is_string($publicLeft) || !isset($leftRaw[$publicLeft])) return ['ok' => false];
            $rawLeft = (string) $leftRaw[$publicLeft];
            if ($publicRight === null || $publicRight === '') {
                $pairs[$rawLeft] = null;
                continue;
            }
            if (!is_string($publicRight) || !isset($rightRaw[$publicRight])) return ['ok' => false];
            $pairs[$rawLeft] = (string) $rightRaw[$publicRight];
        }
        return ['ok' => true, 'payload' => ['pairs' => $pairs]];
    }

    private function clientDate(mixed $value): ?string
    {
        if (!is_string($value) || trim($value) === '') return null;
        try {
            $date = new DateTimeImmutable($value);
            return $date->setTimezone((new AttemptOwnershipService())->timezone())->format('Y-m-d H:i:s');
        } catch (\Throwable) {
            return null;
        }
    }

    private function error(int $status, string $code, string $message): array
    {
        return compact('status', 'code', 'message') + ['ok' => false];
    }
}
