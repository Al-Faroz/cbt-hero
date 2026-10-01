<?php

namespace App\Services;

use RuntimeException;
use Throwable;
use ZipArchive;

class ReportExportService
{
    public function xlsx(string $kind, array $query): array
    {
        if (!extension_loaded('zip')) {
            return $this->error(500, 'SERVER_MISCONFIGURED', 'Ekstensi PHP zip diperlukan untuk export Excel.');
        }

        $report = $this->report($kind, $query);
        if (!($report['ok'] ?? false)) {
            return $report;
        }

        try {
            $bytes = $this->package($report['data']);
        } catch (Throwable $e) {
            log_message('error', 'Export laporan XLSX gagal: {message}', ['message' => $e->getMessage()]);
            return $this->error(500, 'EXPORT_FAILED', 'File Excel belum dapat dibuat.');
        }

        $slug = preg_replace('/[^A-Za-z0-9_-]+/', '_', strtolower((string) $report['data']['slug'])) ?: 'laporan';
        return ['ok' => true, 'status' => 200, 'data' => [
            'content' => $bytes,
            'filename' => trim($slug, '_') . '.xlsx',
        ]];
    }

    public function printable(string $kind, array $query): array
    {
        return $this->report($kind, $query);
    }

    private function report(string $kind, array $query): array
    {
        return match (strtolower($kind)) {
            'hasil' => $this->hasil($query),
            'rekap' => $this->rekap($query),
            'analisis' => $this->analisis($query),
            default => $this->error(404, 'NOT_FOUND', 'Jenis laporan tidak ditemukan.'),
        };
    }

    private function hasil(array $query): array
    {
        $service = new HasilUjianService();
        $items = [];
        $page = 1;
        do {
            $pageQuery = $query;
            $pageQuery['page'] = $page;
            $pageQuery['per_page'] = 100;
            $result = $service->index($pageQuery);
            if (!($result['ok'] ?? false)) return $result;
            $items = array_merge($items, $result['data']['items'] ?? []);
            $pages = (int) ($result['data']['pagination']['pages'] ?? 1);
            $page++;
        } while ($page <= $pages && $page <= 500);

        $rows = [];
        foreach ($items as $item) {
            $rows[] = [
                (string) ($item['nomor_peserta_snapshot'] ?? ''),
                (string) ($item['nama_snapshot'] ?? ''),
                (string) ($item['rombel_snapshot'] ?? ''),
                (string) ($item['nama_mapel'] ?? $item['nama_bank'] ?? ''),
                $item['click_score'] === null ? '' : (float) $item['click_score'],
                $item['typed_score'] === null ? '' : (float) $item['typed_score'],
                $item['final_score'] === null ? '' : (float) $item['final_score'],
                (string) ($item['scoring_status'] ?? ''),
                !empty($item['is_final']) ? 'FINAL' : 'BELUM FINAL',
            ];
        }

        return ['ok' => true, 'status' => 200, 'data' => [
            'title' => 'Hasil Ujian',
            'subtitle' => 'Hasil akademik resmi',
            'slug' => 'hasil_ujian_' . date('Ymd_His'),
            'headers' => ['No Peserta', 'Nama', 'Rombel', 'Mata Pelajaran', 'Nilai Klik', 'Nilai Ketik', 'Nilai Akhir', 'Status Scoring', 'Status Hasil'],
            'rows' => $rows,
        ]];
    }

    private function rekap(array $query): array
    {
        $result = (new RekapNilaiService())->matrix($query);
        if (!($result['ok'] ?? false)) return $result;

        $data = $result['data'];
        $columns = $data['columns'] ?? [];
        $nameCounts = [];
        foreach ($columns as $column) {
            $name = (string) ($column['nama_mapel'] ?? $column['nama_bank'] ?? 'Mapel');
            $nameCounts[$name] = ($nameCounts[$name] ?? 0) + 1;
        }

        $headers = ['No Peserta', 'Nama', 'Rombel'];
        $columnKeys = [];
        foreach ($columns as $column) {
            $name = (string) ($column['nama_mapel'] ?? $column['nama_bank'] ?? 'Mapel');
            $label = ($nameCounts[$name] ?? 0) > 1
                ? $name . ' #' . (int) $column['root_jadwal_id']
                : $name;
            $headers[] = $label;
            $columnKeys[] = (string) (int) $column['root_jadwal_id'];
        }
        $headers[] = 'Rata-rata';

        $rows = [];
        foreach ($data['rows'] ?? [] as $row) {
            $line = [(string) $row['nomor_peserta'], (string) $row['nama'], (string) $row['rombel']];
            foreach ($columnKeys as $key) {
                $score = $row['scores'][$key]['final_score'] ?? null;
                $line[] = $score === null ? '' : (float) $score;
            }
            $line[] = $row['average'] === null ? '' : (float) $row['average'];
            $rows[] = $line;
        }

        $context = $data['context'] ?? [];
        $subtitle = trim(
            (string) ($context['nama'] ?? '')
            . ' · ' . (string) ($context['tahun_pelajaran'] ?? '')
            . ' ' . (string) ($context['semester'] ?? '')
        );

        return ['ok' => true, 'status' => 200, 'data' => [
            'title' => 'Rekap Nilai',
            'subtitle' => $subtitle,
            'slug' => 'rekap_nilai_' . date('Ymd_His'),
            'headers' => $headers,
            'rows' => $rows,
        ]];
    }

    private function analisis(array $query): array
    {
        $result = (new AnalisisSoalService())->index($query);
        if (!($result['ok'] ?? false)) return $result;

        $data = $result['data'];
        $rows = [];
        foreach ($data['items'] ?? [] as $item) {
            $rows[] = [
                (string) ($item['stable_key'] ?? ''),
                $this->typeLabel((string) ($item['question_type'] ?? '')),
                (int) ($item['participant_count'] ?? 0),
                $item['average_percent'] === null ? '' : (float) $item['average_percent'],
                $item['difficulty_index'] === null ? '' : (float) $item['difficulty_index'],
                $item['full_score_rate'] === null ? '' : (float) $item['full_score_rate'],
                $item['zero_score_rate'] === null ? '' : (float) $item['zero_score_rate'],
                (int) ($item['voided_count'] ?? 0),
            ];
        }

        $context = $data['context'] ?? [];
        $subtitle = trim(
            (string) ($context['kegiatan_nama'] ?? '')
            . ' · ' . (string) ($context['nama_mapel'] ?? '')
        );

        return ['ok' => true, 'status' => 200, 'data' => [
            'title' => 'Analisis Soal',
            'subtitle' => $subtitle,
            'slug' => 'analisis_soal_' . date('Ymd_His'),
            'headers' => ['Kode Soal', 'Tipe', 'Peserta', 'Rata-rata (%)', 'Indeks Skor (%)', 'Nilai Penuh (%)', 'Skor Nol (%)', 'Void'],
            'rows' => $rows,
        ]];
    }

    private function package(array $report): string
    {
        $rows = [];
        $rows[] = $this->rowXml(1, [$this->stringCell('A1', (string) $report['title'], 1)], 28);
        $rows[] = $this->rowXml(2, [$this->stringCell('A2', (string) ($report['subtitle'] ?? ''), 2)], 22);

        $headers = $report['headers'] ?? [];
        $headerCells = [];
        foreach ($headers as $index => $header) {
            $headerCells[] = $this->stringCell($this->columnName($index + 1) . '4', (string) $header, 3);
        }
        $rows[] = $this->rowXml(4, $headerCells, 28);

        $rowNo = 5;
        foreach ($report['rows'] ?? [] as $row) {
            $cells = [];
            foreach (array_values($row) as $index => $value) {
                $ref = $this->columnName($index + 1) . $rowNo;
                if (is_int($value) || is_float($value)) {
                    $cells[] = $this->numberCell($ref, $value, 5);
                } else {
                    $cells[] = $this->stringCell($ref, (string) $value, 4);
                }
            }
            $rows[] = $this->rowXml($rowNo, $cells, 22);
            $rowNo++;
        }

        $lastCol = $this->columnName(max(1, count($headers)));
        $cols = '<cols>';
        foreach ($headers as $index => $header) {
            $width = min(42, max(12, mb_strlen((string) $header) + 4));
            if (in_array((string) $header, ['Nama', 'Mata Pelajaran'], true)) $width = 28;
            $cols .= '<col min="' . ($index + 1) . '" max="' . ($index + 1) . '" width="' . $width . '" customWidth="1"/>';
        }
        $cols .= '</cols>';

        $sheet = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
            . '<worksheet xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main">'
            . '<dimension ref="A1:' . $lastCol . max(4, $rowNo - 1) . '"/>'
            . '<sheetViews><sheetView workbookViewId="0"><pane ySplit="4" topLeftCell="A5" activePane="bottomLeft" state="frozen"/></sheetView></sheetViews>'
            . '<sheetFormatPr defaultRowHeight="20"/>' . $cols
            . '<sheetData>' . implode('', $rows) . '</sheetData>'
            . '<autoFilter ref="A4:' . $lastCol . max(4, $rowNo - 1) . '"/>'
            . '<pageMargins left="0.25" right="0.25" top="0.5" bottom="0.5" header="0.2" footer="0.2"/>'
            . '<pageSetup orientation="landscape" paperSize="9" fitToWidth="1" fitToHeight="0"/>'
            . '</worksheet>';

        $tmp = tempnam(sys_get_temp_dir(), 'cbt_report_');
        if ($tmp === false) throw new RuntimeException('File sementara tidak dapat dibuat.');
        @unlink($tmp);
        $path = $tmp . '.xlsx';

        $zip = new ZipArchive();
        if ($zip->open($path, ZipArchive::CREATE | ZipArchive::OVERWRITE) !== true) {
            throw new RuntimeException('Paket XLSX tidak dapat dibuat.');
        }

        try {
            $zip->addFromString('[Content_Types].xml',
                '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
                . '<Types xmlns="http://schemas.openxmlformats.org/package/2006/content-types">'
                . '<Default Extension="rels" ContentType="application/vnd.openxmlformats-package.relationships+xml"/>'
                . '<Default Extension="xml" ContentType="application/xml"/>'
                . '<Override PartName="/xl/workbook.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.sheet.main+xml"/>'
                . '<Override PartName="/xl/styles.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.styles+xml"/>'
                . '<Override PartName="/xl/worksheets/sheet1.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.worksheet+xml"/>'
                . '</Types>');
            $zip->addFromString('_rels/.rels',
                '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
                . '<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">'
                . '<Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/officeDocument" Target="xl/workbook.xml"/>'
                . '</Relationships>');
            $zip->addFromString('xl/workbook.xml',
                '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
                . '<workbook xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main" '
                . 'xmlns:r="http://schemas.openxmlformats.org/officeDocument/2006/relationships">'
                . '<sheets><sheet name="LAPORAN" sheetId="1" r:id="rId1"/></sheets></workbook>');
            $zip->addFromString('xl/_rels/workbook.xml.rels',
                '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
                . '<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">'
                . '<Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/worksheet" Target="worksheets/sheet1.xml"/>'
                . '<Relationship Id="rId2" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/styles" Target="styles.xml"/>'
                . '</Relationships>');
            $zip->addFromString('xl/styles.xml', $this->stylesXml());
            $zip->addFromString('xl/worksheets/sheet1.xml', $sheet);
        } finally {
            $zip->close();
        }

        $bytes = file_get_contents($path);
        @unlink($path);
        if ($bytes === false || strlen($bytes) < 800) {
            throw new RuntimeException('File XLSX kosong.');
        }
        return $bytes;
    }

    private function rowXml(int $number, array $cells, int $height): string
    {
        return '<row r="' . $number . '" ht="' . $height . '" customHeight="1">' . implode('', $cells) . '</row>';
    }

    private function stringCell(string $ref, string $value, int $style): string
    {
        return '<c r="' . $ref . '" t="inlineStr" s="' . $style . '"><is><t xml:space="preserve">'
            . htmlspecialchars($value, ENT_XML1 | ENT_QUOTES, 'UTF-8') . '</t></is></c>';
    }

    private function numberCell(string $ref, int|float $value, int $style): string
    {
        return '<c r="' . $ref . '" s="' . $style . '"><v>'
            . rtrim(rtrim(number_format((float) $value, 6, '.', ''), '0'), '.')
            . '</v></c>';
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

    private function stylesXml(): string
    {
        return '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
            . '<styleSheet xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main">'
            . '<fonts count="4">'
            . '<font><sz val="10"/><name val="Calibri"/></font>'
            . '<font><b/><sz val="16"/><color rgb="FF17324D"/><name val="Calibri"/></font>'
            . '<font><sz val="10"/><color rgb="FF667085"/><name val="Calibri"/></font>'
            . '<font><b/><sz val="10"/><color rgb="FFFFFFFF"/><name val="Calibri"/></font>'
            . '</fonts>'
            . '<fills count="4"><fill><patternFill patternType="none"/></fill><fill><patternFill patternType="gray125"/></fill>'
            . '<fill><patternFill patternType="solid"><fgColor rgb="FF274B63"/></patternFill></fill>'
            . '<fill><patternFill patternType="solid"><fgColor rgb="FFF7F9FC"/></patternFill></fill></fills>'
            . '<borders count="2"><border/><border><left style="thin"><color rgb="FFD8DEE8"/></left>'
            . '<right style="thin"><color rgb="FFD8DEE8"/></right><top style="thin"><color rgb="FFD8DEE8"/></top>'
            . '<bottom style="thin"><color rgb="FFD8DEE8"/></bottom><diagonal/></border></borders>'
            . '<cellStyleXfs count="1"><xf numFmtId="0" fontId="0" fillId="0" borderId="0"/></cellStyleXfs>'
            . '<cellXfs count="6">'
            . '<xf numFmtId="0" fontId="0" fillId="0" borderId="0" xfId="0"/>'
            . '<xf numFmtId="0" fontId="1" fillId="0" borderId="0" xfId="0"/>'
            . '<xf numFmtId="0" fontId="2" fillId="0" borderId="0" xfId="0"/>'
            . '<xf numFmtId="0" fontId="3" fillId="2" borderId="1" xfId="0"><alignment horizontal="center" vertical="center" wrapText="1"/></xf>'
            . '<xf numFmtId="0" fontId="0" fillId="3" borderId="1" xfId="0"><alignment vertical="center" wrapText="1"/></xf>'
            . '<xf numFmtId="0" fontId="0" fillId="3" borderId="1" xfId="0"><alignment horizontal="right" vertical="center"/></xf>'
            . '</cellXfs><cellStyles count="1"><cellStyle name="Normal" xfId="0" builtinId="0"/></cellStyles>'
            . '</styleSheet>';
    }

    private function typeLabel(string $type): string
    {
        return match ($type) {
            'PG' => 'Pilihan Ganda',
            'PG_KOMPLEKS' => 'PG Kompleks',
            'PG_BERTINGKAT' => 'PG Bertingkat',
            'MATCHING' => 'Menjodohkan',
            'ISIAN_SINGKAT' => 'Isian Singkat',
            'URAIAN' => 'Uraian',
            default => $type,
        };
    }

    private function error(int $status, string $code, string $message): array
    {
        return compact('status', 'code', 'message') + ['ok' => false];
    }
}
