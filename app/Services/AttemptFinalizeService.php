<?php

namespace App\Services;

use Config\Database;
use RuntimeException;
use Throwable;

class AttemptFinalizeService
{
    public function finalize(
        int $participantId,
        int $attemptId,
        array $payload,
        string $idempotencyKey,
        string $clientUuid,
        int $generation
    ): array {
        $reason = strtoupper(trim(is_scalar($payload['finish_reason'] ?? null) ? (string) $payload['finish_reason'] : ''));
        if (!in_array($reason, ['SUBMIT', 'TIMEOUT'], true))
            return $this->error(422, 'FINISH_REASON_INVALID', 'Alasan selesai tidak valid.');

        $lastServerRevision = is_scalar($payload['last_server_revision'] ?? null)
            ? max(0, (int) $payload['last_server_revision']) : 0;
        $ops = new ParticipantOperationService();
        if (!$ops->validKey($idempotencyKey))
            return $this->error(422, 'IDEMPOTENCY_REQUIRED', 'Idempotency-Key wajib dan formatnya tidak valid.');

        $hash = hash('sha256', json_encode([
            'attempt_id' => $attemptId,
            'finish_reason' => $reason,
            'last_server_revision' => $lastServerRevision,
            'client_uuid' => $clientUuid,
            'client_generation' => $generation,
        ], JSON_UNESCAPED_SLASHES));

        $db = Database::connect(); $db->transBegin();
        try {
            $ownership = new AttemptOwnershipService();
            $attempt = $ownership->owned($db, $participantId, $attemptId, true);
            if ($attempt === null) {
                $db->transRollback(); return $this->error(404, 'NOT_FOUND', 'Attempt tidak ditemukan.');
            }

            $operation = $ops->begin(
                $db,
                $idempotencyKey,
                $participantId,
                'FINALIZE_ATTEMPT',
                (int) $attempt['jadwal_id'],
                $hash
            );
            if (!($operation['ok'] ?? false)) {
                $db->transRollback();
                return $this->error(409, 'IDEMPOTENCY_CONFLICT', 'Idempotency-Key sudah dipakai untuk request berbeda.');
            }

            if (in_array($attempt['status'], ['FINISHED', 'SUPERSEDED'], true)) {
                $ops->complete($db, $idempotencyKey, $attemptId);
                $db->transCommit();
                return $this->finishedResponse($db, $attempt);
            }
            if ($attempt['status'] !== 'ACTIVE') {
                $db->transRollback();
                return $this->error(409, 'ATTEMPT_NOT_ACTIVE', 'Attempt sudah tidak aktif.');
            }

            $clientError = $ownership->validateClient($attempt, $clientUuid, $generation);
            if ($clientError !== null) {
                $db->transRollback();
                return $this->error($clientError['status'], $clientError['code'], $clientError['message']);
            }
            if ($attempt['pause_started_at'] !== null) {
                $db->transRollback();
                return $this->error(423, 'ATTEMPT_PAUSED', 'Attempt sedang dalam Reset Akses.');
            }
            if ($lastServerRevision > (int) $attempt['server_sync_revision']) {
                $db->transRollback();
                return $this->error(409, 'SYNC_REVISION_AHEAD', 'Revisi sync client lebih baru daripada server.');
            }

            $nowTs = time();
            $deadlineTs = strtotime((string) $attempt['deadline_at']);
            if ($reason === 'TIMEOUT' && $deadlineTs !== false && $nowTs + 2 < $deadlineTs) {
                $db->transRollback();
                return $this->error(409, 'TIMEOUT_TOO_EARLY', 'Waktu ujian belum habis.');
            }
            if ($deadlineTs !== false && $nowTs > $deadlineTs) $reason = 'TIMEOUT';

            $snapshot = (new AcademicScoringService())->snapshot($db, $attempt, true);
            $finishAt = date('Y-m-d H:i:s');

            $db->table('attempt')->where('id', $attemptId)->update([
                'status' => 'FINISHED',
                'finish_reason' => $reason,
                'finish_at' => $finishAt,
                'last_activity_at' => $finishAt,
                'scoring_status' => $snapshot['scoring_status'],
            ]);
            $db->table('attempt_active_lock')->where('attempt_id', $attemptId)->delete();

            $versionRow = $db->table('result_snapshot')->selectMax('snapshot_version', 'max_version')
                ->where('attempt_id', $attemptId)->get()->getRowArray();
            $version = ((int) ($versionRow['max_version'] ?? 0)) + 1;

            $db->table('result_snapshot')->insert([
                'attempt_id' => $attemptId,
                'jadwal_id' => (int) $attempt['jadwal_id'],
                'snapshot_version' => $version,
                'result_type' => 'ACADEMIC',
                'click_score' => $snapshot['click_score'],
                'typed_score' => $snapshot['typed_score'],
                'final_score' => $snapshot['final_score'],
                'scoring_status' => $snapshot['scoring_status'],
                'payload_json' => json_encode($snapshot['payload'], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
                'is_final' => 0,
                'finalized_at' => null,
            ]);
            $resultId = (int) $db->insertID();

            foreach ($snapshot['items'] as $item) {
                $db->table('result_item_snapshot')->insert([
                    'result_snapshot_id' => $resultId,
                    'prepared_assignment_item_id' => $item['item_id'],
                    'question_type' => $item['question_type'],
                    'raw_score' => $item['raw_score'],
                    'max_point' => $item['max_point'],
                    'type_weight_percent' => $item['weight_percent'],
                    'weighted_score' => $item['weighted_score'],
                    'voided' => (int) ($item['voided'] ?? 0),
                    'payload_json' => json_encode([
                        'scoring_state' => $item['scoring_state'],
                        'scoring_revision_id' => (int) ($item['scoring_revision_id'] ?? 0),
                    ], JSON_UNESCAPED_SLASHES),
                ]);
            }

            $db->query(
                'INSERT INTO official_result_pointer
                    (root_jadwal_id, peserta_kegiatan_id, attempt_id, result_snapshot_id)
                 VALUES (?, ?, ?, ?)
                 ON DUPLICATE KEY UPDATE attempt_id = VALUES(attempt_id),
                    result_snapshot_id = VALUES(result_snapshot_id), updated_at = CURRENT_TIMESTAMP',
                [(int) $attempt['root_jadwal_id'], (int) $attempt['peserta_kegiatan_id'], $attemptId, $resultId]
            );

            $ops->complete($db, $idempotencyKey, $attemptId);
            if ($db->transStatus() === false || $db->transCommit() === false)
                throw new RuntimeException('Commit FINALIZE gagal.');

            $fresh = $ownership->owned($db, $participantId, $attemptId);
            return $this->finishedResponse($db, $fresh);
        } catch (Throwable $e) {
            $db->transRollback();
            log_message('error', 'Finalize Attempt gagal: {message}', ['message' => $e->getMessage()]);
            return $this->error(409, 'FINALIZE_FAILED', 'Ujian belum dapat diselesaikan. Sinkronkan jawaban lalu coba kembali.');
        }
    }

    public function forceFinishLocked($db, array $attempt): array
    {
        $attemptId = (int) ($attempt['id'] ?? 0);
        if ($attemptId < 1)
            throw new RuntimeException('Attempt tidak valid.');

        if (in_array((string) ($attempt['status'] ?? ''), ['FINISHED', 'SUPERSEDED'], true)) {
            $snapshot = $db->table('result_snapshot')
                ->where('attempt_id', $attemptId)
                ->orderBy('snapshot_version', 'DESC')->get()->getRowArray();
            return [
                'changed' => false,
                'attempt_id' => $attemptId,
                'result_snapshot_id' => $snapshot === null ? null : (int) $snapshot['id'],
            ];
        }

        if (($attempt['status'] ?? '') !== 'ACTIVE')
            throw new RuntimeException('Attempt sudah tidak aktif.');

        $snapshot = (new AcademicScoringService())->snapshot($db, $attempt, true);
        $finishAt = date('Y-m-d H:i:s');

        $db->table('attempt')->where('id', $attemptId)->update([
            'status' => 'FINISHED',
            'finish_reason' => 'FORCE_FINISH',
            'finish_at' => $finishAt,
            'pause_started_at' => null,
            'last_activity_at' => $finishAt,
            'scoring_status' => $snapshot['scoring_status'],
        ]);
        $db->table('attempt_active_lock')->where('attempt_id', $attemptId)->delete();

        $openPause = $db->table('attempt_pause_event')
            ->where('attempt_id', $attemptId)->where('ended_at', null)
            ->orderBy('id', 'DESC')->get()->getRowArray();
        if ($openPause !== null) {
            $started = strtotime((string) $openPause['started_at']);
            $paused = $started === false ? 0 : max(0, time() - $started);
            $db->table('attempt_pause_event')->where('id', (int) $openPause['id'])->update([
                'ended_at' => $finishAt,
                'paused_seconds' => $paused,
            ]);
            $db->table('attempt')->where('id', $attemptId)->set(
                'paused_seconds',
                'paused_seconds + ' . $paused,
                false
            )->update();
        }

        $versionRow = $db->table('result_snapshot')->selectMax('snapshot_version', 'max_version')
            ->where('attempt_id', $attemptId)->get()->getRowArray();
        $version = ((int) ($versionRow['max_version'] ?? 0)) + 1;

        $db->table('result_snapshot')->insert([
            'attempt_id' => $attemptId,
            'jadwal_id' => (int) $attempt['jadwal_id'],
            'snapshot_version' => $version,
            'result_type' => 'ACADEMIC',
            'click_score' => $snapshot['click_score'],
            'typed_score' => $snapshot['typed_score'],
            'final_score' => $snapshot['final_score'],
            'scoring_status' => $snapshot['scoring_status'],
            'payload_json' => json_encode($snapshot['payload'], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
            'is_final' => $snapshot['scoring_status'] === 'COMPLETE' ? 1 : 0,
            'finalized_at' => $snapshot['scoring_status'] === 'COMPLETE' ? $finishAt : null,
        ]);
        $resultId = (int) $db->insertID();

        foreach ($snapshot['items'] as $item) {
            $db->table('result_item_snapshot')->insert([
                'result_snapshot_id' => $resultId,
                'prepared_assignment_item_id' => $item['item_id'],
                'question_type' => $item['question_type'],
                'raw_score' => $item['raw_score'],
                'max_point' => $item['max_point'],
                'type_weight_percent' => $item['weight_percent'],
                'weighted_score' => $item['weighted_score'],
                'voided' => 0,
                'payload_json' => json_encode([
                    'scoring_state' => $item['scoring_state'],
                ], JSON_UNESCAPED_SLASHES),
            ]);
        }

        $db->query(
            'INSERT INTO official_result_pointer
                (root_jadwal_id, peserta_kegiatan_id, attempt_id, result_snapshot_id)
             VALUES (?, ?, ?, ?)
             ON DUPLICATE KEY UPDATE attempt_id = VALUES(attempt_id),
                result_snapshot_id = VALUES(result_snapshot_id), updated_at = CURRENT_TIMESTAMP',
            [(int) $attempt['root_jadwal_id'], (int) $attempt['peserta_kegiatan_id'], $attemptId, $resultId]
        );

        return [
            'changed' => true,
            'attempt_id' => $attemptId,
            'result_snapshot_id' => $resultId,
            'scoring_status' => $snapshot['scoring_status'],
        ];
    }

    private function finishedResponse($db, array $attempt): array
    {
        $snapshot = $db->table('result_snapshot')
            ->where('attempt_id', (int) $attempt['id'])
            ->orderBy('snapshot_version', 'DESC')->get()->getRowArray();
        $show = (int) $attempt['tampilkan_nilai_saat_selesai'] === 1
            && $attempt['psych_instrument_id'] === null;
        $snapshotPayload = $snapshot === null
            ? []
            : json_decode((string) ($snapshot['payload_json'] ?? ''), true);
        $typedState = is_array($snapshotPayload) && isset($snapshotPayload['typed_score_state'])
            ? (string) $snapshotPayload['typed_score_state']
            : ($snapshot === null
                ? 'NOT_APPLICABLE'
                : ((string) $snapshot['scoring_status'] === 'COMPLETE' ? 'COMPLETE' : 'IN_PROCESS'));

        return ['ok' => true, 'status' => 200, 'data' => [
            'attempt_status' => (string) $attempt['status'],
            'finish_reason' => $attempt['finish_reason'],
            'finish_at' => $attempt['finish_at'] === null
                ? null : (new AttemptOwnershipService())->iso((string) $attempt['finish_at']),
            'show_result' => $show,
            'click_score' => $show && $snapshot !== null ? $snapshot['click_score'] : null,
            'typed_score' => $show && $snapshot !== null ? $snapshot['typed_score'] : null,
            'final_score' => $show && $snapshot !== null ? $snapshot['final_score'] : null,
            'typed_score_state' => $typedState,
            'redirect' => base_url('attempt/' . (int) $attempt['id'] . '/selesai'),
        ]];
    }

    private function error(int $status, string $code, string $message): array
    {
        return compact('status', 'code', 'message') + ['ok' => false];
    }
}
