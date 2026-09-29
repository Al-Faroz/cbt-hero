<?php

namespace App\Services;

use Config\Database;

class AttemptBootstrapService
{
    public function bootstrap(int $participantId, int $attemptId, string $clientUuid, int $generation): array
    {
        $db = Database::connect();
        $ownership = new AttemptOwnershipService();
        $attempt = $ownership->owned($db, $participantId, $attemptId);
        if ($attempt === null) return $this->error(404, 'NOT_FOUND', 'Attempt tidak ditemukan.');
        if ($attempt['status'] !== 'ACTIVE') return $this->error(409, 'ATTEMPT_NOT_ACTIVE', 'Attempt sudah tidak aktif.');

        $clientError = $ownership->validateClient($attempt, $clientUuid, $generation);
        if ($clientError !== null)
            return $this->error($clientError['status'], $clientError['code'], $clientError['message']);

        $items = (new QuestionRuntimeItemService())->items($db, $attemptId, (int) $attempt['prepared_assignment_id']);
        $responses = $db->table('attempt_response AS ar')
            ->select('ar.prepared_assignment_item_id, ar.answer_payload, ar.client_revision, ar.server_revision, ar.is_flagged, '
                . 'pai.mapping_json, sr.question_type')
            ->join('prepared_assignment_item AS pai', 'pai.id = ar.prepared_assignment_item_id')
            ->join('soal AS s', 's.id = pai.soal_id')
            ->join('soal_revision AS sr', 'sr.soal_id = s.id AND sr.revision_no = s.current_revision_no', 'left', false)
            ->where('ar.attempt_id', $attemptId)->get()->getResultArray();
        $answers = [];
        foreach ($responses as $row) {
            $payload = $row['answer_payload'] === null
                ? null
                : json_decode((string) $row['answer_payload'], true);
            if ($row['question_type'] === 'MATCHING')
                $payload = $this->matchingPayloadForClient($payload, (string) ($row['mapping_json'] ?? ''));
            $answers[(int) $row['prepared_assignment_item_id']] = [
                'answer_payload' => $payload,
                'client_revision' => (int) $row['client_revision'],
                'server_revision' => (int) $row['server_revision'],
                'is_flagged' => (bool) $row['is_flagged'],
            ];
        }

        $media = $db->table('prepared_assignment_media AS pam')
            ->select('pam.media_asset_id, pam.is_critical, pam.prefetch_order, '
                . 'ma.storage_type, ma.media_kind, ma.provider, ma.external_url, ma.status')
            ->join('media_assets AS ma', 'ma.id = pam.media_asset_id')
            ->where('pam.prepared_assignment_id', (int) $attempt['prepared_assignment_id'])
            ->orderBy('pam.is_critical', 'DESC')->orderBy('pam.prefetch_order', 'ASC')
            ->get()->getResultArray();

        $mediaManifest = [];
        foreach ($media as $row) {
            if ($row['status'] !== 'ACTIVE') continue;
            $id = (int) $row['media_asset_id'];
            $mediaManifest[] = [
                'id' => $id,
                'kind' => (string) $row['media_kind'],
                'provider' => $row['provider'],
                'url' => $row['storage_type'] === 'LOCAL'
                    ? base_url('api/attempt/' . $attemptId . '/media/' . $id)
                    : (string) $row['external_url'],
                'critical' => (bool) $row['is_critical'],
                'prefetch_order' => (int) $row['prefetch_order'],
            ];
        }

        $db->table('attempt')->where('id', $attemptId)->update(['last_activity_at' => date('Y-m-d H:i:s')]);

        return ['ok' => true, 'status' => 200, 'data' => [
            'attempt' => [
                'id' => $attemptId,
                'status' => $attempt['status'],
                'client_generation' => (int) $attempt['client_generation'],
                'start_at' => $ownership->iso((string) $attempt['start_at']),
                'deadline_at' => $ownership->iso((string) $attempt['deadline_at']),
                'remaining_seconds' => $ownership->remainingSeconds($attempt),
                'paused' => $attempt['pause_started_at'] !== null,
                'duration_seconds' => (int) $attempt['duration_seconds_snapshot'],
                'added_seconds' => (int) $attempt['added_seconds'],
                'server_sync_revision' => (int) $attempt['server_sync_revision'],
                'last_sync_at' => $attempt['last_sync_at'] === null ? null : $ownership->iso((string) $attempt['last_sync_at']),
            ],
            'participant' => [
                'nomor_peserta' => $attempt['nomor_peserta_snapshot'],
                'username' => $attempt['username_snapshot'],
                'nama' => $attempt['nama_snapshot'],
                'rombel' => $attempt['rombel_snapshot'],
                'ruang' => $attempt['ruang_snapshot'],
            ],
            'exam' => [
                'jadwal_id' => (int) $attempt['jadwal_id'],
                'kegiatan_nama' => $attempt['kegiatan_nama'],
                'nama_ujian' => $attempt['nama_mapel'] ?: $attempt['nama_bank'],
                'tingkat' => $attempt['tingkat'] === null ? null : (int) $attempt['tingkat'],
            ],
            'package' => [
                'mode' => 'FULL',
                'items' => $items,
                'item_count' => count($items),
            ],
            'answers' => $answers,
            'media_manifest' => $mediaManifest,
            'revision_map' => array_column($items, 'revision_id', 'item_id'),
        ]];
    }

    public function media(int $participantId, int $attemptId, int $mediaId): array
    {
        $db = Database::connect();
        $ownership = new AttemptOwnershipService();
        $attempt = $ownership->owned($db, $participantId, $attemptId);
        if ($attempt === null) return $this->error(404, 'NOT_FOUND', 'Attempt tidak ditemukan.');

        $row = $db->table('prepared_assignment_media AS pam')
            ->select('ma.*')->join('media_assets AS ma', 'ma.id = pam.media_asset_id')
            ->where('pam.prepared_assignment_id', (int) $attempt['prepared_assignment_id'])
            ->where('pam.media_asset_id', $mediaId)->where('ma.status', 'ACTIVE')
            ->get()->getRowArray();
        if ($row === null) return $this->error(404, 'NOT_FOUND', 'Media tidak termasuk assignment.');

        return ['ok' => true, 'status' => 200, 'data' => $row];
    }

    private function matchingPayloadForClient(mixed $payload, string $mappingJson): mixed
    {
        if (!is_array($payload) || !is_array($payload['pairs'] ?? null)) return $payload;
        $mapping = json_decode($mappingJson, true);
        if (!is_array($mapping)) return ['pairs' => []];
        $leftAlias = is_array($mapping['left_alias'] ?? null) ? $mapping['left_alias'] : [];
        $rightAlias = is_array($mapping['right_alias'] ?? null) ? $mapping['right_alias'] : [];
        $pairs = [];
        foreach ($payload['pairs'] as $left => $right) {
            if (!is_string($left) || !isset($leftAlias[$left])) continue;
            $publicLeft = (string) $leftAlias[$left];
            $pairs[$publicLeft] = is_string($right) && isset($rightAlias[$right])
                ? (string) $rightAlias[$right]
                : null;
        }
        return ['pairs' => $pairs];
    }

    private function error(int $status, string $code, string $message): array
    {
        return compact('status', 'code', 'message') + ['ok' => false];
    }
}
