<?php

namespace App\Services;

use Config\Database;
use RuntimeException;
use Throwable;
use ZipArchive;

class QuestionExcelTemplateService
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
    private const SHEET_NAMES = [
        'PG' => 'PG',
        'PG_KOMPLEKS' => 'PG KOMPLEKS',
        'PG_BERTINGKAT' => 'PG BERTINGKAT',
        'MATCHING' => 'MENJODOHKAN',
        'ISIAN_SINGKAT' => 'ISIAN SINGKAT',
        'URAIAN' => 'URAIAN',
    ];

    public function excel(int $bankId): array
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
        if (!$configs) return $this->error(409, 'STATE_CONFLICT', 'Atur Komposisi Bank sebelum mengunduh template Excel.');

        $rank = array_flip(self::TYPE_ORDER);
        foreach ($configs as $config) {
            $type = (string) ($config['question_type'] ?? '');
            $count = (int) ($config['question_count'] ?? 0);
            if (!in_array($type, self::TYPE_ORDER, true) || $count < 1)
                return $this->error(409, 'STATE_CONFLICT', 'Komposisi Bank belum valid untuk pembuatan template Excel.');
            if (in_array($type, ['PG', 'PG_KOMPLEKS', 'PG_BERTINGKAT', 'MATCHING'], true)) {
                $optionCount = (int) ($config['option_count'] ?? 0);
                $max = $type === 'PG' ? 6 : ($type === 'MATCHING' ? 12 : 8);
                if ($optionCount < 2 || $optionCount > $max)
                    return $this->error(409, 'STATE_CONFLICT', 'Jumlah pilihan/pasangan Komposisi Bank belum valid.');
            }
        }
        usort($configs, static fn(array $a, array $b): int =>
            ($rank[$a['question_type']] ?? 99) <=> ($rank[$b['question_type']] ?? 99));
        $total = array_sum(array_map(static fn(array $row): int => (int) $row['question_count'], $configs));
        if ($total > 200) return $this->error(422, 'VALIDATION_FAILED', 'Template Excel mendukung maksimal 200 soal per file.');

        try {
            $bytes = $this->package($bank, $configs);
        } catch (Throwable $e) {
            log_message('error', 'Generator template Excel gagal: {message}', ['message' => $e->getMessage()]);
            return $this->error(500, 'TEMPLATE_FAILED', 'Template Excel tidak dapat dibuat.');
        }

        $slug = preg_replace('/[^A-Za-z0-9_-]+/', '_', (string) $bank['nama_bank']) ?: 'bank_soal';
        $slug = trim($slug, '_');
        return ['ok' => true, 'status' => 200, 'data' => [
            'content' => $bytes,
            'filename' => 'template_' . ($slug !== '' ? $slug : 'bank_soal') . '.xlsx',
        ]];
    }

    private function package(array $bank, array $configs): string
    {
        $sheets = [['name' => 'PETUNJUK', 'xml' => $this->guideSheet($bank, $configs)]];
        foreach ($configs as $config) {
            $type = (string) $config['question_type'];
            $sheets[] = ['name' => self::SHEET_NAMES[$type], 'xml' => $this->questionSheet($config)];
        }

        $tmp = tempnam(sys_get_temp_dir(), 'cbt_excel_');
        if ($tmp === false) throw new RuntimeException('File sementara tidak dapat dibuat.');
        @unlink($tmp);
        $path = $tmp . '.xlsx';

        $zip = new ZipArchive();
        if ($zip->open($path, ZipArchive::CREATE | ZipArchive::OVERWRITE) !== true)
            throw new RuntimeException('Paket XLSX tidak dapat dibuat.');

        try {
            $zip->addFromString('[Content_Types].xml', $this->contentTypesXml(count($sheets)));
            $zip->addEmptyDir('_rels');
            $zip->addFromString('_rels/.rels', $this->rootRelsXml());
            $zip->addEmptyDir('xl');
            $zip->addEmptyDir('xl/_rels');
            $zip->addEmptyDir('xl/worksheets');
            $zip->addFromString('xl/workbook.xml', $this->workbookXml($sheets));
            $zip->addFromString('xl/_rels/workbook.xml.rels', $this->workbookRelsXml(count($sheets)));
            $zip->addFromString('xl/styles.xml', $this->stylesXml());
            foreach ($sheets as $index => $sheet)
                $zip->addFromString('xl/worksheets/sheet' . ($index + 1) . '.xml', $sheet['xml']);
        } finally {
            $zip->close();
        }

        $bytes = file_get_contents($path);
        @unlink($path);
        if ($bytes === false || strlen($bytes) < 1000) throw new RuntimeException('XLSX kosong.');
        return $bytes;
    }

    private function guideSheet(array $bank, array $configs): string
    {
        $rows = [];
        $rows[] = $this->row(1, [$this->stringCell('A1', 'CBT-HERO-EXCEL-V2', 0)], true, 2);
        $rows[] = $this->row(2, [$this->stringCell('A2', 'Template Impor Soal Akademik', 1)], false, 28);
        $context = (string) $bank['nama_bank'] . ' · ' . (string) $bank['kegiatan_nama']
            . ' · ' . (string) $bank['mapel_nama'] . ' · Tingkat ' . (string) $bank['tingkat'];
        $rows[] = $this->row(3, [$this->stringCell('A3', $context, 2)], false, 22);
        $rows[] = $this->row(5, [$this->stringCell('A5', 'Petunjuk Pengisian', 3)], false, 22);

        $instructions = [
            'Template ini dibuat otomatis dari Komposisi Bank. Isi sheet jenis soal yang tersedia; jangan menambah, menghapus, atau memindahkan kolom.',
            'Setiap baris adalah satu soal. Baris yang benar-benar kosong akan dilewati saat impor.',
            'Gambar: tempel atau Insert gambar lalu letakkan di dalam area sel Soal, Pilihan, pasangan Menjodohkan, atau Rubrik yang sesuai. Gambar boleh berdampingan dengan teks.',
            'Audio: ketik Audio: lalu tempel link file Google Drive langsung di dalam sel yang membutuhkan audio.',
            'Video: ketik Video: lalu tempel link file Google Drive langsung di dalam sel yang membutuhkan video. Pastikan file Google Drive dapat dibuka oleh siapa saja yang memiliki link.',
            'Rumus: untuk Excel, gunakan tulisan biasa bila sederhana atau tempel rumus sebagai gambar. Jangan memakai rumus/formula Excel (=SUM, =A1+B1, dan sejenisnya) pada template.',
            'Huruf Arab, aksara Jawa, dan huruf lain dapat diketik atau ditempel langsung seperti teks biasa.',
            'PG: isi satu huruf jawaban benar, misalnya B. PG Kompleks: isi semua huruf yang benar, misalnya A,C.',
            'Isian Singkat mode TEKS: tulis beberapa jawaban yang diterima dalam satu sel dengan baris baru. Mode ANGKA: isi angka harapan dan toleransi.',
            'Sebelum commit, periksa Pratinjau hasil impor agar gambar, rumus, audio, video, jawaban, dan nilai sudah benar.',
        ];
        $rowNo = 6;
        foreach ($instructions as $i => $line) {
            $rows[] = $this->row($rowNo, [
                $this->stringCell('A' . $rowNo, (string) ($i + 1), 5),
                $this->stringCell('B' . $rowNo, $line, 4),
            ], false, 36);
            $rowNo++;
        }

        $rowNo++;
        $rows[] = $this->row($rowNo, [
            $this->stringCell('A' . $rowNo, 'Tipe Soal', 3),
            $this->stringCell('B' . $rowNo, 'Jumlah', 3),
            $this->stringCell('C' . $rowNo, 'Pilihan/Pasangan', 3),
            $this->stringCell('D' . $rowNo, 'Bobot', 3),
            $this->stringCell('E' . $rowNo, 'Keterangan', 3),
        ], false, 24);
        foreach ($configs as $config) {
            $rowNo++;
            $type = (string) $config['question_type'];
            $choice = in_array($type, ['PG', 'PG_KOMPLEKS', 'PG_BERTINGKAT', 'MATCHING'], true)
                ? (string) (int) $config['option_count'] : '—';
            $note = $type === 'MATCHING'
                ? ((string) $config['scoring_mode'] === 'ALL_OR_NOTHING' ? 'Semua pasangan harus benar' : 'Dinilai per pasangan')
                : ($type === 'ISIAN_SINGKAT' ? 'TEKS atau ANGKA'
                    : ($type === 'URAIAN' ? 'Gunakan pedoman penilaian' : ''));
            $rows[] = $this->row($rowNo, [
                $this->stringCell('A' . $rowNo, self::TYPE_LABELS[$type] ?? $type, 4),
                $this->numberCell('B' . $rowNo, (int) $config['question_count'], 5),
                $this->stringCell('C' . $rowNo, $choice, 5),
                $this->stringCell('D' . $rowNo, $this->decimal((string) $config['weight_percent']) . '%', 5),
                $this->stringCell('E' . $rowNo, $note, 4),
            ], false, 22);
        }

        $last = max(22, $rowNo);
        return $this->worksheetXml(
            implode('', $rows),
            '<cols><col min="1" max="1" width="18" customWidth="1"/><col min="2" max="2" width="88" customWidth="1"/>'
                . '<col min="3" max="3" width="20" customWidth="1"/><col min="4" max="4" width="14" customWidth="1"/>'
                . '<col min="5" max="5" width="38" customWidth="1"/></cols>',
            'A1:E' . $last,
            ['A2:E2', 'A3:E3', 'A5:E5'],
            '',
            ''
        );
    }

    private function questionSheet(array $config): string
    {
        $type = (string) $config['question_type'];
        $count = (int) $config['question_count'];
        $optionCount = (int) ($config['option_count'] ?? 0);
        $mode = (string) ($config['scoring_mode'] ?? '');
        [$headers, $widths, $instruction] = $this->sheetLayout($type, $optionCount, $mode);
        $lastCol = $this->columnName(count($headers));

        $marker = 'CBT-HERO-EXCEL-V2|' . $type . '|' . $optionCount . '|' . $mode;
        $rows = [];
        $rows[] = $this->row(1, [$this->stringCell('A1', $marker, 0)], true, 2);
        $rows[] = $this->row(2, [$this->stringCell('A2', self::TYPE_LABELS[$type] ?? $type, 1)], false, 28);
        $rows[] = $this->row(3, [$this->stringCell('A3', $instruction, 2)], false, 34);

        $headerCells = [];
        foreach ($headers as $i => $header)
            $headerCells[] = $this->stringCell($this->columnName($i + 1) . '5', $header, 3);
        $rows[] = $this->row(5, $headerCells, false, 32);

        for ($i = 1; $i <= $count; $i++) {
            $rowNo = $i + 5;
            $cells = [$this->numberCell('A' . $rowNo, $i, 5)];
            for ($col = 2; $col <= count($headers); $col++) {
                $ref = $this->columnName($col) . $rowNo;
                $value = $this->defaultValue($type, $headers[$col - 1]);
                if ($value === null) $cells[] = $this->blankCell($ref, $this->controlColumn($headers[$col - 1]) ? 5 : 4);
                elseif (is_int($value) || is_float($value)) $cells[] = $this->numberCell($ref, $value, 5);
                else $cells[] = $this->stringCell($ref, (string) $value, $this->controlColumn($headers[$col - 1]) ? 5 : 4);
            }
            $rows[] = $this->row($rowNo, $cells, false, 60);
        }

        $validations = $this->validations($type, $optionCount, $count);
        $cols = '<cols>';
        foreach ($widths as $i => $width)
            $cols .= '<col min="' . ($i + 1) . '" max="' . ($i + 1) . '" width="' . $width . '" customWidth="1"/>';
        $cols .= '</cols>';

        return $this->worksheetXml(
            implode('', $rows),
            $cols,
            'A1:' . $lastCol . ($count + 5),
            ['A2:' . $lastCol . '2', 'A3:' . $lastCol . '3'],
            '<pane ySplit="5" topLeftCell="A6" activePane="bottomLeft" state="frozen"/>',
            $validations
        );
    }

    private function sheetLayout(string $type, int $optionCount, string $mode): array
    {
        if ($type === 'PG') {
            $headers = ['No', 'Soal'];
            for ($i = 0; $i < $optionCount; $i++) $headers[] = 'Pilihan ' . chr(65 + $i);
            $headers[] = 'Jawaban Benar';
            $widths = [7, 44, ...array_fill(0, $optionCount, 28), 18];
            return [$headers, $widths,
                'Isi soal dan semua pilihan. Pada Jawaban Benar tulis satu huruf, misalnya B. Poin maksimum otomatis 1.'];
        }
        if ($type === 'PG_KOMPLEKS') {
            $headers = ['No', 'Soal'];
            for ($i = 0; $i < $optionCount; $i++) $headers[] = 'Pilihan ' . chr(65 + $i);
            $headers[] = 'Pilihan Benar';
            $widths = [7, 44, ...array_fill(0, $optionCount, 28), 22];
            return [$headers, $widths,
                'Isi soal dan semua pilihan. Pada Pilihan Benar tulis semua huruf yang benar, misalnya A,C. Harus tetap ada pilihan yang salah.'];
        }
        if ($type === 'PG_BERTINGKAT') {
            $headers = ['No', 'Soal'];
            $widths = [7, 44];
            for ($i = 0; $i < $optionCount; $i++) {
                $letter = chr(65 + $i);
                $headers[] = 'Pilihan ' . $letter;
                $headers[] = 'Poin ' . $letter;
                $widths[] = 28; $widths[] = 12;
            }
            return [$headers, $widths,
                'Isi setiap pilihan dan poinnya. Poin maksimum soal otomatis mengikuti nilai pilihan tertinggi. Sedikitnya satu pilihan harus bernilai lebih dari 0.'];
        }
        if ($type === 'MATCHING') {
            $headers = ['No', 'Soal'];
            $widths = [7, 42];
            for ($i = 1; $i <= $optionCount; $i++) {
                $headers[] = 'Kiri ' . $i;
                $headers[] = 'Pasangan Kanan ' . $i;
                $widths[] = 24; $widths[] = 24;
            }
            $headers[] = 'Poin Maksimum'; $widths[] = 16;
            $modeText = $mode === 'ALL_OR_NOTHING' ? 'semua pasangan harus benar' : 'dinilai per pasangan';
            return [$headers, $widths,
                'Isi bagian kiri dan pasangan kanan yang benar. Jumlah pasangan sudah ditetapkan. Cara penilaian: ' . $modeText . '.'];
        }
        if ($type === 'ISIAN_SINGKAT') {
            return [
                ['No', 'Soal', 'Mode', 'Jawaban Diterima / Angka Harapan', 'Toleransi', 'Poin Maksimum'],
                [7, 48, 14, 38, 14, 16],
                'Pilih Mode TEKS atau ANGKA. TEKS: beberapa jawaban boleh ditulis dalam satu sel dengan baris baru. ANGKA: isi angka harapan dan toleransi; toleransi 0 berarti harus tepat.'
            ];
        }
        return [
            ['No', 'Soal', 'Pedoman Penilaian', 'Poin Maksimum'],
            [7, 52, 48, 16],
            'Isi soal, pedoman penilaian, dan poin maksimum. Poin maksimum sudah diisi 1 dan boleh diganti sesuai kebutuhan.'
        ];
    }

    private function defaultValue(string $type, string $header): string|int|float|null
    {
        if ($type === 'ISIAN_SINGKAT' && $header === 'Mode') return 'TEKS';
        if (in_array($type, ['ISIAN_SINGKAT', 'URAIAN'], true) && $header === 'Poin Maksimum') return 1;
        return null;
    }

    private function controlColumn(string $header): bool
    {
        return str_starts_with($header, 'Poin ') || in_array($header, [
            'No', 'Jawaban Benar', 'Pilihan Benar', 'Mode', 'Toleransi', 'Poin Maksimum'
        ], true);
    }

    private function validations(string $type, int $optionCount, int $count): string
    {
        if ($count < 1) return '';
        $end = $count + 5;
        $items = [];
        if ($type === 'PG') {
            $col = $this->columnName(3 + $optionCount);
            $letters = implode(',', array_map(static fn(int $i): string => chr(65 + $i), range(0, $optionCount - 1)));
            $items[] = '<dataValidation type="list" allowBlank="1" showErrorMessage="1" error="Pilih satu huruf jawaban." sqref="'
                . $col . '6:' . $col . $end . '"><formula1>&quot;' . $letters . '&quot;</formula1></dataValidation>';
        }
        if ($type === 'ISIAN_SINGKAT') {
            $items[] = '<dataValidation type="list" allowBlank="0" showErrorMessage="1" error="Pilih TEKS atau ANGKA." sqref="C6:C'
                . $end . '"><formula1>&quot;TEKS,ANGKA&quot;</formula1></dataValidation>';
        }
        return $items ? '<dataValidations count="' . count($items) . '">' . implode('', $items) . '</dataValidations>' : '';
    }

    private function worksheetXml(string $rows, string $cols, string $dimension, array $merges, string $pane, string $validations): string
    {
        $mergeXml = '';
        if ($merges) {
            $mergeXml = '<mergeCells count="' . count($merges) . '">';
            foreach ($merges as $ref) $mergeXml .= '<mergeCell ref="' . $ref . '"/>';
            $mergeXml .= '</mergeCells>';
        }
        return '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
            . '<worksheet xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main" '
            . 'xmlns:r="http://schemas.openxmlformats.org/officeDocument/2006/relationships">'
            . '<dimension ref="' . $dimension . '"/>'
            . '<sheetViews><sheetView workbookViewId="0">' . $pane . '</sheetView></sheetViews>'
            . '<sheetFormatPr defaultRowHeight="18"/>'
            . $cols
            . '<sheetData>' . $rows . '</sheetData>'
            . $mergeXml . $validations
            . '<pageMargins left="0.3" right="0.3" top="0.5" bottom="0.5" header="0.2" footer="0.2"/>'
            . '<pageSetup orientation="landscape" paperSize="9"/>'
            . '</worksheet>';
    }

    private function row(int $number, array $cells, bool $hidden = false, int $height = 20): string
    {
        return '<row r="' . $number . '" ht="' . $height . '" customHeight="1"'
            . ($hidden ? ' hidden="1"' : '') . '>' . implode('', $cells) . '</row>';
    }

    private function stringCell(string $ref, string $value, int $style): string
    {
        return '<c r="' . $ref . '" t="inlineStr" s="' . $style . '"><is><t xml:space="preserve">'
            . $this->xml($value) . '</t></is></c>';
    }

    private function numberCell(string $ref, int|float $value, int $style): string
    {
        return '<c r="' . $ref . '" s="' . $style . '"><v>' . $value . '</v></c>';
    }

    private function blankCell(string $ref, int $style): string
    {
        return '<c r="' . $ref . '" s="' . $style . '"/>';
    }

    private function columnName(int $index): string
    {
        $name = '';
        while ($index > 0) {
            $index--;
            $name = chr(65 + ($index % 26)) . $name;
            $index = intdiv($index, 26);
        }
        return $name;
    }

    private function workbookXml(array $sheets): string
    {
        $xml = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
            . '<workbook xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main" '
            . 'xmlns:r="http://schemas.openxmlformats.org/officeDocument/2006/relationships"><sheets>';
        foreach ($sheets as $i => $sheet)
            $xml .= '<sheet name="' . $this->xml((string) $sheet['name']) . '" sheetId="' . ($i + 1)
                . '" r:id="rId' . ($i + 1) . '"/>';
        return $xml . '</sheets><calcPr calcId="191029"/></workbook>';
    }

    private function workbookRelsXml(int $sheetCount): string
    {
        $xml = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
            . '<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">';
        for ($i = 1; $i <= $sheetCount; $i++)
            $xml .= '<Relationship Id="rId' . $i
                . '" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/worksheet" Target="worksheets/sheet'
                . $i . '.xml"/>';
        $xml .= '<Relationship Id="rId' . ($sheetCount + 1)
            . '" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/styles" Target="styles.xml"/>';
        return $xml . '</Relationships>';
    }

    private function contentTypesXml(int $sheetCount): string
    {
        $xml = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
            . '<Types xmlns="http://schemas.openxmlformats.org/package/2006/content-types">'
            . '<Default Extension="rels" ContentType="application/vnd.openxmlformats-package.relationships+xml"/>'
            . '<Default Extension="xml" ContentType="application/xml"/>'
            . '<Override PartName="/xl/workbook.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.sheet.main+xml"/>'
            . '<Override PartName="/xl/styles.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.styles+xml"/>';
        for ($i = 1; $i <= $sheetCount; $i++)
            $xml .= '<Override PartName="/xl/worksheets/sheet' . $i
                . '.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.worksheet+xml"/>';
        return $xml . '</Types>';
    }

    private function rootRelsXml(): string
    {
        return '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
            . '<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">'
            . '<Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/officeDocument" Target="xl/workbook.xml"/>'
            . '</Relationships>';
    }

    private function stylesXml(): string
    {
        return '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
            . '<styleSheet xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main">'
            . '<fonts count="4">'
            . '<font><sz val="10"/><name val="Calibri"/></font>'
            . '<font><b/><sz val="16"/><color rgb="FF17324D"/><name val="Calibri"/></font>'
            . '<font><sz val="10"/><color rgb="FF475467"/><name val="Calibri"/></font>'
            . '<font><b/><sz val="10"/><color rgb="FFFFFFFF"/><name val="Calibri"/></font>'
            . '</fonts>'
            . '<fills count="4"><fill><patternFill patternType="none"/></fill><fill><patternFill patternType="gray125"/></fill>'
            . '<fill><patternFill patternType="solid"><fgColor rgb="FF274B63"/><bgColor indexed="64"/></patternFill></fill>'
            . '<fill><patternFill patternType="solid"><fgColor rgb="FFF6F8FB"/><bgColor indexed="64"/></patternFill></fill></fills>'
            . '<borders count="2"><border/><border>'
            . '<left style="thin"><color rgb="FFD8DEE8"/></left><right style="thin"><color rgb="FFD8DEE8"/></right>'
            . '<top style="thin"><color rgb="FFD8DEE8"/></top><bottom style="thin"><color rgb="FFD8DEE8"/></bottom>'
            . '<diagonal/></border></borders>'
            . '<cellStyleXfs count="1"><xf numFmtId="0" fontId="0" fillId="0" borderId="0"/></cellStyleXfs>'
            . '<cellXfs count="6">'
            . '<xf numFmtId="0" fontId="0" fillId="0" borderId="0" xfId="0"/>'
            . '<xf numFmtId="0" fontId="1" fillId="0" borderId="0" xfId="0"><alignment vertical="center"/></xf>'
            . '<xf numFmtId="0" fontId="2" fillId="0" borderId="0" xfId="0"><alignment wrapText="1" vertical="center"/></xf>'
            . '<xf numFmtId="0" fontId="3" fillId="2" borderId="1" xfId="0"><alignment horizontal="center" vertical="center" wrapText="1"/></xf>'
            . '<xf numFmtId="0" fontId="0" fillId="3" borderId="1" xfId="0"><alignment vertical="top" wrapText="1"/></xf>'
            . '<xf numFmtId="0" fontId="0" fillId="3" borderId="1" xfId="0"><alignment horizontal="center" vertical="center" wrapText="1"/></xf>'
            . '</cellXfs>'
            . '<cellStyles count="1"><cellStyle name="Normal" xfId="0" builtinId="0"/></cellStyles>'
            . '</styleSheet>';
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

    private function error(int $status, string $code, string $message): array
    {
        return compact('status', 'code', 'message') + ['ok' => false];
    }
}
