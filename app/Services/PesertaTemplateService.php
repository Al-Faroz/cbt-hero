<?php

namespace App\Services;

use Config\Database;
use RuntimeException;
use ZipArchive;

class PesertaTemplateService
{
    public function build(): string
    {
        if (! extension_loaded('zip')) {
            throw new RuntimeException('Ekstensi PHP zip diperlukan untuk template.');
        }
        $source = new ZipArchive();
        if ($source->open(ROOTPATH . 'assets/templates/peserta.xlsx') !== true) {
            throw new RuntimeException('Template Peserta tidak tersedia.');
        }
        $path = tempnam(WRITEPATH . 'cache/', 'peserta_tpl_');
        if ($path === false) {
            $source->close();
            throw new RuntimeException('Folder cache tidak dapat ditulis.');
        }
        $target = new ZipArchive();
        if ($target->open($path, ZipArchive::CREATE | ZipArchive::OVERWRITE) !== true) {
            $source->close();
            @unlink($path);
            throw new RuntimeException('Template tidak dapat dibuat.');
        }
        try {
            for ($i = 0; $i < $source->numFiles; $i++) {
                $name = $source->getNameIndex($i);
                if ($name === 'xl/worksheets/sheet2.xml') {
                    $target->addFromString($name, $this->guideSheet());
                } else {
                    $target->addFromString($name, $source->getFromIndex($i));
                }
            }
        } finally {
            $source->close();
            $target->close();
        }
        $bytes = file_get_contents($path);
        @unlink($path);
        if ($bytes === false) {
            throw new RuntimeException('Template tidak dapat dibaca.');
        }
        return $bytes;
    }

    private function guideSheet(): string
    {
        $rows = [
            ['PANDUAN IMPORT PESERTA'],
            ['Isi hanya Sheet Data Peserta; Sheet Panduan tidak diimport.'],
            ['NISN, Nama, Jenis Kelamin, Rombel wajib.'],
            ['Jenis Kelamin L atau P. Rombel harus aktif dan cocok dengan daftar di bawah.'],
            ['Keterangan, Username, Password opsional.'],
            ['NISN, Username, Password menggunakan format teks agar nol awal tidak hilang.'],
            ['Password 8–64 karakter. Username 3–64 huruf/angka/titik/minus/underscore.'],
            ['Upload → Parse → Validasi → Preview → Fix/Exclude → Commit.'],
            [], ['ROMBEL AKTIF TERSEDIA'], ['Rombel'],
        ];
        foreach (Database::connect()->table('rombel')->select('display_name')
            ->where('status', 'ACTIVE')->orderBy('tingkat')->orderBy('kode_rombel')->get()->getResultArray() as $rombel) {
            $rows[] = [$rombel['display_name']];
        }
        $xml = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
            . '<worksheet xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main">'
            . '<sheetData>';
        foreach ($rows as $index => $row) {
            $number = $index + 1;
            $xml .= '<row r="' . $number . '">';
            foreach ($row as $column => $value) {
                $ref = chr(65 + $column) . $number;
                $xml .= '<c r="' . $ref . '" t="inlineStr"><is><t>'
                    . htmlspecialchars((string) $value, ENT_XML1 | ENT_QUOTES, 'UTF-8')
                    . '</t></is></c>';
            }
            $xml .= '</row>';
        }
        return $xml . '</sheetData></worksheet>';
    }
}
