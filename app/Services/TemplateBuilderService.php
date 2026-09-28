<?php

namespace App\Services;

use Config\Database;
use RuntimeException;
use Throwable;
use ZipArchive;

class TemplateBuilderService
{
    private const TYPE_ORDER = ['PG', 'PG_KOMPLEKS', 'PG_BERTINGKAT', 'MATCHING', 'ISIAN_SINGKAT', 'URAIAN'];
    private const TYPE_LABELS = [
        'PG' => 'Pilihan Ganda',
        'PG_KOMPLEKS' => 'PG Kompleks',
        'PG_BERTINGKAT' => 'PG Bertingkat',
        'MATCHING' => 'Menjodohkan',
        'ISIAN_SINGKAT' => 'Isian Singkat',
        'URAIAN' => 'Uraian',
    ];

    public function word(int $bankId): array
    {
        if ($bankId < 1) return $this->error(404, 'NOT_FOUND', 'Bank tidak ditemukan.');
        if (!extension_loaded('zip')) return $this->error(500, 'SERVER_MISCONFIGURED', 'Ekstensi PHP zip diperlukan.');

        $db = Database::connect();
        $bank = $db->table('bank_soal AS b')
            ->select('b.id, b.nama_bank, b.tingkat, b.status, k.nama AS kegiatan_nama, k.status AS kegiatan_status, m.nama_mapel AS mapel_nama')
            ->join('kegiatan AS k', 'k.id = b.kegiatan_id')
            ->join('mata_pelajaran AS m', 'm.id = b.mapel_id')
            ->where('b.id', $bankId)->get()->getRowArray();
        if ($bank === null) return $this->error(404, 'NOT_FOUND', 'Bank tidak ditemukan.');

        $configs = $db->table('bank_type_config')
            ->select('question_type, question_count, option_count, weight_percent, scoring_mode')
            ->where('bank_soal_id', $bankId)->get()->getResultArray();
        if (!$configs) return $this->error(409, 'STATE_CONFLICT', 'Atur Komposisi Bank sebelum mengunduh template Word.');

        foreach ($configs as $config) {
            $type = (string) ($config['question_type'] ?? '');
            $count = (int) ($config['question_count'] ?? 0);
            if (!in_array($type, self::TYPE_ORDER, true) || $count < 1)
                return $this->error(409, 'STATE_CONFLICT', 'Komposisi Bank belum valid untuk pembuatan template Word.');
            if (in_array($type, ['PG', 'PG_KOMPLEKS', 'PG_BERTINGKAT', 'MATCHING'], true)) {
                $optionCount = (int) ($config['option_count'] ?? 0);
                $max = $type === 'PG' ? 6 : ($type === 'MATCHING' ? 12 : 8);
                if ($optionCount < 2 || $optionCount > $max)
                    return $this->error(409, 'STATE_CONFLICT', 'Jumlah pilihan/pasangan Komposisi Bank belum valid.');
            }
        }
        $rank = array_flip(self::TYPE_ORDER);
        usort($configs, static fn(array $a, array $b): int => ($rank[$a['question_type']] ?? 99) <=> ($rank[$b['question_type']] ?? 99));
        $total = array_sum(array_map(static fn(array $row): int => (int) $row['question_count'], $configs));
        if ($total < 1) return $this->error(409, 'STATE_CONFLICT', 'Komposisi Bank belum memiliki jumlah soal.');
        if ($total > 200) return $this->error(422, 'VALIDATION_FAILED', 'Template Word mendukung maksimal 200 soal per file sesuai batas impor saat ini.');

        try {
            $bytes = $this->wordPackage($bank, $configs);
        } catch (Throwable $e) {
            log_message('error', 'Generator template Word gagal: {message}', ['message' => $e->getMessage()]);
            return $this->error(500, 'TEMPLATE_FAILED', 'Template Word tidak dapat dibuat.');
        }

        $slug = preg_replace('/[^A-Za-z0-9_-]+/', '_', (string) $bank['nama_bank']) ?: 'bank_soal';
        $slug = trim($slug, '_');
        return ['ok' => true, 'status' => 200, 'data' => [
            'content' => $bytes,
            'filename' => 'template_' . ($slug !== '' ? $slug : 'bank_soal') . '.docx',
        ]];
    }

    private function wordPackage(array $bank, array $configs): string
    {
        $tmp = tempnam(sys_get_temp_dir(), 'cbt_word_');
        if ($tmp === false) throw new RuntimeException('File sementara tidak dapat dibuat.');
        @unlink($tmp);
        $path = $tmp . '.docx';
        $zip = new ZipArchive();
        if ($zip->open($path, ZipArchive::CREATE | ZipArchive::OVERWRITE) !== true)
            throw new RuntimeException('Paket DOCX tidak dapat dibuat.');
        try {
            $zip->addFromString('[Content_Types].xml', $this->contentTypesXml());
            $zip->addEmptyDir('_rels');
            $zip->addFromString('_rels/.rels', $this->rootRelsXml());
            $zip->addEmptyDir('word');
            $zip->addEmptyDir('word/_rels');
            $zip->addFromString('word/_rels/document.xml.rels', $this->documentRelsXml());
            $zip->addFromString('word/styles.xml', $this->stylesXml());
            $zip->addFromString('word/document.xml', $this->documentXml($bank, $configs));
        } finally {
            $zip->close();
        }
        $bytes = file_get_contents($path);
        @unlink($path);
        if ($bytes === false || strlen($bytes) < 500) throw new RuntimeException('DOCX kosong.');
        return $bytes;
    }

    private function documentXml(array $bank, array $configs): string
    {
        $parts = [];
        $parts[] = $this->paragraph('Template Impor Soal Akademik', true, 32, '17324D');
        $parts[] = $this->paragraph((string) $bank['nama_bank'] . ' · ' . (string) $bank['kegiatan_nama']
            . ' · ' . (string) $bank['mapel_nama'] . ' · Tingkat ' . (string) $bank['tingkat'], false, 20, '475467');
        $parts[] = $this->paragraph('Ketentuan Pengisian Template Soal', true, 24, '111827', true);
        $parts[] = $this->paragraph('Format: CBT-HERO-WORD-V2', false, 2, 'FFFFFF', false, true);
        foreach ([
            '1. Template ini dibuat otomatis dari Komposisi Bank. Jumlah blok soal dan jumlah pilihan/pasangan sudah ditetapkan oleh aplikasi.',
            '2. Jangan mengubah label Bagian/Soal/Pilihan/Mode/Rubrik. Isi hanya sel yang disediakan; gambar, teks Arab/RTL, rumus, dan tabel boleh ditempatkan pada sel isi.',
            '3. Pilihan Ganda: beri tanda ✓ pada tepat satu jawaban benar. PG Kompleks: beri ✓ pada semua jawaban benar, dengan minimal satu benar dan satu salah.',
            '4. PG Bertingkat: isi poin setiap pilihan. Poin maksimum soal dihitung otomatis dari poin pilihan tertinggi.',
            '5. Menjodohkan: isi setiap sisi kiri dan pasangan kanan. Jumlah pasangan serta cara penilaian mengikuti Komposisi Bank. Poin maksimum adalah skor tertinggi untuk satu soal Menjodohkan; contoh isi 4 bila skor penuh soal tersebut adalah 4.',
            '6. Isian Singkat: pilih mode TEKS atau ANGKA. Untuk TEKS, tulis satu jawaban diterima per baris. Untuk ANGKA, isi angka harapan dan toleransi absolut. Toleransi kosong/0 berarti harus tepat; contoh angka harapan 10 dengan toleransi 0,5 menerima 9,5 sampai 10,5.',
            '7. Uraian: isi soal dan rubrik/pedoman penilaian. Poin maksimum otomatis terisi 1 sebagai default dan boleh diganti sesuai skala rubrik, misalnya 5 bila rubrik memakai skor 0–5.',
            '8. Hapus/ganti teks di dalam tanda kurung siku sebelum impor. Jangan menambah atau menghapus baris struktur template.',
        ] as $line) $parts[] = $this->paragraph($line, false, 18, '243247');

        $summary = [['Tipe Soal', 'Jumlah Soal', 'Pilihan/Pasangan', 'Bobot', 'Catatan']];
        foreach ($configs as $config) {
            $type = (string) $config['question_type'];
            $choice = in_array($type, ['PG', 'PG_KOMPLEKS', 'PG_BERTINGKAT', 'MATCHING'], true)
                ? (string) (int) $config['option_count'] : '—';
            $note = $type === 'MATCHING'
                ? ($config['scoring_mode'] === 'ALL_OR_NOTHING' ? 'Semua benar; isi poin maksimum per soal' : 'Poin sebagian; isi poin maksimum per soal')
                : ($type === 'PG' || $type === 'PG_KOMPLEKS' ? 'Poin maksimum otomatis 1'
                    : ($type === 'URAIAN' ? 'Poin maksimum default 1; boleh diubah' : ''));
            $summary[] = [self::TYPE_LABELS[$type] ?? $type, (string) (int) $config['question_count'], $choice,
                $this->decimal((string) $config['weight_percent']) . '%', $note];
        }
        $parts[] = $this->paragraph('Komposisi Bank', true, 22, '111827', true);
        $parts[] = $this->table($summary, [2400, 1500, 1700, 1200, 3500], true);

        foreach ($configs as $config) {
            $type = (string) $config['question_type'];
            $count = (int) $config['question_count'];
            $optionCount = (int) ($config['option_count'] ?? 0);
            $parts[] = $this->paragraph(self::TYPE_LABELS[$type] ?? $type, true, 28, '111827', true, false, true);
            $parts[] = $this->paragraph($this->typeInstruction($type, $optionCount, (string) ($config['scoring_mode'] ?? '')), false, 18, '475467');

            if ($type === 'ISIAN_SINGKAT') {
                $rows = [['No', 'Soal', 'Mode', 'Jawaban diterima / Angka harapan', 'Toleransi (khusus ANGKA)', 'Poin maksimum']];
                for ($i = 1; $i <= $count; $i++) {
                    $rows[] = [$this->hiddenMarker("CBT-HERO-WORD-V2|ISIAN_SINGKAT|{$i}") . (string) $i,
                        '[Tulis soal isian singkat]', '[TEKS/ANGKA]', '[Satu jawaban per baris / angka harapan]', '[Kosong/0 = tepat; contoh 0,5]', '[Isi angka]'];
                }
                $parts[] = $this->table($rows, [600, 3800, 1200, 3300, 1500, 1500], true);
                continue;
            }
            if ($type === 'URAIAN') {
                $rows = [['No', 'Soal', 'Rubrik / Pedoman Penilaian', 'Poin maksimum']];
                for ($i = 1; $i <= $count; $i++) {
                    $rows[] = [$this->hiddenMarker("CBT-HERO-WORD-V2|URAIAN|{$i}") . (string) $i,
                        '[Tulis soal uraian]', '[Tulis rubrik/pedoman penilaian]', '1'];
                }
                $parts[] = $this->table($rows, [650, 4800, 4400, 1600], true);
                continue;
            }

            for ($i = 1; $i <= $count; $i++) {
                $parts[] = $this->paragraph('Soal ' . $i, true, 20, '111827', true);
                if ($type === 'MATCHING') {
                    $rows = [[$this->hiddenMarker("CBT-HERO-WORD-V2|MATCHING|{$i}") . 'Soal', 'Sisi kiri', 'Pasangan benar di sisi kanan']];
                    for ($j = 1; $j <= $optionCount; $j++)
                        $rows[] = [$j === 1 ? '[Tulis soal di sini]' : '', '[Isi kiri ' . $j . ']', '[Isi kanan ' . $j . ']'];
                    $rows[] = ['Cara penilaian', $config['scoring_mode'] === 'ALL_OR_NOTHING' ? 'Semua benar' : 'Poin sebagian', ''];
                    $rows[] = ['Poin maksimum', '[Isi angka]', ''];
                    $parts[] = $this->table($rows, [4400, 3600, 3600], true);
                    continue;
                }

                $third = $type === 'PG_BERTINGKAT' ? 'Poin pilihan' : 'Benar';
                $rows = [[$this->hiddenMarker("CBT-HERO-WORD-V2|{$type}|{$i}") . 'Bagian', 'Isi', $third],
                    ['Soal', '[Tulis soal di sini]', '']];
                for ($j = 0; $j < $optionCount; $j++) {
                    $letter = chr(65 + $j);
                    $rows[] = ['Pilihan ' . $letter, '[Pilihan ' . $letter . ']', $type === 'PG_BERTINGKAT' ? '[poin]' : ''];
                }
                $parts[] = $this->table($rows, [2600, 7600, 1400], true);
            }
        }

        $body = implode('', $parts);
        return '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
            . '<w:document xmlns:w="http://schemas.openxmlformats.org/wordprocessingml/2006/main">'
            . '<w:body>' . $body
            . '<w:sectPr><w:pgSz w:w="16838" w:h="11906" w:orient="landscape"/>'
            . '<w:pgMar w:top="720" w:right="720" w:bottom="720" w:left="720" w:header="360" w:footer="360" w:gutter="0"/>'
            . '</w:sectPr></w:body></w:document>';
    }

    private function typeInstruction(string $type, int $optionCount, string $mode): string
    {
        return match ($type) {
            'PG' => "Setiap soal mempunyai {$optionCount} pilihan. Beri ✓ pada tepat satu jawaban benar. Poin maksimum otomatis 1.",
            'PG_KOMPLEKS' => "Setiap soal mempunyai {$optionCount} pilihan. Beri ✓ pada semua jawaban benar; harus tetap ada pilihan salah. Poin maksimum otomatis 1.",
            'PG_BERTINGKAT' => "Setiap soal mempunyai {$optionCount} pilihan. Isi poin setiap pilihan; poin maksimum diambil dari nilai pilihan tertinggi.",
            'MATCHING' => "Setiap soal mempunyai {$optionCount} pasangan. Cara penilaian dikunci dari Komposisi Bank: "
                . ($mode === 'ALL_OR_NOTHING' ? 'Semua benar.' : 'Poin sebagian.')
                . ' Poin maksimum adalah skor tertinggi untuk satu soal; contoh isi 4 bila skor penuh soal tersebut adalah 4.',
            'ISIAN_SINGKAT' => 'Satu baris adalah satu soal. Mode TEKS menerima satu atau lebih jawaban (satu per baris). Mode ANGKA memakai angka harapan dan toleransi absolut; kosong/0 berarti harus tepat, contoh 10 dengan toleransi 0,5 menerima 9,5–10,5.',
            'URAIAN' => 'Satu baris adalah satu soal. Isi rubrik/pedoman penilaian. Poin maksimum default 1 sudah diisi otomatis dan boleh diganti sesuai skala rubrik, misalnya 5 untuk rubrik 0–5.',
            default => '',
        };
    }

    private function table(array $rows, array $widths, bool $header): string
    {
        $grid = implode('', array_map(static fn(int $w): string => '<w:gridCol w:w="' . $w . '"/>', $widths));
        $xml = '<w:tbl><w:tblPr><w:tblW w:w="0" w:type="auto"/><w:tblLayout w:type="fixed"/>'
            . '<w:tblBorders><w:top w:val="single" w:sz="4" w:color="CDD5DF"/><w:left w:val="single" w:sz="4" w:color="CDD5DF"/>'
            . '<w:bottom w:val="single" w:sz="4" w:color="CDD5DF"/><w:right w:val="single" w:sz="4" w:color="CDD5DF"/>'
            . '<w:insideH w:val="single" w:sz="4" w:color="D8DEE8"/><w:insideV w:val="single" w:sz="4" w:color="D8DEE8"/></w:tblBorders>'
            . '<w:tblCellMar><w:top w:w="70" w:type="dxa"/><w:left w:w="90" w:type="dxa"/><w:bottom w:w="70" w:type="dxa"/><w:right w:w="90" w:type="dxa"/></w:tblCellMar>'
            . '</w:tblPr><w:tblGrid>' . $grid . '</w:tblGrid>';
        foreach ($rows as $ri => $row) {
            $xml .= '<w:tr><w:trPr><w:cantSplit/></w:trPr>';
            foreach ($widths as $ci => $width) {
                $value = (string) ($row[$ci] ?? '');
                $fill = $header && $ri === 0 ? '274B63' : ($ri % 2 === 0 ? 'F3F6F9' : 'FFFFFF');
                $color = $header && $ri === 0 ? 'FFFFFF' : '1F2937';
                $xml .= '<w:tc><w:tcPr><w:tcW w:w="' . $width . '" w:type="dxa"/><w:shd w:fill="' . $fill . '"/></w:tcPr>'
                    . $this->cellParagraph($value, $header && $ri === 0, 17, $color) . '</w:tc>';
            }
            $xml .= '</w:tr>';
        }
        return $xml . '</w:tbl><w:p><w:pPr><w:spacing w:after="70"/></w:pPr></w:p>';
    }

    private function paragraph(string $text, bool $bold = false, int $size = 18, string $color = '111827', bool $spaceBefore = false,
        bool $hidden = false, bool $pageBreakBefore = false): string
    {
        $pPr = '<w:pPr><w:spacing w:before="' . ($spaceBefore ? '120' : '0') . '" w:after="80"/>'
            . ($pageBreakBefore ? '<w:pageBreakBefore/>' : '') . '</w:pPr>';
        return '<w:p>' . $pPr . $this->run($text, $bold, $size, $color, $hidden) . '</w:p>';
    }

    private function cellParagraph(string $text, bool $bold, int $size, string $color): string
    {
        $runs = [];
        foreach (preg_split('/\R/u', $text) ?: [''] as $i => $part) {
            if ($i > 0) $runs[] = '<w:r><w:br/></w:r>';
            if (str_starts_with($part, '[[HIDDEN:') && str_ends_with($part, ']]')) {
                $runs[] = $this->run(substr($part, 9, -2), false, 2, 'FFFFFF', true);
            } elseif (preg_match('/^\[\[HIDDEN:(.*?)\]\](.*)$/s', $part, $m)) {
                $runs[] = $this->run($m[1], false, 2, 'FFFFFF', true);
                $runs[] = $this->run($m[2], $bold, $size, $color, false);
            } else $runs[] = $this->run($part, $bold, $size, $color, false);
        }
        return '<w:p><w:pPr><w:spacing w:before="0" w:after="0"/></w:pPr>' . implode('', $runs) . '</w:p>';
    }

    private function run(string $text, bool $bold, int $size, string $color, bool $hidden): string
    {
        $rPr = '<w:rPr>' . ($bold ? '<w:b/>' : '') . ($hidden ? '<w:vanish/>' : '')
            . '<w:color w:val="' . $color . '"/><w:sz w:val="' . $size . '"/><w:szCs w:val="' . $size . '"/></w:rPr>';
        return '<w:r>' . $rPr . '<w:t xml:space="preserve">' . $this->xml((string) $text) . '</w:t></w:r>';
    }

    private function hiddenMarker(string $marker): string
    {
        return '[[HIDDEN:<<' . $marker . '>>]]';
    }

    private function decimal(string $value): string
    {
        $number = (float) $value;
        return rtrim(rtrim(number_format($number, 3, '.', ''), '0'), '.');
    }

    private function xml(string $value): string
    {
        return htmlspecialchars($value, ENT_XML1 | ENT_QUOTES, 'UTF-8');
    }

    private function contentTypesXml(): string
    {
        return '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
            . '<Types xmlns="http://schemas.openxmlformats.org/package/2006/content-types">'
            . '<Default Extension="rels" ContentType="application/vnd.openxmlformats-package.relationships+xml"/>'
            . '<Default Extension="xml" ContentType="application/xml"/>'
            . '<Override PartName="/word/document.xml" ContentType="application/vnd.openxmlformats-officedocument.wordprocessingml.document.main+xml"/>'
            . '<Override PartName="/word/styles.xml" ContentType="application/vnd.openxmlformats-officedocument.wordprocessingml.styles+xml"/>'
            . '</Types>';
    }

    private function rootRelsXml(): string
    {
        return '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
            . '<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">'
            . '<Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/officeDocument" Target="word/document.xml"/>'
            . '</Relationships>';
    }

    private function documentRelsXml(): string
    {
        return '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
            . '<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">'
            . '<Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/styles" Target="styles.xml"/>'
            . '</Relationships>';
    }

    private function stylesXml(): string
    {
        return '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
            . '<w:styles xmlns:w="http://schemas.openxmlformats.org/wordprocessingml/2006/main">'
            . '<w:style w:type="paragraph" w:default="1" w:styleId="Normal"><w:name w:val="Normal"/>'
            . '<w:rPr><w:rFonts w:ascii="Arial" w:hAnsi="Arial" w:cs="Arial"/><w:sz w:val="18"/><w:szCs w:val="18"/></w:rPr></w:style>'
            . '</w:styles>';
    }

    private function error(int $status, string $code, string $message): array
    {
        return compact('status', 'code', 'message') + ['ok' => false];
    }
}
