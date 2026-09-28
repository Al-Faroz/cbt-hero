<?= $this->extend('manager/layouts/main') ?>
<?= $this->section('content') ?>
<section class="manager-page-header">
    <div><div class="manager-page-kicker">Master Ujian / Bank Soal</div><h1 class="manager-page-title">Soal Akademik Lanjutan</h1>
        <p class="manager-page-description" id="advancedContext">Memuat Bank...</p></div>
    <div class="d-flex gap-2 flex-wrap"><a class="btn btn-outline-secondary" href="<?= base_url('manager/master-ujian/bank-soal/' . $bankId . '/soal') ?>">Soal PG</a>
        <a class="btn btn-outline-secondary" href="<?= base_url('manager/master-ujian/bank-soal/' . $bankId . '/komposisi') ?>">Komposisi</a>
        <a class="btn btn-outline-secondary" href="<?= base_url('manager/master-ujian/bank-soal') ?>">Daftar Bank</a></div>
</section>
<div id="advancedApp" data-api="<?= esc(base_url('manager/api/bank-soal/' . $bankId . '/soal'), 'attr') ?>" data-config="<?= esc(base_url('manager/api/bank-soal/' . $bankId . '/type-config'), 'attr') ?>">
    <section class="manager-section-card mb-3">
        <div class="card-header d-flex gap-2 justify-content-between"><strong>Daftar Soal</strong><button class="btn btn-cbt-primary btn-sm" id="advancedAdd" type="button" disabled>Tambah Soal</button></div>
        <div class="card-body"><div class="row g-2 align-items-end"><div class="col-md-4"><label class="cbt-form-label" for="advancedFilter">Tipe</label><select class="form-select" id="advancedFilter"></select></div><div class="col-md-2"><label class="cbt-form-label" for="advancedSize">Per halaman</label><select class="form-select" id="advancedSize"><option>25</option><option>50</option><option>100</option></select></div></div>
            <div class="cbt-inline-feedback mt-2" id="advancedFeedback" role="status" aria-live="polite"></div></div>
        <div class="table-responsive"><table class="table manager-table mb-0"><thead><tr><th>No</th><th>Tipe</th><th>Pertanyaan</th><th>Revisi</th><th>Aksi</th></tr></thead><tbody id="advancedRows"></tbody></table></div>
        <div class="card-body d-flex justify-content-between align-items-center"><span id="advancedCount"></span><div class="d-flex align-items-center gap-2"><button class="btn btn-outline-secondary btn-sm" id="advancedPrev" type="button">Sebelumnya</button><span id="advancedPage"></span><button class="btn btn-outline-secondary btn-sm" id="advancedNext" type="button">Berikutnya</button></div></div>
    </section>
    <section class="manager-section-card" id="advancedEditor" hidden>
        <div class="card-header fw-bold" id="advancedTitle">Tambah Soal</div>
        <div class="card-body"><form id="advancedForm">
            <div class="row g-2 mb-3"><div class="col-md-6"><label class="cbt-form-label" for="advancedType">Tipe soal</label><select class="form-select" id="advancedType" required></select></div><div class="col-md-3"><label class="cbt-form-label" for="advancedPoint">Poin maksimal</label><input class="form-control" id="advancedPoint" type="number" min="0.0001" max="1000" step="0.0001" value="1" required></div></div>
            <div class="mb-3"><label class="cbt-form-label" for="advancedStimulus">Stimulus (opsional)</label><textarea class="form-control" id="advancedStimulus" rows="2" maxlength="20000"></textarea></div>
            <div class="mb-3"><label class="cbt-form-label" for="advancedQuestion">Pertanyaan</label><textarea class="form-control" id="advancedQuestion" rows="4" maxlength="10000" required></textarea></div>
            <div class="mb-3" data-media-insert="advancedQuestion" data-api="<?= esc(base_url('manager/api/question-media'), 'attr') ?>"><label class="cbt-form-label">Sisipkan gambar pada pertanyaan</label><div class="d-flex gap-2 flex-wrap"><input type="file" class="form-control" accept="image/jpeg,image/png,image/webp" style="max-width:320px"><button type="button" class="btn btn-outline-primary">Unggah dan sisipkan gambar</button></div><small role="status"></small></div>
            <p class="small text-secondary">Gunakan **tebal** dan $rumus$ untuk format; teks Arab memakai arah otomatis. Gambar dapat dipindah ke stimulus, opsi, pasangan, atau rubrik. Untuk audio/video, ketik langsung <code>Audio: link Google Drive</code> atau <code>Video: link Google Drive</code> pada bagian konten yang membutuhkan media.</p>
            <div id="advancedSpecific"></div>
            <div class="d-flex gap-2"><button class="btn btn-cbt-primary" id="advancedSave" type="submit">Simpan Soal</button><button class="btn btn-outline-secondary" id="advancedCancel" type="button">Batal</button></div>
            <div class="cbt-inline-feedback mt-2" id="advancedFormFeedback" role="alert"></div>
        </form><div class="border-top mt-4 pt-3"><strong>Pratinjau</strong><div id="advancedPreview" class="border rounded p-3 mt-2"></div></div></div>
    </section>
</div>
<?= $this->endSection() ?>
<?= $this->section('pageScripts') ?>
<link rel="stylesheet" href="<?= base_url('assets/vendor/katex/katex.min.css') ?>">
<script src="<?= base_url('assets/vendor/katex/katex.min.js') ?>" defer></script>
<script src="<?= base_url('assets/js/question-renderer.js') ?>" defer></script>
<script src="<?= base_url('assets/js/question-media-upload.js') ?>" defer></script>
<script src="<?= base_url('assets/js/manager-question-advanced.js') ?>" defer></script>
<?= $this->endSection() ?>
