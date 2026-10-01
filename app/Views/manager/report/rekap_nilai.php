<?= $this->extend('manager/layouts/main') ?>
<?= $this->section('content') ?>
<section class="manager-page-header">
    <div>
        <div class="manager-page-kicker">Hasil & Laporan</div>
        <h1 class="manager-page-title">Rekap Nilai</h1>
        <p class="manager-page-description">Matrix nilai resmi peserta per mata pelajaran. Attempt lama yang sudah digantikan tidak ikut rekap.</p>
    </div>
</section>

<div id="rekapNilaiApp"
     data-api="<?= esc(base_url('manager/api/reports/rekap'), 'attr') ?>"
     data-options-api="<?= esc(base_url('manager/api/reports/rekap/options'), 'attr') ?>"
     data-export-xlsx="<?= esc(base_url('manager/hasil/export/rekap/xlsx'), 'attr') ?>"
     data-export-pdf="<?= esc(base_url('manager/hasil/export/rekap/pdf'), 'attr') ?>">
    <section class="manager-section-card mb-3">
        <div class="card-body">
            <div class="row g-2 align-items-end">
                <div class="col-lg-5 col-md-6">
                    <label class="cbt-form-label" for="rekapKegiatan">Kegiatan</label>
                    <select class="form-select" id="rekapKegiatan"><option value="">Pilih Kegiatan</option></select>
                </div>
                <div class="col-lg-3 col-md-6">
                    <label class="cbt-form-label" for="rekapRombel">Rombel</label>
                    <select class="form-select" id="rekapRombel"><option value="">Semua Rombel</option></select>
                </div>
                <div class="col-lg-4 d-flex flex-wrap gap-2">
                    <button class="btn btn-cbt-primary" id="rekapLoad" type="button"><i class="bi bi-grid-3x3-gap me-1"></i>Tampilkan</button>
                    <button class="btn btn-outline-success" id="rekapExcel" type="button" disabled><i class="bi bi-file-earmark-spreadsheet me-1"></i>Excel</button>
                    <button class="btn btn-outline-secondary" id="rekapPdf" type="button" disabled><i class="bi bi-printer me-1"></i>PDF</button>
                </div>
            </div>
            <div class="cbt-inline-feedback mt-2" id="rekapFeedback" role="status" aria-live="polite"></div>
        </div>
    </section>

    <section class="manager-section-card">
        <div class="card-body border-bottom d-flex flex-wrap gap-3 align-items-center">
            <strong id="rekapTitle">Pilih Kegiatan</strong>
            <span class="small text-secondary ms-lg-auto" id="rekapSummary">0 peserta · 0 mata pelajaran</span>
        </div>
        <div class="table-responsive">
            <table class="table manager-table align-middle mb-0">
                <thead id="rekapHead"><tr><th>No Peserta</th><th>Nama</th><th>Rombel</th><th>Rata-rata</th></tr></thead>
                <tbody id="rekapRows"><tr><td colspan="4" class="text-center text-secondary py-4">Pilih Kegiatan untuk menampilkan rekap.</td></tr></tbody>
            </table>
        </div>
    </section>
</div>
<?= $this->endSection() ?>

<?= $this->section('pageScripts') ?>
<script src="<?= base_url('assets/js/manager-rekap-nilai.js') ?>" defer></script>
<?= $this->endSection() ?>
