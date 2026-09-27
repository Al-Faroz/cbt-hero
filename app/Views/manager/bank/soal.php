<?= $this->extend('manager/layouts/main') ?>
<?= $this->section('content') ?>
<section class="manager-page-header">
    <div><div class="manager-page-kicker">Master Ujian / Bank Soal</div><h1 class="manager-page-title">Soal Pilihan Ganda</h1>
        <p class="manager-page-description" id="questionContext">Memuat Bank Soal...</p></div>
    <div class="d-flex gap-2 flex-wrap"><a class="btn btn-outline-primary" href="<?= base_url('manager/master-ujian/bank-soal/' . $bankId . '/soal-lanjutan') ?>">Tipe Soal Lain</a><a class="btn btn-outline-secondary" href="<?= base_url('manager/master-ujian/bank-soal/' . $bankId . '/komposisi') ?>">Komposisi</a>
        <a class="btn btn-outline-secondary" href="<?= base_url('manager/master-ujian/bank-soal') ?>">Kembali ke Bank</a></div>
</section>
<div id="questionApp" data-api="<?= esc(base_url('manager/api/bank-soal/' . $bankId . '/soal'), 'attr') ?>">
    <section class="manager-section-card mb-3">
        <div class="card-header d-flex justify-content-between align-items-center gap-2"><strong>Daftar Soal PG</strong><button class="btn btn-cbt-primary btn-sm" id="questionAdd" type="button" disabled>Tambah Soal PG</button></div>
        <div class="card-body"><p class="small text-secondary mb-0">Teks, **tebal**, rumus $...$, Arab/RTL, gambar dan audio dapat dipakai pada pertanyaan atau opsi.</p>
            <div class="cbt-inline-feedback mt-2" id="questionFeedback" role="status" aria-live="polite"></div></div>
        <div class="table-responsive"><table class="table manager-table mb-0"><thead><tr><th>Urutan</th><th>Pertanyaan</th><th>Poin</th><th>Revisi</th><th>Aksi</th></tr></thead><tbody id="questionRows"></tbody></table></div>
        <div class="card-body d-flex flex-wrap justify-content-between align-items-center gap-2"><span id="questionCount" class="small text-secondary"></span><div class="d-flex align-items-center gap-2"><select id="questionPageSize" class="form-select form-select-sm" aria-label="Soal per halaman"><option>25</option><option>50</option><option>100</option></select><button id="questionPrevious" type="button" class="btn btn-outline-secondary btn-sm">Sebelumnya</button><span id="questionPageInfo"></span><button id="questionNext" type="button" class="btn btn-outline-secondary btn-sm">Berikutnya</button></div></div>
    </section>
    <section class="manager-section-card" id="questionEditor" hidden>
        <div class="card-header fw-bold" id="questionEditorTitle">Tambah Soal PG</div>
        <div class="card-body"><form id="questionForm">
            <div class="mb-3"><label class="cbt-form-label" for="questionText">Pertanyaan</label><textarea id="questionText" class="form-control" rows="5" maxlength="10000" required></textarea></div>
            <div class="mb-3" data-media-insert="questionText" data-api="<?= esc(base_url('manager/api/question-media'), 'attr') ?>"><label class="cbt-form-label">Sisipkan media pada pertanyaan</label><div class="d-flex gap-2 flex-wrap"><input type="file" class="form-control" accept="image/jpeg,image/png,image/webp,audio/mpeg,audio/ogg,audio/mp4" style="max-width:320px"><button type="button" class="btn btn-outline-primary">Unggah dan sisipkan</button><button type="button" class="btn btn-outline-secondary" data-video>Video HTTPS</button></div><small role="status"></small></div>
            <div class="mb-3"><div class="d-flex justify-content-between align-items-center gap-2"><strong>Opsi (2–6)</strong><button id="questionAddOption" class="btn btn-outline-primary btn-sm" type="button">Tambah Opsi</button></div>
                <div class="small text-secondary mb-2">Tandai tepat satu opsi sebagai kunci jawaban.</div><div id="questionOptions" class="d-grid gap-2"></div></div>
            <div class="mb-3" style="max-width:180px"><label class="cbt-form-label" for="questionPoint">Poin maksimal</label><input id="questionPoint" class="form-control" type="number" min="0.0001" max="1000" step="0.0001" value="1" required></div>
            <div class="d-flex gap-2"><button class="btn btn-cbt-primary" id="questionSave" type="submit">Simpan Soal</button><button class="btn btn-outline-secondary" id="questionCancel" type="button">Batal</button></div>
            <div class="cbt-inline-feedback mt-2" id="questionFormFeedback" role="alert"></div>
        </form>
        <div class="border-top mt-4 pt-3"><strong>Pratinjau Soal PG</strong><div id="questionPreview" class="mt-2 p-3 border rounded"></div></div>
        </div>
    </section>
</div>
<?= $this->endSection() ?>
<?= $this->section('pageScripts') ?>
<link rel="stylesheet" href="<?= base_url('assets/vendor/katex/katex.min.css') ?>">
<script src="<?= base_url('assets/vendor/katex/katex.min.js') ?>" defer></script>
<script src="<?= base_url('assets/js/question-renderer.js') ?>" defer></script>
<script src="<?= base_url('assets/js/question-media-upload.js') ?>" defer></script>
<script src="<?= base_url('assets/js/manager-question-pg.js') ?>" defer></script>
<?= $this->endSection() ?>
