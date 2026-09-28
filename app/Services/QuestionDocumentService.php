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
            } else {
                $xlsx = $this->xlsx($zip, $imageImport);
                if (($xlsx['format'] ?? '') === 'CBT-HERO-EXCEL-V2') return $xlsx['items'];
                $rows = $xlsx['rows'] ?? [];
            }

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

    private function xlsx(ZipArchive $zip, ?callable $imageImport): array
    {
        $shared = $this->xlsxSharedStrings($zip);
        $sheets = $this->xlsxSheets($zip);
        foreach ($sheets as $sheet) {
            $values = $this->xlsxSheetValues($zip, $sheet['path'], $shared, $imageImport);
            $marker = trim((string) ($values[0][0] ?? ''));
            if ($marker === 'CBT-HERO-EXCEL-V2') {
                return ['format' => 'CBT-HERO-EXCEL-V2', 'items' => $this->humanExcel($zip, $sheets, $shared, $imageImport)];
            }
        }
        return ['format' => 'TECHNICAL', 'rows' => $this->technicalXlsx($zip, $shared)];
    }

    private function humanExcel(ZipArchive $zip, array $sheets, array $shared, ?callable $imageImport): array
    {
        $result = []; $sequence = 0;
        foreach ($sheets as $sheet) {
            $values = $this->xlsxSheetValues($zip, $sheet['path'], $shared, $imageImport);
            $marker = trim((string) ($values[0][0] ?? ''));
            if (!preg_match('/^CBT-HERO-EXCEL-V2\|(PG|PG_KOMPLEKS|PG_BERTINGKAT|MATCHING|ISIAN_SINGKAT|URAIAN)\|(\d+)\|(.*)$/D', $marker, $match))
                continue;

            $type = $match[1];
            $optionCount = (int) $match[2];
            $mode = trim((string) $match[3]);
            foreach (array_slice($values, 5, null, true) as $zeroRow => $row) {
                $excelRow = $zeroRow + 1;
                $data = $this->excelQuestionRow($type, $row, $optionCount, $mode);
                if ($data === null) continue;
                if (++$sequence > 200) throw new RuntimeException('Maksimal 200 soal per file.');
                $result[] = [
                    'line' => $sequence,
                    'source_ref' => 'Excel ' . $this->label($type) . ' baris ' . $excelRow,
                    'data' => $data,
                ];
            }
        }
        if (!$result) throw new RuntimeException('Template Excel CBT-HERO belum berisi soal.');
        return $result;
    }

    private function excelQuestionRow(string $type, array $row, int $optionCount, string $mode): ?array
    {
        $value = static fn(array $source, int $index): string => trim((string) ($source[$index] ?? ''));

        if ($type === 'PG') {
            $question = $this->input($value($row, 1)); $options = [];
            $meaningful = $question !== '';
            for ($i = 0; $i < $optionCount; $i++) {
                $text = $this->input($value($row, 2 + $i)); $options[] = ['text' => $text];
                $meaningful = $meaningful || $text !== '';
            }
            $key = mb_strtoupper($this->control($value($row, 2 + $optionCount)), 'UTF-8');
            $meaningful = $meaningful || $key !== '';
            if (!$meaningful) return null;
            return ['question_type' => 'PG', 'question_text' => $question, 'options' => $options,
                'correct_key' => $key, 'max_point' => '1'];
        }

        if ($type === 'PG_KOMPLEKS') {
            $question = $this->input($value($row, 1)); $options = [];
            $meaningful = $question !== '';
            for ($i = 0; $i < $optionCount; $i++) {
                $text = $this->input($value($row, 2 + $i)); $options[] = ['text' => $text, 'correct' => false];
                $meaningful = $meaningful || $text !== '';
            }
            $letters = $this->excelLetters($this->control($value($row, 2 + $optionCount)), $optionCount);
            foreach ($letters as $letter) $options[ord($letter) - 65]['correct'] = true;
            $meaningful = $meaningful || $letters !== [];
            if (!$meaningful) return null;
            return ['question_type' => 'PG_KOMPLEKS', 'question_text' => $question, 'options' => $options,
                'max_point' => '1'];
        }

        if ($type === 'PG_BERTINGKAT') {
            $question = $this->input($value($row, 1)); $options = []; $points = [];
            $meaningful = $question !== '';
            for ($i = 0; $i < $optionCount; $i++) {
                $text = $this->input($value($row, 2 + ($i * 2)));
                $point = $this->control($value($row, 3 + ($i * 2)));
                $options[] = ['text' => $text, 'point_value' => $point];
                $numeric = str_replace(',', '.', $point);
                if (is_numeric($numeric)) $points[] = (float) $numeric;
                $meaningful = $meaningful || $text !== '' || $point !== '';
            }
            if (!$meaningful) return null;
            return ['question_type' => 'PG_BERTINGKAT', 'question_text' => $question, 'options' => $options,
                'max_point' => $points ? (string) max($points) : '0'];
        }

        if ($type === 'MATCHING') {
            $question = $this->input($value($row, 1)); $pairs = [];
            $meaningful = $question !== '';
            for ($i = 0; $i < $optionCount; $i++) {
                $left = $this->input($value($row, 2 + ($i * 2)));
                $right = $this->input($value($row, 3 + ($i * 2)));
                $pairs[] = ['left' => $left, 'right' => $right];
                $meaningful = $meaningful || $left !== '' || $right !== '';
            }
            $maxPoint = $this->control($value($row, 2 + ($optionCount * 2)));
            $meaningful = $meaningful || $maxPoint !== '';
            if (!$meaningful) return null;
            return ['question_type' => 'MATCHING', 'question_text' => $question, 'pairs' => $pairs,
                'scoring_mode' => $mode === 'ALL_OR_NOTHING' ? 'ALL_OR_NOTHING' : 'PARTIAL',
                'max_point' => $maxPoint];
        }

        if ($type === 'ISIAN_SINGKAT') {
            $question = $this->input($value($row, 1));
            $answer = $this->input($value($row, 3));
            $tolerance = $this->control($value($row, 4));
            $meaningful = $question !== '' || $answer !== '' || $tolerance !== '';
            if (!$meaningful) return null;
            $modeText = mb_strtoupper($this->control($value($row, 2)), 'UTF-8');
            $shortMode = in_array($modeText, ['ANGKA', 'NUMERIC', 'N'], true) ? 'NUMERIC' : 'TEXT';
            $maxPoint = $this->control($value($row, 5));
            $data = ['question_type' => 'ISIAN_SINGKAT', 'question_text' => $question,
                'short_answer_mode' => $shortMode, 'max_point' => $maxPoint === '' ? '1' : $maxPoint];
            if ($shortMode === 'TEXT') {
                $data['accepted_values'] = array_values(array_filter(
                    array_map('trim', preg_split('/\R/u', $answer) ?: []),
                    static fn(string $item): bool => $item !== ''
                ));
            } else {
                $data['expected_numeric'] = $this->control($answer);
                $data['numeric_tolerance'] = $tolerance === '' ? '0' : $tolerance;
            }
            return $data;
        }

        $question = $this->input($value($row, 1));
        $rubric = $this->input($value($row, 2));
        if ($question === '' && $rubric === '') return null;
        $maxPoint = $this->control($value($row, 3));
        return ['question_type' => 'URAIAN', 'question_text' => $question, 'rubric_text' => $rubric,
            'max_point' => $maxPoint === '' ? '1' : $maxPoint];
    }

    private function excelLetters(string $value, int $optionCount): array
    {
        if ($value === '') return [];
        $tokens = preg_split('/[^A-Za-z]+/u', mb_strtoupper($value, 'UTF-8')) ?: [];
        $result = [];
        foreach ($tokens as $token) {
            if (strlen($token) !== 1) continue;
            $index = ord($token) - 65;
            if ($index >= 0 && $index < $optionCount) $result[$token] = $token;
        }
        return array_values($result);
    }

    private function technicalXlsx(ZipArchive $zip, array $shared): array
    {
        $source = $zip->getFromName('xl/worksheets/sheet1.xml');
        if ($source === false) throw new RuntimeException('Sheet Soal tidak ada.');
        $xp = new DOMXPath($this->xml($source)); $rows = [];
        foreach ($xp->query('//*[local-name()="sheetData"]/*[local-name()="row"]') as $row) {
            $cells = array_fill(0, count(self::HEADERS), '');
            foreach ($xp->query('./*[local-name()="c"]', $row) as $cell) {
                if (!$cell instanceof DOMElement || !preg_match('/^([A-Z]+)[0-9]+$/D', $cell->getAttribute('r'), $match)) continue;
                $col = $this->excelColumnIndex($match[1]);
                if ($col < 1 || $col > count(self::HEADERS)) continue;
                if ($xp->query('./*[local-name()="f"]', $cell)->length) throw new RuntimeException('Rumus Excel tidak boleh dipakai.');
                $cells[$col - 1] = trim($this->xlsxCellValue($xp, $cell, $shared));
            }
            $rows[] = $cells;
            if (count($rows) > 202) throw new RuntimeException('Dokumen terlalu banyak baris.');
        }
        return $rows;
    }

    private function xlsxSharedStrings(ZipArchive $zip): array
    {
        $shared = []; $source = $zip->getFromName('xl/sharedStrings.xml');
        if ($source === false) return $shared;
        $xp = new DOMXPath($this->xml($source));
        foreach ($xp->query('//*[local-name()="si"]') as $node) {
            $text = '';
            foreach ($xp->query('.//*[local-name()="t"]', $node) as $part) $text .= $part->textContent;
            $shared[] = $text;
        }
        return $shared;
    }

    private function xlsxSheets(ZipArchive $zip): array
    {
        $source = $zip->getFromName('xl/workbook.xml');
        if ($source === false) throw new RuntimeException('Workbook Excel tidak ditemukan.');
        $xp = new DOMXPath($this->xml($source));
        $rels = $this->officeRelationships($zip, 'xl/workbook.xml');
        $result = [];
        foreach ($xp->query('//*[local-name()="sheets"]/*[local-name()="sheet"]') as $sheet) {
            if (!$sheet instanceof DOMElement) continue;
            $id = $sheet->getAttributeNS('http://schemas.openxmlformats.org/officeDocument/2006/relationships', 'id');
            $path = $rels[$id]['target'] ?? '';
            if ($path === '' || !str_starts_with($path, 'xl/worksheets/')) continue;
            $result[] = ['name' => $sheet->getAttribute('name'), 'path' => $path];
        }
        if (!$result) throw new RuntimeException('Sheet Excel tidak ditemukan.');
        return $result;
    }

    private function xlsxSheetValues(ZipArchive $zip, string $path, array $shared, ?callable $imageImport): array
    {
        $source = $zip->getFromName($path);
        if ($source === false) throw new RuntimeException('Sheet Excel tidak dapat dibaca.');
        $xp = new DOMXPath($this->xml($source)); $values = [];

        foreach ($xp->query('//*[local-name()="sheetData"]/*[local-name()="row"]/*[local-name()="c"]') as $cell) {
            if (!$cell instanceof DOMElement || !preg_match('/^([A-Z]+)([0-9]+)$/D', $cell->getAttribute('r'), $match)) continue;
            if ($xp->query('./*[local-name()="f"]', $cell)->length)
                throw new RuntimeException('Rumus/formula Excel tidak boleh dipakai pada template soal.');
            $row = (int) $match[2] - 1; $col = $this->excelColumnIndex($match[1]) - 1;
            if ($row < 0 || $row > 260 || $col < 0 || $col > 60) continue;
            $text = trim($this->xlsxCellValue($xp, $cell, $shared));
            if (mb_strlen($text) > 20000) throw new RuntimeException('Sel Excel terlalu panjang.');
            $values[$row][$col] = $text;
        }

        foreach ($this->xlsxDrawingTokens($zip, $path, $source, $imageImport) as $item) {
            $row = $item['row']; $col = $item['col'];
            $current = trim((string) ($values[$row][$col] ?? ''));
            $values[$row][$col] = $current === '' ? $item['token'] : $current . "\n" . $item['token'];
        }

        $maxRow = $values ? max(array_keys($values)) : 0;
        $maxCol = 0;
        foreach ($values as $row) if ($row) $maxCol = max($maxCol, max(array_keys($row)));
        $matrix = [];
        for ($r = 0; $r <= $maxRow; $r++) {
            $matrix[$r] = [];
            for ($c = 0; $c <= $maxCol; $c++) $matrix[$r][$c] = (string) ($values[$r][$c] ?? '');
        }
        return $matrix;
    }

    private function xlsxCellValue(DOMXPath $xp, DOMElement $cell, array $shared): string
    {
        $type = $cell->getAttribute('t');
        if ($type === 'inlineStr') {
            $value = '';
            foreach ($xp->query('.//*[local-name()="is"]//*[local-name()="t"]', $cell) as $text) $value .= $text->textContent;
            return $value;
        }
        $value = $xp->query('./*[local-name()="v"]', $cell)->item(0)?->textContent ?? '';
        if ($type === 's') return $shared[(int) $value] ?? '';
        if ($type === 'b') return $value === '1' ? 'TRUE' : 'FALSE';
        return $value;
    }

    private function xlsxDrawingTokens(ZipArchive $zip, string $sheetPath, string $sheetXml, ?callable $imageImport): array
    {
        $xp = new DOMXPath($this->xml($sheetXml));
        $rels = $this->officeRelationships($zip, $sheetPath);
        $result = []; $cache = [];
        foreach ($xp->query('//*[local-name()="drawing"]') as $drawing) {
            if (!$drawing instanceof DOMElement) continue;
            $id = $drawing->getAttributeNS('http://schemas.openxmlformats.org/officeDocument/2006/relationships', 'id');
            $drawingPath = $rels[$id]['target'] ?? '';
            if ($drawingPath === '') continue;
            $drawingXml = $zip->getFromName($drawingPath);
            if ($drawingXml === false) continue;
            $drawingXp = new DOMXPath($this->xml($drawingXml));
            $drawingRels = $this->officeRelationships($zip, $drawingPath);
            foreach ($drawingXp->query('//*[local-name()="oneCellAnchor" or local-name()="twoCellAnchor"]') as $anchor) {
                $from = $drawingXp->query('./*[local-name()="from"]', $anchor)->item(0);
                if ($from === null) continue;
                $colNode = $drawingXp->query('./*[local-name()="col"]', $from)->item(0);
                $rowNode = $drawingXp->query('./*[local-name()="row"]', $from)->item(0);
                $blip = $drawingXp->query('.//*[local-name()="blip"]', $anchor)->item(0);
                if (!$blip instanceof DOMElement || $colNode === null || $rowNode === null) continue;
                $relId = $blip->getAttributeNS('http://schemas.openxmlformats.org/officeDocument/2006/relationships', 'embed');
                $mediaPath = $drawingRels[$relId]['target'] ?? '';
                if ($mediaPath === '' || !str_starts_with($mediaPath, 'xl/media/')) continue;
                if ($imageImport === null) throw new RuntimeException('Gambar Excel tidak dapat diproses.');

                if (!isset($cache[$mediaPath])) {
                    $bytes = $zip->getFromName($mediaPath);
                    if ($bytes === false) throw new RuntimeException('Gambar Excel tidak ditemukan.');
                    $cache[$mediaPath] = $imageImport($bytes);
                }
                $result[] = [
                    'row' => (int) $rowNode->textContent,
                    'col' => (int) $colNode->textContent,
                    'token' => '[[media:' . $cache[$mediaPath] . ']]',
                ];
            }
        }
        return $result;
    }

    private function officeRelationships(ZipArchive $zip, string $partPath): array
    {
        $dir = dirname($partPath);
        $relsPath = ($dir === '.' ? '' : $dir . '/') . '_rels/' . basename($partPath) . '.rels';
        $source = $zip->getFromName($relsPath);
        if ($source === false) return [];
        $xp = new DOMXPath($this->xml($source)); $result = [];
        foreach ($xp->query('//*[local-name()="Relationship"]') as $relation) {
            if (!$relation instanceof DOMElement || $relation->getAttribute('TargetMode') === 'External') continue;
            $id = $relation->getAttribute('Id');
            $target = $this->resolveOfficePath($partPath, $relation->getAttribute('Target'));
            if ($id !== '' && $target !== '') $result[$id] = [
                'target' => $target,
                'type' => $relation->getAttribute('Type'),
            ];
        }
        return $result;
    }

    private function resolveOfficePath(string $base, string $target): string
    {
        $target = str_replace('\\', '/', trim($target));
        if ($target === '' || str_contains($target, "\0")) return '';
        if (str_starts_with($target, '/')) $combined = ltrim($target, '/');
        else $combined = dirname($base) . '/' . $target;
        $parts = [];
        foreach (explode('/', $combined) as $part) {
            if ($part === '' || $part === '.') continue;
            if ($part === '..') {
                if (!$parts) return '';
                array_pop($parts); continue;
            }
            $parts[] = $part;
        }
        $resolved = implode('/', $parts);
        return str_starts_with($resolved, 'xl/') ? $resolved : '';
    }

    private function excelColumnIndex(string $letters): int
    {
        $index = 0;
        foreach (str_split($letters) as $letter) $index = ($index * 26) + ord($letter) - 64;
        return $index;
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
            $parts = './/*[local-name()="oMath" and not(ancestor::*[local-name()="oMath"])]'
                . '|.//*[local-name()="t" and not(ancestor::*[local-name()="oMath"])]'
                . '|.//*[local-name()="br" and not(ancestor::*[local-name()="oMath"])]'
                . '|.//*[local-name()="blip"]';
            foreach ($xp->query($parts, $paragraph) as $piece) {
                if ($piece->localName === 'br') { $value .= "\n"; continue; }
                if ($piece->localName === 'oMath') {
                    $latex = trim($this->ommlToLatex($xp, $piece));
                    if ($latex !== '') $value .= '$' . $latex . '$';
                    continue;
                }
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

    private function ommlToLatex(DOMXPath $xp, DOMNode $node): string
    {
        if ($node->nodeType === XML_TEXT_NODE) return $node->nodeValue ?? '';
        if (! $node instanceof DOMElement) return '';
        $name = $node->localName;

        $children = function (DOMNode $parent) use ($xp): string {
            $result = '';
            foreach ($parent->childNodes as $child) {
                if ($child instanceof DOMElement && preg_match('/Pr$/D', $child->localName)) continue;
                $result .= $this->ommlToLatex($xp, $child);
            }
            return $result;
        };
        $child = function (string $local) use ($xp, $node): ?DOMNode {
            return $xp->query('./*[local-name()="' . $local . '"]', $node)->item(0);
        };
        $value = function (?DOMNode $item) use ($children): string {
            return $item === null ? '' : trim($children($item));
        };

        if ($name === 't') return $this->mathText($node->textContent);
        if (in_array($name, ['oMath', 'e', 'num', 'den', 'sup', 'sub', 'fName', 'lim', 'box', 'borderBox'], true))
            return $children($node);
        if ($name === 'r') {
            $text = '';
            foreach ($xp->query('.//*[local-name()="t"]', $node) as $part) $text .= $this->mathText($part->textContent);
            return $text;
        }
        if ($name === 'f') return '\\frac{' . $value($child('num')) . '}{' . $value($child('den')) . '}';
        if ($name === 'sSup') return '{' . $value($child('e')) . '}^{' . $value($child('sup')) . '}';
        if ($name === 'sSub') return '{' . $value($child('e')) . '}_{' . $value($child('sub')) . '}';
        if ($name === 'sSubSup') return '{' . $value($child('e')) . '}_{' . $value($child('sub'))
            . '}^{' . $value($child('sup')) . '}';
        if ($name === 'rad') {
            $base = $value($child('e')); $degree = $value($child('deg'));
            return $degree === '' ? '\\sqrt{' . $base . '}' : '\\sqrt[' . $degree . ']{' . $base . '}';
        }
        if ($name === 'nary') {
            $symbol = '\\sum';
            $chr = $xp->query('./*[local-name()="naryPr"]/*[local-name()="chr"]', $node)->item(0);
            if ($chr instanceof DOMElement) {
                $raw = $chr->getAttributeNS('http://schemas.openxmlformats.org/officeDocument/2006/math', 'val')
                    ?: $chr->getAttribute('m:val') ?: $chr->getAttribute('val');
                $symbol = match ($raw) {
                    '∫' => '\\int', '∬' => '\\iint', '∭' => '\\iiint', '∏' => '\\prod', '∐' => '\\coprod',
                    '∑', '' => '\\sum', default => $this->mathText($raw),
                };
            }
            $sub = $value($child('sub')); $sup = $value($child('sup')); $body = $value($child('e'));
            return $symbol . ($sub !== '' ? '_{' . $sub . '}' : '') . ($sup !== '' ? '^{' . $sup . '}' : '')
                . ($body !== '' ? ' ' . $body : '');
        }
        if ($name === 'd') {
            $begin = '('; $end = ')';
            $beginNode = $xp->query('./*[local-name()="dPr"]/*[local-name()="begChr"]', $node)->item(0);
            if ($beginNode instanceof DOMElement) $begin = $beginNode->getAttributeNS(
                'http://schemas.openxmlformats.org/officeDocument/2006/math', 'val')
                ?: $beginNode->getAttribute('m:val') ?: $beginNode->getAttribute('val') ?: $begin;
            $endNode = $xp->query('./*[local-name()="dPr"]/*[local-name()="endChr"]', $node)->item(0);
            if ($endNode instanceof DOMElement) $end = $endNode->getAttributeNS(
                'http://schemas.openxmlformats.org/officeDocument/2006/math', 'val')
                ?: $endNode->getAttribute('m:val') ?: $endNode->getAttribute('val') ?: $end;
            $expressions = [];
            foreach ($xp->query('./*[local-name()="e"]', $node) as $expression) $expressions[] = $value($expression);
            return '\\left' . $this->mathDelimiter($begin) . implode(',', $expressions)
                . '\\right' . $this->mathDelimiter($end);
        }
        if ($name === 'm') {
            $rows = [];
            foreach ($xp->query('./*[local-name()="mr"]', $node) as $row) {
                $cells = [];
                foreach ($xp->query('./*[local-name()="e"]', $row) as $cellNode) $cells[] = $value($cellNode);
                $rows[] = implode(' & ', $cells);
            }
            return '\\begin{bmatrix}' . implode(' \\\\ ', $rows) . '\\end{bmatrix}';
        }
        if ($name === 'eqArr') {
            $rows = [];
            foreach ($xp->query('./*[local-name()="e"]', $node) as $row) $rows[] = $value($row);
            return '\\begin{aligned}' . implode(' \\\\ ', $rows) . '\\end{aligned}';
        }
        if ($name === 'func') return $value($child('fName')) . '\\left(' . $value($child('e')) . '\\right)';
        if ($name === 'limLow') return $value($child('e')) . '_{' . $value($child('lim')) . '}';
        if ($name === 'limUpp') return $value($child('e')) . '^{' . $value($child('lim')) . '}';
        if ($name === 'bar') return '\\overline{' . $value($child('e')) . '}';
        if ($name === 'acc') {
            $accent = $xp->query('./*[local-name()="accPr"]/*[local-name()="chr"]', $node)->item(0);
            $raw = $accent instanceof DOMElement ? ($accent->getAttributeNS(
                'http://schemas.openxmlformats.org/officeDocument/2006/math', 'val')
                ?: $accent->getAttribute('m:val') ?: $accent->getAttribute('val')) : '';
            $command = match ($raw) {'ˆ', '^' => '\\hat', '¯', '̅' => '\\bar', '→' => '\\vec', default => '\\hat'};
            return $command . '{' . $value($child('e')) . '}';
        }
        return $children($node);
    }

    private function mathText(string $text): string
    {
        return strtr($text, [
            '×' => '\\times ', '÷' => '\\div ', '≤' => '\\le ', '≥' => '\\ge ', '≠' => '\\ne ',
            '≈' => '\\approx ', '∞' => '\\infty ', '±' => '\\pm ', '∓' => '\\mp ', '→' => '\\to ',
            '∈' => '\\in ', '∉' => '\\notin ', '∪' => '\\cup ', '∩' => '\\cap ',
        ]);
    }

    private function mathDelimiter(string $value): string
    {
        return match ($value) {
            '{' => '\\{', '}' => '\\}', '[' => '[', ']' => ']', '|' => '|',
            '⌈' => '\\lceil', '⌉' => '\\rceil', '⌊' => '\\lfloor', '⌋' => '\\rfloor',
            default => $value === '' ? '.' : $value,
        };
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
