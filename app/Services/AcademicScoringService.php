<?php

namespace App\Services;

class AcademicScoringService
{
    private const CLICK_TYPES = ['PG', 'PG_KOMPLEKS', 'PG_BERTINGKAT', 'MATCHING'];
    private const TYPED_TYPES = ['ISIAN_SINGKAT', 'URAIAN'];

    public function snapshot($db, array $attempt, bool $updateResponses = true): array
    {
        $attemptId = (int) ($attempt['id'] ?? 0);
        $assignmentId = (int) ($attempt['prepared_assignment_id'] ?? 0);

        $rows = $db->table('prepared_assignment_item AS pai')
            ->select(
                'pai.id AS item_id, pai.metadata_json, pai.soal_id, '
                . 's.status AS question_status, s.current_revision_no, '
                . 'sr.id AS scoring_revision_id, sr.question_type, sr.max_point, sr.scoring_mode AS revision_scoring_mode, '
                . 'sr.short_answer_mode, sr.expected_numeric, sr.numeric_tolerance, sr.change_kind, '
                . 'btc.weight_percent AS current_weight_percent, btc.scoring_mode AS bank_scoring_mode, '
                . 'ar.id AS response_id, ar.answer_payload, ar.auto_score, ar.manual_score, ar.effective_score, ar.scoring_state'
            )
            ->join('soal AS s', 's.id = pai.soal_id')
            ->join('soal_revision AS sr', 'sr.soal_id = s.id AND sr.revision_no = s.current_revision_no', 'left', false)
            ->join(
                'bank_type_config AS btc',
                'btc.bank_soal_id = s.bank_soal_id AND btc.question_type = sr.question_type',
                'left',
                false
            )
            ->join(
                'attempt_response AS ar',
                'ar.prepared_assignment_item_id = pai.id AND ar.attempt_id = ' . $attemptId,
                'left',
                false
            )
            ->where('pai.prepared_assignment_id', $assignmentId)
            ->orderBy('pai.sequence_no', 'ASC')
            ->get()
            ->getResultArray();

        $answerService = new AcademicAnswerService();
        $items = [];
        $totals = [];
        $pendingManual = false;
        $pendingTypedManual = false;

        foreach ($rows as $row) {
            if ($row['scoring_revision_id'] === null) {
                continue;
            }

            $metadata = json_decode((string) ($row['metadata_json'] ?? ''), true);
            $metadata = is_array($metadata) ? $metadata : [];
            $type = (string) $row['question_type'];
            $maxPoint = max(0.0, (float) $row['max_point']);
            $weight = $row['current_weight_percent'] !== null
                ? (float) $row['current_weight_percent']
                : (float) ($metadata['weight_percent'] ?? 0);
            $voided = (string) ($row['question_status'] ?? '') === 'VOID'
                || (string) ($row['change_kind'] ?? '') === 'VOID';

            $payload = null;
            if ($row['answer_payload'] !== null && (string) $row['answer_payload'] !== '') {
                $decoded = json_decode((string) $row['answer_payload'], true);
                if (is_array($decoded)) {
                    $payload = $decoded;
                }
            }

            $manual = $row['manual_score'] === null ? null : (float) $row['manual_score'];
            $auto = null;
            $effective = 0.0;
            $state = 'AUTO_ZERO';

            if ($voided) {
                $state = 'VOID';
            } else {
                $scoringItem = [
                    'soal_revision_id' => (int) $row['scoring_revision_id'],
                    'question_type' => $type,
                    'max_point' => $maxPoint,
                    'scoring_mode' => $row['bank_scoring_mode'] ?? $row['revision_scoring_mode'],
                    'short_answer_mode' => $row['short_answer_mode'],
                    'expected_numeric' => $row['expected_numeric'],
                    'numeric_tolerance' => $row['numeric_tolerance'],
                ];

                if ($type === 'URAIAN' && $this->essayBlank($payload)) {
                    $auto = 0.0;
                    $effective = $manual ?? 0.0;
                    $state = $manual === null ? 'AUTO_ZERO' : 'MANUAL';
                } else {
                    $validated = $answerService->validate($db, $scoringItem, $payload);
                    if (!($validated['ok'] ?? false)) {
                        $auto = null;
                        $effective = $manual ?? 0.0;
                        $state = $manual === null ? 'NEEDS_REVIEW' : 'MANUAL';
                        if ($manual === null) {
                            $pendingManual = true;
                            if (in_array($type, self::TYPED_TYPES, true)) {
                                $pendingTypedManual = true;
                            }
                        }
                    } else {
                        $auto = $validated['auto_score'] === null ? null : (float) $validated['auto_score'];
                        $effective = $manual !== null ? $manual : (float) ($auto ?? 0.0);
                        $state = $manual !== null ? 'MANUAL' : (string) $validated['scoring_state'];
                        if ($state === 'PENDING_MANUAL') {
                            $pendingManual = true;
                            if (in_array($type, self::TYPED_TYPES, true)) {
                                $pendingTypedManual = true;
                            }
                        }
                    }
                }

                $effective = max(0.0, min($maxPoint, $effective));
            }

            if ($updateResponses && $row['response_id'] !== null) {
                $db->table('attempt_response')->where('id', (int) $row['response_id'])->update([
                    'auto_score' => $auto,
                    'effective_score' => $voided ? 0 : $effective,
                    'scoring_state' => $state,
                ]);
            }

            if (!$voided) {
                if (!isset($totals[$type])) {
                    $totals[$type] = ['max' => 0.0, 'raw' => 0.0, 'weight' => max(0.0, $weight)];
                }
                $totals[$type]['max'] += $maxPoint;
                $totals[$type]['raw'] += $effective;
                $totals[$type]['weight'] = max(0.0, $weight);
            }

            $items[] = [
                'item_id' => (int) $row['item_id'],
                'question_type' => $type,
                'raw_score' => $voided ? 0.0 : $effective,
                'max_point' => $maxPoint,
                'weight_percent' => max(0.0, $weight),
                'weighted_score' => 0.0,
                'voided' => $voided ? 1 : 0,
                'scoring_state' => $state,
                'scoring_revision_id' => (int) $row['scoring_revision_id'],
            ];
        }

        $activeWeight = 0.0;
        foreach ($totals as $total) {
            if ($total['max'] > 0) {
                $activeWeight += $total['weight'];
            }
        }

        $normalizedWeights = [];
        $typeContribution = [];
        foreach ($totals as $type => $total) {
            $normalized = $activeWeight > 0 && $total['max'] > 0
                ? ($total['weight'] / $activeWeight) * 100.0
                : 0.0;
            $normalizedWeights[$type] = $normalized;
            $typeContribution[$type] = $total['max'] > 0
                ? ($total['raw'] / $total['max']) * $normalized
                : 0.0;
        }

        $clickEarned = 0.0;
        $clickMax = 0.0;
        $typedEarned = 0.0;
        $typedMax = 0.0;

        foreach ($typeContribution as $type => $contribution) {
            $weight = (float) ($normalizedWeights[$type] ?? 0);
            if (in_array($type, self::CLICK_TYPES, true)) {
                $clickEarned += $contribution;
                $clickMax += $weight;
            } elseif (in_array($type, self::TYPED_TYPES, true)) {
                $typedEarned += $contribution;
                $typedMax += $weight;
            }
        }

        foreach ($items as &$item) {
            if ($item['voided'] === 1) {
                $item['weight_percent'] = 0.0;
                $item['weighted_score'] = 0.0;
                continue;
            }
            $type = $item['question_type'];
            $denominator = (float) ($totals[$type]['max'] ?? 0);
            $normalized = (float) ($normalizedWeights[$type] ?? 0);
            $item['weight_percent'] = $normalized;
            $item['weighted_score'] = $denominator > 0
                ? ($item['raw_score'] / $denominator) * $normalized
                : 0.0;
        }
        unset($item);

        $clickScore = $clickMax > 0 ? ($clickEarned / $clickMax) * 100.0 : null;
        $typedScore = $typedMax > 0 && !$pendingTypedManual ? ($typedEarned / $typedMax) * 100.0 : null;
        $finalScore = $pendingManual ? null : array_sum($typeContribution);
        $typedState = $typedMax <= 0 ? 'NOT_APPLICABLE' : ($pendingTypedManual ? 'IN_PROCESS' : 'COMPLETE');

        return [
            'scoring_status' => $pendingManual ? 'IN_PROCESS' : 'COMPLETE',
            'click_score' => $clickScore === null ? null : round($clickScore, 2),
            'typed_score' => $typedScore === null ? null : round($typedScore, 2),
            'final_score' => $finalScore === null ? null : round($finalScore, 2),
            'items' => $items,
            'payload' => [
                'type_contribution' => $typeContribution,
                'normalized_type_weight' => $normalizedWeights,
                'pending_manual' => $pendingManual,
                'pending_typed_manual' => $pendingTypedManual,
                'click_max_contribution' => $clickMax,
                'typed_max_contribution' => $typedMax,
                'typed_score_state' => $typedState,
            ],
        ];
    }

    private function essayBlank(?array $payload): bool
    {
        if ($payload === null) {
            return true;
        }
        return trim((string) ($payload['text'] ?? '')) === '';
    }
}
