<?php

namespace App\Services;

class QuestionValidationService
{
    public const TYPES = ['PG', 'PG_KOMPLEKS', 'PG_BERTINGKAT', 'MATCHING', 'ISIAN_SINGKAT', 'URAIAN'];

    public function validate(array $payload): array
    {
        $type = $payload['question_type'] ?? 'PG';
        if (! is_string($type) || ! in_array($type, self::TYPES, true)) return $this->bad('Tipe soal tidak dikenal.');
        $question = $this->text($payload['question_text'] ?? null, 10000, true);
        $stimulus = $this->text($payload['stimulus_text'] ?? '', 20000, false);
        $point = $this->decimal($payload['max_point'] ?? null, 4, 1000, false);
        if ($question === null || $stimulus === null || $point === null || (float) $point <= 0)
            return $this->bad('Pertanyaan, stimulus, atau poin tidak valid.');
        $data = ['question_type' => $type, 'question_html' => $this->safe($question),
            'stimulus_html' => $stimulus === '' ? null : $this->safe($stimulus), 'max_point' => $point,
            'scoring_mode' => null, 'short_answer_mode' => null, 'expected_numeric' => null,
            'numeric_tolerance' => null, 'rubric_html' => null, 'options' => [], 'pairs' => [], 'accepted' => []];
        if (in_array($type, ['PG', 'PG_KOMPLEKS', 'PG_BERTINGKAT'], true)) {
            $raw = $payload['options'] ?? null;
            if (! is_array($raw) || count($raw) < 2 || count($raw) > 8)
                return $this->bad('Tipe pilihan membutuhkan 2–8 opsi.');
            $correctKey = $payload['correct_key'] ?? null;
            $correctCount = 0; $positive = 0;
            foreach (array_values($raw) as $i => $option) {
                if (! is_array($option)) return $this->bad('Format opsi tidak valid.');
                $text = $this->text($option['text'] ?? null, 3000, true);
                if ($text === null) return $this->bad('Setiap opsi wajib berisi teks maksimal 3000 karakter.');
                $key = chr(65 + $i); $correct = null; $value = null;
                if ($type === 'PG') {
                    $correct = $correctKey === $key ? 1 : 0;
                    $correctCount += $correct;
                } elseif ($type === 'PG_KOMPLEKS') {
                    if (! is_bool($option['correct'] ?? null)) return $this->bad('Kunci PG Kompleks harus boolean.');
                    $correct = $option['correct'] ? 1 : 0;
                    $correctCount += $correct;
                } else {
                    $value = $this->decimal($option['point_value'] ?? null, 4, $point, true);
                    if ($value === null) return $this->bad('Poin tiap opsi PG Bertingkat harus 0 hingga poin maksimal.');
                    if ((float) $value > 0) $positive++;
                }
                $data['options'][] = ['option_key' => $key, 'content_html' => $this->safe($text),
                    'is_correct' => $correct, 'point_value' => $value, 'sort_order' => $i + 1];
            }
            if ($type === 'PG' && (! is_string($correctKey)
                || ! in_array($correctKey, array_column($data['options'], 'option_key'), true)))
                return $this->bad('Pilih tepat satu kunci PG.');
            if ($type === 'PG_KOMPLEKS' && ($correctCount < 1 || $correctCount >= count($data['options'])))
                return $this->bad('PG Kompleks membutuhkan opsi benar dan opsi salah.');
            if ($type === 'PG_BERTINGKAT' && $positive < 1)
                return $this->bad('PG Bertingkat membutuhkan minimal satu opsi bernilai positif.');
        } elseif ($type === 'MATCHING') {
            $raw = $payload['pairs'] ?? null;
            $mode = $payload['scoring_mode'] ?? null;
            if (! is_array($raw) || count($raw) < 2 || count($raw) > 12
                || ! in_array($mode, ['PARTIAL', 'ALL_OR_NOTHING'], true))
                return $this->bad('Menjodohkan membutuhkan 2–12 pasangan dan mode penilaian.');
            $data['scoring_mode'] = $mode;
            $leftSeen = []; $rightSeen = [];
            foreach (array_values($raw) as $i => $pair) {
                if (! is_array($pair)) return $this->bad('Pasangan tidak valid.');
                $left = $this->text($pair['left'] ?? null, 3000, true);
                $right = $this->text($pair['right'] ?? null, 3000, true);
                if ($left === null || $right === null) return $this->bad('Kedua sisi pasangan wajib diisi.');
                $leftKey = mb_strtolower($left, 'UTF-8'); $rightKey = mb_strtolower($right, 'UTF-8');
                if (isset($leftSeen[$leftKey]) || isset($rightSeen[$rightKey]))
                    return $this->bad('Sisi kiri dan sisi kanan setiap pasangan harus unik.');
                $leftSeen[$leftKey] = true; $rightSeen[$rightKey] = true;
                $key = (string) ($i + 1);
                $data['pairs'][] = ['left_key' => $key, 'left_html' => $this->safe($left),
                    'right_key' => $key, 'right_html' => $this->safe($right), 'sort_order' => $i + 1];
            }
        } elseif ($type === 'ISIAN_SINGKAT') {
            $mode = $payload['short_answer_mode'] ?? null;
            if (! in_array($mode, ['TEXT', 'NUMERIC'], true)) return $this->bad('Pilih mode Isian TEXT atau NUMERIC.');
            $data['short_answer_mode'] = $mode;
            if ($mode === 'TEXT') {
                $raw = $payload['accepted_values'] ?? null;
                if (! is_array($raw) || count($raw) < 1 || count($raw) > 20)
                    return $this->bad('Isian TEXT membutuhkan 1–20 jawaban diterima.');
                $seen = [];
                foreach (array_values($raw) as $i => $answer) {
                    $text = $this->text($answer, 500, true);
                    if ($text === null) return $this->bad('Jawaban Isian TEXT tidak valid.');
                    $normalized = mb_strtolower(preg_replace('/\s+/u', ' ', $text), 'UTF-8');
                    if (isset($seen[$normalized])) return $this->bad('Jawaban Isian yang sama tidak boleh berulang.');
                    $seen[$normalized] = true;
                    $data['accepted'][] = ['accepted_value' => $text, 'normalized_value' => $normalized,
                        'sort_order' => $i + 1];
                }
            } else {
                $expected = $this->decimal($payload['expected_numeric'] ?? null, 8, '9999999999999999', true, true);
                $tolerance = $this->decimal($payload['numeric_tolerance'] ?? '0', 8, '9999999999999999', true);
                if ($expected === null || $tolerance === null) return $this->bad('Angka harapan atau toleransi tidak valid.');
                $data['expected_numeric'] = $expected; $data['numeric_tolerance'] = $tolerance;
            }
        } else {
            $rubric = $this->text($payload['rubric_text'] ?? '', 10000, false);
            if ($rubric === null) return $this->bad('Rubrik Uraian melebihi 10000 karakter.');
            $data['rubric_html'] = $rubric === '' ? null : $this->safe($rubric);
            $data['scoring_mode'] = 'MANUAL';
        }
        return ['ok' => true, 'data' => $data];
    }

    private function text(mixed $value, int $max, bool $required): ?string
    {
        if (! is_string($value)) return null;
        $text = trim(str_replace(["\r\n", "\r"], "\n", $value));
        return (str_contains($text, "\0") || mb_strlen($text) > $max || ($required && $text === '')) ? null : $text;
    }

    private function decimal(mixed $value, int $scale, int|string $max, bool $allowZero, bool $signed = false): ?string
    {
        if (! is_string($value) && ! is_int($value) && ! is_float($value)) return null;
        $raw = str_replace(',', '.', (string) $value);
        if (! preg_match('/^' . ($signed ? '-?' : '') . '[0-9]{1,16}(?:\.[0-9]{1,' . $scale . '})?$/D', $raw)) return null;
        $negative = str_starts_with($raw, '-');
        [$integer, $fraction] = array_pad(explode('.', ltrim($raw, '-'), 2), 2, '');
        $integer = ltrim($integer, '0') ?: '0';
        [$limit, $limitFraction] = array_pad(explode('.', (string) $max, 2), 2, '');
        if (strlen($integer) > strlen($limit) || (strlen($integer) === strlen($limit) && strcmp($integer, $limit) > 0)
            || ($integer === $limit && strcmp(str_pad($fraction, $scale, '0'), str_pad($limitFraction, $scale, '0')) > 0)
            || (!$allowZero && $integer === '0' && trim($fraction, '0') === '')) return null;
        return ($negative && ($integer !== '0' || trim($fraction, '0') !== '') ? '-' : '')
            . $integer . '.' . str_pad($fraction, $scale, '0');
    }

    private function safe(string $text): string
    {
        return str_replace("\n", '<br>', htmlspecialchars($text, ENT_QUOTES | ENT_SUBSTITUTE | ENT_HTML5, 'UTF-8'));
    }

    private function bad(string $message): array
    {
        return ['ok' => false, 'message' => $message];
    }
}
