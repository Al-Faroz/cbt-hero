<?php

namespace App\Services;

class ResultSnapshotService
{
    public function create($db, array $attempt, array $snapshot, bool $final = false, ?string $finalizedAt = null): int
    {
        $attemptId = (int) $attempt['id'];
        $versionRow = $db->table('result_snapshot')
            ->selectMax('snapshot_version', 'max_version')
            ->where('attempt_id', $attemptId)
            ->get()
            ->getRowArray();
        $version = ((int) ($versionRow['max_version'] ?? 0)) + 1;
        $finalizedAt = $final ? ($finalizedAt ?? date('Y-m-d H:i:s')) : null;

        $db->table('result_snapshot')->insert([
            'attempt_id' => $attemptId,
            'jadwal_id' => (int) $attempt['jadwal_id'],
            'snapshot_version' => $version,
            'result_type' => 'ACADEMIC',
            'click_score' => $snapshot['click_score'],
            'typed_score' => $snapshot['typed_score'],
            'final_score' => $snapshot['final_score'],
            'scoring_status' => (string) $snapshot['scoring_status'],
            'payload_json' => json_encode($snapshot['payload'], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
            'is_final' => $final ? 1 : 0,
            'finalized_at' => $finalizedAt,
        ]);
        $resultId = (int) $db->insertID();

        foreach ($snapshot['items'] as $item) {
            $db->table('result_item_snapshot')->insert([
                'result_snapshot_id' => $resultId,
                'prepared_assignment_item_id' => (int) $item['item_id'],
                'question_type' => (string) $item['question_type'],
                'raw_score' => $item['raw_score'],
                'max_point' => $item['max_point'],
                'type_weight_percent' => $item['weight_percent'],
                'weighted_score' => $item['weighted_score'],
                'voided' => (int) ($item['voided'] ?? 0),
                'payload_json' => json_encode([
                    'scoring_state' => (string) ($item['scoring_state'] ?? ''),
                    'scoring_revision_id' => (int) ($item['scoring_revision_id'] ?? 0),
                ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
            ]);
        }

        $db->table('attempt')->where('id', $attemptId)->update([
            'scoring_status' => (string) $snapshot['scoring_status'],
        ]);

        $db->query(
            'INSERT INTO official_result_pointer
                (root_jadwal_id, peserta_kegiatan_id, attempt_id, result_snapshot_id)
             VALUES (?, ?, ?, ?)
             ON DUPLICATE KEY UPDATE attempt_id = VALUES(attempt_id),
                result_snapshot_id = VALUES(result_snapshot_id), updated_at = CURRENT_TIMESTAMP',
            [
                (int) $attempt['root_jadwal_id'],
                (int) $attempt['peserta_kegiatan_id'],
                $attemptId,
                $resultId,
            ]
        );

        return $resultId;
    }

    public function latest($db, int $attemptId): ?array
    {
        return $db->table('result_snapshot')
            ->where('attempt_id', $attemptId)
            ->orderBy('snapshot_version', 'DESC')
            ->get()
            ->getRowArray();
    }
}
