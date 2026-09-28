<?php

namespace App\Services;

use DOMDocument;
use DOMElement;
use DOMNode;
use DOMXPath;
use RuntimeException;
use ZipArchive;

class QuestionDocumentService
{
    public const HEADERS = ['question_type', 'stimulus_text', 'question_text', 'max_point', 'options_json',
        'correct_key', 'pairs_json', 'scoring_mode', 'short_answer_mode', 'accepted_values_json',
        'expected_numeric', 'numeric_tolerance', 'rubric_text'];

    public function parse(string $path, string $extension, ?callable $imageImport = null): array
    {
        if (!extension_loaded('zip') || !extension_loaded('dom'))
            throw new RuntimeException('Ekstensi PHP zip dan DOM diperlukan.');
        $zip = new ZipArchive();
        if ($zip->open($path) !== true) throw new RuntimeException('File Office tidak valid.');
        try {
            $sum = 0;
            if ($zip->numFiles > 250) throw new RuntimeException('Dokumen terlalu kompleks.');
            for ($i = 0; $i < $zip->numFiles; $i++) {
                $entry = $zip->statIndex($i); $sum += (int) ($entry['size'] ?? 0);
                if ($sum > 30 * 1024 * 1024) throw new RuntimeException('Isi dokumen melebihi 30 MB.');
                if (str_contains((string) ($entry['name'] ?? ''), 'vbaProject'))
                    throw new RuntimeException('Dokumen bermakro tidak didukung.');
            }

            if ($extension === 'docx') {
                $docx = $this->docx($zip, $imageImport);
                if (($docx['format'] ?? '') === 'CBT-HERO-WORD-V2') return $docx['items'];
                $rows = $docx['rows'] ?? [];
            } else $rows = $this->xlsx($zip);

            if (!$rows || array_map('strtolower', array_slice($rows[0], 0, count(self::HEADERS))) !== self::HEADERS)
                throw new RuntimeException('Header dokumen tidak sesuai template Bank Soal Akademik.');
            $result = [];
            foreach (array_slice($rows, 1) as $i => $values) {
                if (!array_filter($values, fn($v) => trim($v) !== '')) continue;
                if (count($result) >= 200) throw new RuntimeException('Maksimal 200 soal per file.');
                $data = array_combine(self::HEADERS, array_pad(array_slice($values, 0, count(self::HEADERS)), count(self::HEADERS), ''));
                foreach (['options_json' => 'options', 'pairs_json' => 'pairs', 'accepted_values_json' => 'accepted_values'] as $from => $to) {
                    if ($data[$from] !== '') {
                        $decoded = json_decode($data[$from], true);
                        $data[$to] = is_array($decoded) ? $decoded : null;
                    }
                    unset($data[$from]);
                }
                $result[] = ['line' => $i + 2, 'data' => array_filter($data, fn($v) => $v !== '' && $v !== null)];
            }
            if (!$result) throw new RuntimeException('Dokumen belum berisi soal.');
            return $result;
        } finally { $zip->close(); }
    }

    private function xlsx(ZipArchive $zip): array
    {
        $shared = []; $source = $zip->getFromName('xl/sharedStrings.xml');
        if ($source !== false) {
            $xp = new DOMXPath($this->xml($source));
            foreach ($xp->query('//*[local-name()="si"]') as $node) {
                $text = '';
                foreach ($xp->query('.//*[local-name()="t"]', $node) as $part) $text .= $part->textContent;
                $shared[] = $text;
            }
        }
        $source = $zip->getFromName('xl/worksheets/sheet1.xml');
        if ($source === false) throw new RuntimeException('Sheet Soal tidak ada.');
        $xp = new DOMXPath($this->xml($source)); $rows = [];
        foreach ($xp->query('//*[local-name()="sheetData"]/*[local-name()="row"]') as $row) {
            $cells = array_fill(0, count(self::HEADERS), '');
            foreach ($xp->query('./*[local-name()="c"]', $row) as $cell) {
                if (!preg_match('/^([A-Z]+)[0-9]+$/D', $cell->getAttribute('r'), $match)) continue;
                $col = 0; foreach (str_split($match[1]) as $letter) $col = $col * 26 + ord($letter) - 64;
                if ($col < 1 || $col > count(self::HEADERS)) continue;
                if ($xp->query('./*[local-name()="f"]', $cell)->length) throw new RuntimeException('Rumus Excel tidak boleh dipakai.');
                $type = $cell->getAttribute('t'); $value = '';
                if ($type === 'inlineStr') {
                    foreach ($xp->query('.//*[local-name()="t"]', $cell) as $text) $value .= $text->textContent;
                } else {
                    $value = $xp->query('./*[local-name()="v"]', $cell)->item(0)?->textContent ?? '';
                    if ($type === 's') $value = $shared[(int) $value] ?? '';
                }
                if (mb_strlen($value) > 20000) throw new RuntimeException('Sel Excel terlalu panjang.');
                $cells[$col - 1] = trim($value);
            }
            $rows[] = $cells;
            if (count($rows) > 202) throw new RuntimeException('Dokumen terlalu banyak baris.');
        }
        return $rows;
    }

    private function docx(ZipArchive $zip, ?callable $imageImport): array
    {
        $source = $zip->getFromName('word/document.xml');
        if ($source === false) throw new RuntimeException('Tabel Word tidak ditemukan.');
        $xp = new DOMXPath($this->xml($source));
        $relationships = $this->relationships($zip);
        $images = [];

        if (str_contains($source, 'CBT-HERO-WORD-V2')) {
            return ['format' => 'CBT-HERO-WORD-V2',
                'items' => $this->humanWord($zip, $xp, $relationships, $images, $imageImport)];
        }

        $table = $xp->query('//*[local-name()="tbl"]')->item(0);
        if ($table === null) throw new RuntimeException('Tabel Word tidak ditemukan.');
        return ['format' => 'TECHNICAL', 'rows' => $this->tableValues($zip, $xp, $table, $relationships, $images, $imageImport)];
    }

    private function humanWord(ZipArchive $zip, DOMXPath $xp, array $relationships, array &$images, ?callable $imageImport): array
    {
        $result = []; $sequence = 0;
        foreach ($xp->query('//*[local-name()="body"]/*[local-name()="tbl"]') as $table) {
            $rows = $this->tableValues($zip, $xp, $table, $relationships, $images, $imageImport);
            if (!$rows) continue;

            $first = (string) ($rows[0][0] ?? '');
            if (preg_match('/<<CBT-HERO-WORD-V2\|(PG|PG_KOMPLEKS|PG_BERTINGKAT|MATCHING)\|(\d+)>>/D', $first, $m)) {
                $type = $m[1]; $number = (int) $m[2];
                $rows[0][0] = trim(str_replace($m[0], '', $first));
                $data = $this->choiceBlock($type, $rows);
                $result[] = ['line' => ++$sequence, 'source_ref' => 'Word ' . $this->label($type) . ' soal ' . $number, 'data' => $data];
                continue;
            }

            $sectionType = null;
            foreach (array_slice($rows, 1) as $row) {
                $cell = (string) ($row[0] ?? '');
                if (preg_match('/<<CBT-HERO-WORD-V2\|(ISIAN_SINGKAT|URAIAN)\|(\d+)>>/D', $cell, $m)) {
                    $sectionType = $m[1]; break;
                }
            }
            if ($sectionType === null) continue;
            foreach (array_slice($rows, 1) as $row) {
                $cell = (string) ($row[0] ?? '');
                if (!preg_match('/<<CBT-HERO-WORD-V2\|' . $sectionType . '\|(\d+)>>/D', $cell, $m)) continue;
                $number = (int) $m[1]; $row[0] = trim(str_replace($m[0], '', $cell));
                $data = $sectionType === 'ISIAN_SINGKAT' ? $this->shortAnswerRow($row) : $this->essayRow($row);
                $result[] = ['line' => ++$sequence, 'source_ref' => 'Word ' . $this->label($sectionType) . ' soal ' . $number, 'data' => $data];
            }
        }
        if (!$result) throw new RuntimeException('Template Word CBT-HERO tidak berisi blok soal yang dikenali.');
        if (count($result) > 200) throw new RuntimeException('Maksimal 200 soal per file.');
        return $result;
    }

    private function choiceBlock(string $type, array $rows): array
    {
        if ($type === 'MATCHING') {
            $pairs = []; $question = ''; $mode = null; $maxPoint = '';
            foreach (array_slice($rows, 1) as $row) {
                $label = trim((string) ($row[0] ?? ''));
                if (mb_strtolower($label) === 'cara penilaian') {
                    $modeText = mb_strtolower($this->control((string) ($row[1] ?? '')), 'UTF-8');
                    $mode = str_contains($modeText, 'semua') ? 'ALL_OR_NOTHING' : 'PARTIAL'; continue;
                }
                if (mb_strtolower($label) === 'poin maksimum') {
                    $maxPoint = $this->control((string) ($row[1] ?? '')); continue;
                }
                $left = $this->input((string) ($row[1] ?? ''));
                $right = $this->input((string) ($row[2] ?? ''));
                if ($question === '') $question = $this->input($label);
                $pairs[] = ['left' => $left, 'right' => $right];
            }
            return ['question_type' => 'MATCHING', 'question_text' => $question, 'pairs' => $pairs,
                'scoring_mode' => $mode ?? 'PARTIAL', 'max_point' => $maxPoint];
        }

        $question = $this->input((string) ($rows[1][1] ?? '')); $options = []; $correctKey = null; $maxPoint = '1';
        foreach (array_slice($rows, 2) as $row) {
            $label = trim((string) ($row[0] ?? ''));
            if (!preg_match('/^Pilihan\s+([A-L])$/iD', $label, $m)) continue;
            $letter = strtoupper($m[1]); $text = $this->input((string) ($row[1] ?? '')); $third = $this->control((string) ($row[2] ?? ''));
            if ($type === 'PG') {
                if ($this->marked($third)) $correctKey = $letter;
                $options[] = ['text' => $text];
            } elseif ($type === 'PG_KOMPLEKS') {
                $options[] = ['text' => $text, 'correct' => $this->marked($third)];
            } else {
                $options[] = ['text' => $text, 'point_value' => $third];
            }
        }
        if ($type === 'PG_BERTINGKAT') {
            $numeric = array_map(static function (array $o): float {
                $value = str_replace(',', '.', (string) $o['point_value']);
                return is_numeric($value) ? (float) $value : 0.0;
            }, $options);
            $maxPoint = $numeric ? (string) max($numeric) : '0';
        }
        $data = ['question_type' => $type, 'question_text' => $question, 'options' => $options, 'max_point' => $maxPoint];
        if ($type === 'PG') $data['correct_key'] = $correctKey;
        return $data;
    }

    private function shortAnswerRow(array $row): array
    {
        $modeText = mb_strtoupper($this->control((string) ($row[2] ?? '')), 'UTF-8');
        $mode = in_array($modeText, ['ANGKA', 'NUMERIC', 'N'], true) ? 'NUMERIC'
            : (in_array($modeText, ['TEKS', 'TEXT', 'T'], true) ? 'TEXT' : $modeText);
        $answer = $this->input((string) ($row[3] ?? ''));
        $data = ['question_type' => 'ISIAN_SINGKAT', 'question_text' => $this->input((string) ($row[1] ?? '')),
            'short_answer_mode' => $mode, 'max_point' => $this->control((string) ($row[5] ?? ''))];
        if ($mode === 'TEXT') {
            $values = array_values(array_filter(array_map('trim', preg_split('/\R/u', $answer) ?: []), static fn(string $v): bool => $v !== ''));
            $data['accepted_values'] = $values;
        } else {
            $data['expected_numeric'] = $this->control($answer);
            $tolerance = $this->control((string) ($row[4] ?? ''));
            $data['numeric_tolerance'] = $tolerance === '' ? '0' : $tolerance;
        }
        return $data;
    }

    private function essayRow(array $row): array
    {
        $maxPoint = $this->control((string) ($row[3] ?? ''));
        return ['question_type' => 'URAIAN', 'question_text' => $this->input((string) ($row[1] ?? '')),
            'rubric_text' => $this->input((string) ($row[2] ?? '')), 'max_point' => $maxPoint === '' ? '1' : $maxPoint];
    }

    private function relationships(ZipArchive $zip): array
    {
        $relationships = [];
        $rels = $zip->getFromName('word/_rels/document.xml.rels');
        if ($rels === false) return $relationships;
        $xp = new DOMXPath($this->xml($rels));
        foreach ($xp->query('//*[local-name()="Relationship"]') as $relation)
            if ($relation instanceof DOMElement && $relation->getAttribute('TargetMode') !== 'External')
                $relationships[$relation->getAttribute('Id')] = $relation->getAttribute('Target');
        return $relationships;
    }

    private function tableValues(ZipArchive $zip, DOMXPath $xp, DOMNode $table, array $relationships, array &$images, ?callable $imageImport): array
    {
        $rows = [];
        foreach ($xp->query('./*[local-name()="tr"]', $table) as $row) {
            $values = [];
            foreach ($xp->query('./*[local-name()="tc"]', $row) as $cell)
                $values[] = $this->cellValue($zip, $xp, $cell, $relationships, $images, $imageImport);
            $rows[] = $values;
        }
        return $rows;
    }

    private function cellValue(ZipArchive $zip, DOMXPath $xp, DOMNode $cell, array $relationships, array &$images, ?callable $imageImport): string
    {
        $paragraphs = [];
        foreach ($xp->query('./*[local-name()="p"]', $cell) as $paragraph) {
            $value = '';
            foreach ($xp->query('.//*[local-name()="t"]|.//*[local-name()="br"]|.//*[local-name()="blip"]', $paragraph) as $piece) {
                if ($piece->localName === 'br') { $value .= "\n"; continue; }
                if ($piece->localName === 'blip') {
                    $relationship = $piece instanceof DOMElement
                        ? $piece->getAttributeNS('http://schemas.openxmlformats.org/officeDocument/2006/relationships', 'embed') : '';
                    $target = $relationships[$relationship] ?? '';
                    if ($imageImport === null || !preg_match('~^media/[A-Za-z0-9_.-]+$~D', $target))
                        throw new RuntimeException('Gambar Word tidak memiliki referensi media yang didukung.');
                    if (!isset($images[$relationship])) {
                        $bytes = $zip->getFromName('word/' . $target);
                        if ($bytes === false) throw new RuntimeException('Gambar Word tidak ditemukan.');
                        $images[$relationship] = $imageImport($bytes);
                    }
                    $value .= '[[media:' . $images[$relationship] . ']]'; continue;
                }
                $bold = $piece->parentNode !== null && $xp->query('./*[local-name()="rPr"]/*[local-name()="b"]', $piece->parentNode)->length > 0;
                $italic = $piece->parentNode !== null && $xp->query('./*[local-name()="rPr"]/*[local-name()="i"]', $piece->parentNode)->length > 0;
                $value .= $bold ? '**' . $piece->textContent . '**' : ($italic ? '*' . $piece->textContent . '*' : $piece->textContent);
            }
            $paragraphs[] = $value;
        }
        foreach ($xp->query('./*[local-name()="tbl"]', $cell) as $nested) {
            foreach ($xp->query('./*[local-name()="tr"]', $nested) as $nestedRow) {
                $pieces = [];
                foreach ($xp->query('./*[local-name()="tc"]', $nestedRow) as $nestedCell) {
                    $parts = [];
                    foreach ($xp->query('.//*[local-name()="t"]', $nestedCell) as $text) $parts[] = $text->textContent;
                    $pieces[] = str_replace('|', ' ', implode('', $parts));
                }
                $paragraphs[] = '| ' . implode(' | ', $pieces) . ' |';
            }
        }
        $value = trim(implode("\n", $paragraphs));
        if (mb_strlen($value) > 20000) throw new RuntimeException('Sel Word terlalu panjang.');
        return $value;
    }

    private function input(string $value): string
    {
        $value = trim($value);
        return preg_match('/^\[[^\]]+\]$/uD', $value) ? '' : $value;
    }

    private function control(string $value): string
    {
        $value = $this->input($value);
        if ($value === '') return '';
        return trim(str_replace(['**', '*'], '', $value));
    }

    private function marked(string $value): bool
    {
        $value = mb_strtoupper($this->control($value), 'UTF-8');
        return in_array($value, ['✓', '✔', 'V', 'X', '1', 'BENAR', 'TRUE'], true);
    }

    private function label(string $type): string
    {
        return match ($type) {
            'PG' => 'PG', 'PG_KOMPLEKS' => 'PG Kompleks', 'PG_BERTINGKAT' => 'PG Bertingkat',
            'MATCHING' => 'Menjodohkan', 'ISIAN_SINGKAT' => 'Isian Singkat', 'URAIAN' => 'Uraian', default => $type,
        };
    }

    private function xml(string $source): DOMDocument
    {
        if (strlen($source) > 20 * 1024 * 1024 || stripos($source, '<!DOCTYPE') !== false || stripos($source, '<!ENTITY') !== false)
            throw new RuntimeException('XML dokumen terlalu besar atau tidak aman.');
        $dom = new DOMDocument();
        if (!@$dom->loadXML($source, LIBXML_NONET | LIBXML_COMPACT)) throw new RuntimeException('XML dokumen rusak.');
        return $dom;
    }
}
