<?php

namespace App\Services;

use DOMDocument;
use DOMElement;
use DOMXPath;
use RuntimeException;
use ZipArchive;

class PesertaXlsxService
{
    private const MAX_ROWS = 5000;

    /** @return list<array{line:int,data:array<string,string>}> */
    public function parse(string $path): array
    {
        if (! extension_loaded('zip') || ! extension_loaded('dom')) {
            throw new RuntimeException('Ekstensi PHP zip dan DOM diperlukan untuk XLSX.');
        }
        $zip = new ZipArchive();
        if ($zip->open($path) !== true) {
            throw new RuntimeException('Berkas bukan XLSX yang valid.');
        }
        try {
            if ($zip->numFiles > 200) {
                throw new RuntimeException('Struktur XLSX terlalu besar.');
            }
            $total = 0;
            for ($i = 0; $i < $zip->numFiles; $i++) {
                $stat = $zip->statIndex($i);
                $total += (int) ($stat['size'] ?? 0);
                if ($total > 25 * 1024 * 1024) {
                    throw new RuntimeException('Isi XLSX melebihi batas 25 MB.');
                }
            }
            $shared = $this->sharedStrings($zip);
            $xml = $this->read($zip, 'xl/worksheets/sheet1.xml', 15 * 1024 * 1024);
            $dom = $this->xml($xml);
            $xp = new DOMXPath($dom);
            $sheetRows = $xp->query('//*[local-name()="sheetData"]/*[local-name()="row"]');
            if ($sheetRows === false || $sheetRows->length === 0) {
                throw new RuntimeException('Sheet Data Peserta kosong.');
            }
            $result = [];
            $expected = ['NISN', 'NAMA', 'JENIS KELAMIN', 'ROMBEL'];
            $head = $this->cells($sheetRows->item(0), $xp, $shared);
            foreach ($expected as $i => $label) {
                if (mb_strtoupper(trim($head[$i] ?? ''), 'UTF-8') !== $label) {
                    throw new RuntimeException('Header Sheet 1 harus NISN, Nama, Jenis Kelamin, Rombel.');
                }
            }
            for ($i = 1; $i < $sheetRows->length; $i++) {
                $node = $sheetRows->item($i);
                $cells = $this->cells($node, $xp, $shared);
                if (count(array_filter($cells, static fn ($v) => $v !== '')) === 0) {
                    continue;
                }
                if (count($result) >= self::MAX_ROWS) {
                    throw new RuntimeException('Maksimal 5.000 baris data per berkas.');
                }
                $result[] = [
                    'line' => (int) $node->getAttribute('r'),
                    'data' => [
                        'nisn' => $cells[0] ?? '', 'nama' => $cells[1] ?? '',
                        'jenis_kelamin' => $cells[2] ?? '', 'rombel' => $cells[3] ?? '',
                        'keterangan' => $cells[4] ?? '', 'username' => $cells[5] ?? '',
                        'password' => $cells[6] ?? '',
                    ],
                ];
            }
            if ($result === []) {
                throw new RuntimeException('Tidak ada baris Peserta pada Sheet 1.');
            }
            return $result;
        } finally {
            $zip->close();
        }
    }

    private function sharedStrings(ZipArchive $zip): array
    {
        if ($zip->locateName('xl/sharedStrings.xml') === false) {
            return [];
        }
        $dom = $this->xml($this->read($zip, 'xl/sharedStrings.xml', 5 * 1024 * 1024));
        $xp = new DOMXPath($dom);
        $strings = [];
        foreach ($xp->query('//*[local-name()="si"]') as $si) {
            $parts = [];
            foreach ($xp->query('.//*[local-name()="t"]', $si) as $node) {
                $parts[] = $node->textContent;
            }
            $strings[] = implode('', $parts);
            if (count($strings) > 100000) {
                throw new RuntimeException('Shared strings XLSX terlalu banyak.');
            }
        }
        return $strings;
    }

    private function cells(DOMElement $row, DOMXPath $xp, array $shared): array
    {
        $values = array_fill(0, 7, '');
        foreach ($xp->query('./*[local-name()="c"]', $row) as $cell) {
            $ref = $cell->getAttribute('r');
            if (! preg_match('/^([A-Z]+)[0-9]+$/', $ref, $matches)) {
                continue;
            }
            $col = 0;
            foreach (str_split($matches[1]) as $letter) {
                $col = $col * 26 + ord($letter) - 64;
            }
            if ($col < 1 || $col > 7) {
                continue;
            }
            if ($xp->query('./*[local-name()="f"]', $cell)->length > 0) {
                throw new RuntimeException('Rumus Excel tidak boleh dipakai dalam kolom data.');
            }
            $type = $cell->getAttribute('t');
            if ($type === 'inlineStr') {
                $parts = [];
                foreach ($xp->query('.//*[local-name()="t"]', $cell) as $text) {
                    $parts[] = $text->textContent;
                }
                $value = implode('', $parts);
            } else {
                $v = $xp->query('./*[local-name()="v"]', $cell)->item(0);
                $value = $v?->textContent ?? '';
                if ($type === 's') {
                    $value = $shared[(int) $value] ?? '';
                }
            }
            if (mb_strlen($value) > 4096) {
                throw new RuntimeException('Salah satu sel Excel terlalu panjang.');
            }
            $values[$col - 1] = trim($value);
        }
        return $values;
    }

    private function read(ZipArchive $zip, string $name, int $limit): string
    {
        $stat = $zip->statName($name);
        if ($stat === false || (int) $stat['size'] > $limit) {
            throw new RuntimeException('Struktur XLSX tidak sesuai template atau terlalu besar.');
        }
        $contents = $zip->getFromName($name);
        if ($contents === false) {
            throw new RuntimeException('Sheet XLSX tidak dapat dibaca.');
        }
        return $contents;
    }

    private function xml(string $contents): DOMDocument
    {
        if (stripos($contents, '<!DOCTYPE') !== false || stripos($contents, '<!ENTITY') !== false) {
            throw new RuntimeException('Dokumen XML tidak aman.');
        }
        $dom = new DOMDocument();
        if (! @$dom->loadXML($contents, LIBXML_NONET | LIBXML_COMPACT)) {
            throw new RuntimeException('XML di dalam XLSX tidak valid.');
        }
        return $dom;
    }
}
