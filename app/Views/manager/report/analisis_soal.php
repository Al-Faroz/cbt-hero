<?= $this->extend('manager/layouts/main') ?>
<?= $this->section('content') ?>
<section class="manager-page-header">
    <div>
        <div class="manager-page-kicker">Hasil & Laporan</div>
        <h1 class="manager-page-title">Analisis Soal</h1>
        <p class="manager-page-description">Analisis item dari hasil resmi. Nilai agregat dihitung langsung dari snapshot resmi agar tidak memakai Attempt historis yang sudah digantikan.</p>
    </div>
</section>

<div id="analisisSoalApp"
     data-api="<?= esc(base_url('manager/api/reports/analysis'), 'attr') ?>"
     data-options-api="<?= esc(base_url('manager/api/reports/analysis/options'), 'attr') ?>"
     data-export-xlsx="<?= esc(base_url('manager/hasil/export/analisis/xlsx'), 'attr') ?>"
     data-export-pdf="<?= esc(base_url('manager/hasil/export/analisis/pdf'), 'attr') ?>">
    <section class="manager-section-card mb-3">
        <div class="card-body">
            <div class="row g-2 align-items-end">
                <div class="col-lg-6">
                    <label class="cbt-form-label" for="analysisJadwal">Jadwal</label>
                    <select class="form-select" id="analysisJadwal"><option value="">Pilih Jadwal</option></select>
                </div>
                <div class="col-lg-2 col-md-4">
                    <label class="cbt-form-label" for="analysisType">Tipe Soal</label>
                    <select class="form-select" id="analysisType"><option value="">Semua Tipe</option></select>
                </div>
                <div class="col-lg-4 d-flex flex-wrap gap-2">
                    <button class="btn btn-cbt-primary" id="analysisLoad" type="button"><i class="bi bi-bar-chart-line me-1"></i>Analisis</button>
                    <button class="btn btn-outline-success" id="analysisExcel" type="button" disabled>Excel</button>
                    <button class="btn btn-outline-secondary" id="analysisPdf" type="button" disabled>PDF</button>
                </div>
            </div>
            <div class="cbt-inline-feedback mt-2" id="analysisFeedback" role="status" aria-live="polite"></div>
        </div>
    </section>

    <section class="manager-section-card">
        <div class="card-body border-bottom d-flex flex-wrap gap-3 align-items-center">
            <strong id="analysisTitle">Pilih Jadwal</strong>
            <span class="small text-secondary ms-lg-auto" id="analysisSummary">0 soal · 0 peserta</span>
        </div>
        <div class="table-responsive">
            <table class="table manager-table align-middle mb-0">
                <thead>
                <tr>
                    <th>Kode Soal</th><th>Tipe</th><th>Peserta</th><th>Rata-rata</th>
                    <th>Indeks Skor</th><th>Nilai Penuh</th><th>Skor Nol</th><th>Void</th><th>Aksi</th>
                </tr>
                </thead>
                <tbody id="analysisRows"><tr><td colspan="9" class="text-center text-secondary py-4">Pilih Jadwal untuk menampilkan analisis.</td></tr></tbody>
            </table>
        </div>
    </section>
</div>

<div class="modal fade" id="analysisDetailModal" tabindex="-1" aria-labelledby="analysisDetailTitle" aria-hidden="true">
    <div class="modal-dialog modal-xl modal-dialog-scrollable">
        <div class="modal-content">
            <div class="modal-header">
                <div><div class="manager-page-kicker mb-1">Detail Item</div><h2 class="modal-title fs-5" id="analysisDetailTitle">Analisis Soal</h2></div>
                <button class="btn-close" type="button" data-bs-dismiss="modal" aria-label="Tutup"></button>
            </div>
            <div class="modal-body" id="analysisDetailBody"></div>
        </div>
    </div>
</div>
<?= $this->endSection() ?>

<?= $this->section('pageScripts') ?>
<script src="<?= base_url('assets/js/manager-analisis-soal.js') ?>" defer></script>
<?= $this->endSection() ?>
