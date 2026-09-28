<?= $this->extend('manager/layouts/main') ?>
<?= $this->section('content') ?>
<section class="manager-page-header"><div><div class="manager-page-kicker">Master Ujian / Bank Soal</div><h1 class="manager-page-title">Impor Soal Akademik</h1><p class="manager-page-description">Unggah template Word atau Excel, periksa setiap baris staging, lalu commit ke Bank DRAFT.</p></div>
    <a class="btn btn-outline-secondary" href="<?= base_url('manager/master-ujian/bank-soal') ?>">Daftar Bank</a></section>
<div id="questionImportApp" data-api="<?= esc(base_url('manager/api/bank-soal/' . $bankId . '/imports'), 'attr') ?>" data-bank="<?= (int) $bankId ?>" data-template="<?= esc(base_url('manager/api/bank-soal/template'), 'attr') ?>">
    <section class="manager-section-card mb-3"><div class="card-header fw-bold">Template dan Unggah</div><div class="card-body">
        <div class="d-flex gap-2 mb-3 flex-wrap"><a class="btn btn-outline-primary btn-sm" href="<?= base_url('manager/import-template/bank-soal.xlsx') ?>">Template Excel</a><a class="btn btn-outline-primary btn-sm" href="<?= base_url('manager/api/bank-soal/' . $bankId . '/template/docx') ?>">Template Word sesuai Komposisi</a></div>
        <p class="small text-secondary mb-1">Template Word dibuat otomatis dari Komposisi Bank: jumlah soal, pilihan, dan pasangan sudah disiapkan. Isi sel yang tersedia tanpa JSON, lalu unggah kembali file DOCX yang sama.</p>
        <p class="small text-secondary">Template Excel masih memakai format teknis lama pada tahap ini. File maksimal 5 MB dan 200 soal.</p>
        <form id="questionImportForm" class="d-flex gap-2 align-items-end flex-wrap"><div><label class="cbt-form-label" for="questionImportFile">File XLSX/DOCX</label><input id="questionImportFile" class="form-control" type="file" accept=".xlsx,.docx" required></div><button id="questionImportUpload" class="btn btn-cbt-primary" type="submit">Unggah dan Validasi</button></form>
        <div class="mt-3"><label class="cbt-form-label" for="questionImportHistory">Riwayat impor Bank ini</label><select class="form-select" id="questionImportHistory" style="max-width:480px"><option value="">Pilih job terdahulu</option></select></div>
        <div class="cbt-inline-feedback mt-2" id="questionImportFeedback" role="status" aria-live="polite"></div>
    </div></section>
    <section id="questionImportStaging" class="manager-section-card" hidden><div class="card-header d-flex justify-content-between align-items-center gap-2"><strong id="questionImportTitle">Staging</strong><div class="d-flex gap-2"><button class="btn btn-outline-primary btn-sm" id="questionImportValidate" type="button">Validasi ulang</button><button class="btn btn-cbt-primary btn-sm" id="questionImportCommit" type="button">Commit soal valid</button></div></div>
        <div class="card-body"><p id="questionImportSummary" class="mb-2"></p><p class="small text-secondary mb-0">Baris invalid dapat diperbaiki sebagai JSON atau dikeluarkan. Commit hanya tersedia setelah semua baris aktif valid.</p></div>
        <div class="table-responsive"><table class="table manager-table mb-0"><thead><tr><th>Baris</th><th>Tipe</th><th>Pertanyaan</th><th>Status / Kesalahan</th><th>Aksi</th></tr></thead><tbody id="questionImportRows"></tbody></table></div>
    </section>
</div>
<?= $this->endSection() ?>
<?= $this->section('pageScripts') ?>
<link rel="stylesheet" href="<?= base_url('assets/vendor/katex/katex.min.css') ?>">
<script src="<?= base_url('assets/vendor/katex/katex.min.js') ?>" defer></script>
<script src="<?= base_url('assets/js/question-renderer.js') ?>" defer></script>
<script src="<?= base_url('assets/js/manager-question-import.js') ?>" defer></script>
<?= $this->endSection() ?>
