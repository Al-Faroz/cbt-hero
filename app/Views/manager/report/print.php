<!doctype html>
<html lang="id">
<head>
<meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1">
<title><?= esc($pageTitle ?? 'Laporan') ?> | CBT-HERO</title>
<style>
body{font:12px/1.45 Arial,sans-serif;color:#111;margin:18px}.toolbar{display:flex;gap:8px;margin-bottom:18px}.toolbar button{padding:8px 12px}
h1{font-size:20px;margin:0}p{margin:4px 0 16px;color:#555}.table-wrap{overflow:auto}table{border-collapse:collapse;width:100%;font-size:10px}
th,td{border:1px solid #bbb;padding:5px 6px;vertical-align:top}th{background:#eef2f6;text-align:center}td.num{text-align:right}
@media print{body{margin:0}.toolbar{display:none}.table-wrap{overflow:visible}thead{display:table-header-group}tr{break-inside:avoid}@page{size:A4 landscape;margin:10mm}}
</style>
</head>
<body>
<div class="toolbar"><button type="button" onclick="window.print()">Cetak / Simpan PDF</button><button type="button" onclick="window.close()">Tutup</button></div>
<h1><?= esc($report['title'] ?? 'Laporan') ?></h1>
<p><?= esc($report['subtitle'] ?? '') ?></p>
<div class="table-wrap"><table><thead><tr>
<?php foreach (($report['headers'] ?? []) as $header): ?><th><?= esc($header) ?></th><?php endforeach ?>
</tr></thead><tbody>
<?php if (empty($report['rows'])): ?>
<tr><td colspan="<?= max(1, count($report['headers'] ?? [])) ?>" style="text-align:center">Tidak ada data.</td></tr>
<?php else: foreach ($report['rows'] as $row): ?><tr>
<?php foreach ($row as $value): ?><td<?= is_int($value) || is_float($value) ? ' class="num"' : '' ?>><?= esc(is_float($value) ? number_format($value, 2, ',', '.') : (string) $value) ?></td><?php endforeach ?>
</tr><?php endforeach; endif ?>
</tbody></table></div>
</body></html>
