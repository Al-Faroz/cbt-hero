<?php

namespace App\Services;

class AcademicAnswerService
{
    public function validate($db, array $item, mixed $payload): array
    {
        $type = (string) $item['question_type'];
        if ($payload === null) return ['ok' => true, 'payload' => null, 'auto_score' => 0.0, 'scoring_state' => $type === 'URAIAN' ? 'PENDING_MANUAL' : 'AUTO'];

        if (!is_array($payload))
            return $this->error('Jawaban harus berupa objek.');

        return match ($type) {
            'PG' => $this->singleChoice($db, $item, $payload, false),
            'PG_BERTINGKAT' => $this->singleChoice($db, $item, $payload, true),
            'PG_KOMPLEKS' => $this->complexChoice($db, $item, $payload),
            'MATCHING' => $this->matching($db, $item, $payload),
            'ISIAN_SINGKAT' => $this->shortAnswer($db, $item, $payload),
            'URAIAN' => $this->essay($item, $payload),
            default => $this->error('Tipe soal belum didukung.'),
        };
    }

    private function singleChoice($db, array $item, array $payload, bool $tiered): array
    {
        if (array_diff(array_keys($payload), ['selected']) !== [])
            return $this->error('Format jawaban pilihan tidak valid.');
        $selected = $payload['selected'] ?? null;
        if ($selected === null || $selected === '') return ['ok' => true, 'payload' => ['selected' => null], 'auto_score' => 0.0, 'scoring_state' => 'AUTO'];
        if (!is_string($selected) || mb_strlen($selected) > 64) return $this->error('Pilihan jawaban tidak valid.');

        $option = $db->table('soal_opsi')->select('option_key, is_correct, point_value')
            ->where('soal_revision_id', (int) $item['soal_revision_id'])
            ->where('option_key', $selected)->get()->getRowArray();
        if ($option === null) return $this->error('Pilihan jawaban tidak tersedia.');

        $score = $tiered
            ? (float) ($option['point_value'] ?? 0)
            : ((int) $option['is_correct'] === 1 ? (float) $item['max_point'] : 0.0);
        $score = max(0.0, min((float) $item['max_point'], $score));
        return ['ok' => true, 'payload' => ['selected' => $selected], 'auto_score' => $score, 'scoring_state' => 'AUTO'];
    }

    private function complexChoice($db, array $item, array $payload): array
    {
        if (array_diff(array_keys($payload), ['selected']) !== [] || !is_array($payload['selected'] ?? null))
            return $this->error('Format PG Kompleks tidak valid.');
        $selected = array_values(array_unique(array_filter(array_map(
            static fn($v): string => is_string($v) ? trim($v) : '',
            $payload['selected']
        ), static fn(string $v): bool => $v !== '')));
        if (count($selected) > 20) return $this->error('Pilihan terlalu banyak.');

        $options = $db->table('soal_opsi')->select('option_key, is_correct')
            ->where('soal_revision_id', (int) $item['soal_revision_id'])->get()->getResultArray();
        $known = array_column($options, null, 'option_key');
        foreach ($selected as $key) if (!isset($known[$key])) return $this->error('Pilihan jawaban tidak tersedia.');

        $correct = array_values(array_map(
            static fn(array $row): string => (string) $row['option_key'],
            array_filter($options, static fn(array $row): bool => (int) $row['is_correct'] === 1)
        ));
        $correctSelected = count(array_intersect($selected, $correct));
        $wrongSelected = count(array_diff($selected, $correct));
        $mode = (string) ($item['scoring_mode'] ?? 'PARTIAL');
        if ($mode === 'ALL_OR_NOTHING') {
            $ratio = count($selected) === count($correct)
                && count(array_diff($selected, $correct)) === 0
                && count(array_diff($correct, $selected)) === 0
                ? 1.0 : 0.0;
        } else {
            $ratio = count($correct) > 0
                ? max(0.0, ($correctSelected - $wrongSelected) / count($correct))
                : 0.0;
        }
        $score = $ratio * (float) $item['max_point'];

        sort($selected);
        return ['ok' => true, 'payload' => ['selected' => $selected], 'auto_score' => $score, 'scoring_state' => 'AUTO'];
    }

    private function matching($db, array $item, array $payload): array
    {
        if (array_diff(array_keys($payload), ['pairs']) !== [] || !is_array($payload['pairs'] ?? null))
            return $this->error('Format jawaban Menjodohkan tidak valid.');

        $pairs = $db->table('soal_matching_pair')->select('left_key, right_key')
            ->where('soal_revision_id', (int) $item['soal_revision_id'])->get()->getResultArray();
        $correct = []; $rightKeys = [];
        foreach ($pairs as $row) {
            $correct[(string) $row['left_key']] = (string) $row['right_key'];
            $rightKeys[(string) $row['right_key']] = true;
        }

        $answer = [];
        foreach ($payload['pairs'] as $left => $right) {
            if (!is_string($left) || !array_key_exists($left, $correct)) return $this->error('Sisi kiri Menjodohkan tidak valid.');
            if ($right === null || $right === '') { $answer[$left] = null; continue; }
            if (!is_string($right) || !isset($rightKeys[$right])) return $this->error('Pasangan kanan tidak valid.');
            $answer[$left] = $right;
        }

        $usedRight = array_values(array_filter($answer, static fn($value): bool => is_string($value) && $value !== ''));
        if (count($usedRight) !== count(array_unique($usedRight)))
            return $this->error('Satu pilihan kanan hanya boleh dipakai satu kali.');

        $correctCount = 0;
        foreach ($correct as $left => $right) if (($answer[$left] ?? null) === $right) $correctCount++;
        $mode = (string) ($item['scoring_mode'] ?? 'PARTIAL');
        $ratio = $mode === 'ALL_OR_NOTHING'
            ? ($correctCount === count($correct) ? 1.0 : 0.0)
            : (count($correct) > 0 ? $correctCount / count($correct) : 0.0);
        return ['ok' => true, 'payload' => ['pairs' => $answer], 'auto_score' => $ratio * (float) $item['max_point'], 'scoring_state' => 'AUTO'];
    }

    private function shortAnswer($db, array $item, array $payload): array
    {
        if (array_diff(array_keys($payload), ['value']) !== [])
            return $this->error('Format Isian Singkat tidak valid.');
        $value = $payload['value'] ?? '';
        if (!is_string($value) && !is_numeric($value)) return $this->error('Jawaban Isian Singkat tidak valid.');
        $value = trim((string) $value);
        if (mb_strlen($value) > 2000) return $this->error('Jawaban Isian Singkat terlalu panjang.');

        $correct = false;
        if ((string) $item['short_answer_mode'] === 'NUMERIC') {
            $normalized = str_replace(',', '.', preg_replace('/\s+/u', '', $value) ?? '');
            if (is_numeric($normalized)) {
                $expected = (float) $item['expected_numeric'];
                $tolerance = max(0.0, (float) ($item['numeric_tolerance'] ?? 0));
                $correct = abs((float) $normalized - $expected) <= $tolerance;
            }
        } else {
            $normalized = $this->normalizeText($value);
            $rows = $db->table('soal_short_answer_text')->select('normalized_value, accepted_value')
                ->where('soal_revision_id', (int) $item['soal_revision_id'])->get()->getResultArray();
            foreach ($rows as $row) {
                $candidate = trim((string) ($row['normalized_value'] ?: $row['accepted_value']));
                if ($normalized === $this->normalizeText($candidate)) { $correct = true; break; }
            }
        }

        return ['ok' => true, 'payload' => ['value' => $value], 'auto_score' => $correct ? (float) $item['max_point'] : 0.0, 'scoring_state' => 'AUTO'];
    }

    private function essay(array $item, array $payload): array
    {
        if (array_diff(array_keys($payload), ['text']) !== [])
            return $this->error('Format jawaban Uraian tidak valid.');
        $text = $payload['text'] ?? '';
        if (!is_string($text) || mb_strlen($text) > 30000) return $this->error('Jawaban Uraian terlalu panjang.');
        return ['ok' => true, 'payload' => ['text' => $text], 'auto_score' => null, 'scoring_state' => 'PENDING_MANUAL'];
    }

    private function normalizeText(string $value): string
    {
        $value = mb_strtolower(trim($value), 'UTF-8');
        return preg_replace('/\s+/u', ' ', $value) ?? $value;
    }

    private function error(string $message): array
    {
        return ['ok' => false, 'message' => $message];
    }
}
