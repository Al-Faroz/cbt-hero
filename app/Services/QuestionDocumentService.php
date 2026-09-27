<?php

namespace App\Services;

use DOMDocument;
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
            $rows = $extension === 'xlsx' ? $this->xlsx($zip) : $this->docx($zip, $imageImport);
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
        $xp = new DOMXPath($this->xml($source)); $table = $xp->query('//*[local-name()="tbl"]')->item(0);
        if ($table === null) throw new RuntimeException('Tabel Word tidak ditemukan.');
        $relationships = [];
        $rels = $zip->getFromName('word/_rels/document.xml.rels');
        if ($rels !== false) {
            $relationXpath = new DOMXPath($this->xml($rels));
            foreach ($relationXpath->query('//*[local-name()="Relationship"]') as $relation)
                if ($relation->getAttribute('TargetMode') !== 'External')
                    $relationships[$relation->getAttribute('Id')] = $relation->getAttribute('Target');
        }
        $images = [];
        $rows = [];
        foreach ($xp->query('./*[local-name()="tr"]', $table) as $row) {
            $values = [];
            foreach ($xp->query('./*[local-name()="tc"]', $row) as $cell) {
                $paragraphs = [];
                foreach ($xp->query('./*[local-name()="p"]', $cell) as $paragraph) {
                    $value = '';
                    foreach ($xp->query('.//*[local-name()="t"]|.//*[local-name()="br"]|.//*[local-name()="blip"]', $paragraph) as $piece) {
                        if ($piece->localName === 'br') { $value .= "\n"; continue; }
                        if ($piece->localName === 'blip') {
                            $relationship = $piece->getAttributeNS('http://schemas.openxmlformats.org/officeDocument/2006/relationships', 'embed');
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
                $values[] = $value;
            }
            $rows[] = $values;
            if (count($rows) > 202) throw new RuntimeException('Dokumen terlalu banyak baris.');
        }
        return $rows;
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
