<?= $this->extend('manager/layouts/main') ?>
<?= $this->section('content') ?>
<section class="manager-page-header">
    <div>
        <div class="manager-page-kicker">Hasil & Laporan</div>
        <h1 class="manager-page-title">Hasil Ujian</h1>
        <p class="manager-page-description">Menampilkan hasil akademik yang saat ini dipilih sebagai hasil aktif. Status FINAL/BELUM FINAL ditampilkan terpisah; Attempt lama yang sudah digantikan Susulan tetap hanya menjadi histori.</p>
    </div>
</section>

<div id="hasilUjianApp"
     data-api="<?= esc(base_url('manager/api/results'), 'attr') ?>"
     data-options-api="<?= esc(base_url('manager/api/results/options'), 'attr') ?>"
     data-export-xlsx="<?= esc(base_url('manager/hasil/export/hasil/xlsx'), 'attr') ?>"
     data-export-pdf="<?= esc(base_url('manager/hasil/export/hasil/pdf'), 'attr') ?>">

    <section class="manager-section-card mb-3">
        <div class="card-body">
            <div class="row g-2">
                <div class="col-lg-3 col-md-6">
                    <label class="cbt-form-label" for="hasilKegiatan">Kegiatan</label>
                    <select class="form-select" id="hasilKegiatan"><option value="">Semua Kegiatan</option></select>
                </div>
                <div class="col-lg-3 col-md-6">
                    <label class="cbt-form-label" for="hasilJadwal">Jadwal</label>
                    <select class="form-select" id="hasilJadwal"><option value="">Semua Jadwal</option></select>
                </div>
                <div class="col-lg-2 col-md-4">
                    <label class="cbt-form-label" for="hasilMapel">Mata Pelajaran</label>
                    <select class="form-select" id="hasilMapel"><option value="">Semua Mapel</option></select>
                </div>
                <div class="col-lg-2 col-md-4">
                    <label class="cbt-form-label" for="hasilRombel">Rombel</label>
                    <select class="form-select" id="hasilRombel"><option value="">Semua Rombel</option></select>
                </div>
                <div class="col-lg-2 col-md-4">
                    <label class="cbt-form-label" for="hasilFinal">Status Hasil</label>
                    <select class="form-select" id="hasilFinal">
                        <option value="">Semua</option>
                        <option value="1">Final</option>
                        <option value="0">Belum Final</option>
                    </select>
                </div>
            </div>

            <div class="row g-2 mt-1 align-items-end">
                <div class="col-lg-5 col-md-8">
                    <label class="cbt-form-label" for="hasilSearch">Cari Peserta</label>
                    <input class="form-control" id="hasilSearch" type="search" placeholder="No peserta, nama, atau rombel">
                </div>
                <div class="col-lg-7 col-md-4 d-flex flex-wrap gap-2">
                    <button class="btn btn-cbt-primary" id="hasilApply" type="button">
                        <i class="bi bi-funnel me-1"></i>Terapkan
                    </button>
                    <button class="btn btn-outline-secondary" id="hasilReset" type="button">Reset</button>
                    <button class="btn btn-outline-success" id="hasilExcel" type="button"><i class="bi bi-file-earmark-spreadsheet me-1"></i>Excel</button>
                    <button class="btn btn-outline-secondary" id="hasilPdf" type="button"><i class="bi bi-printer me-1"></i>PDF</button>
                    <span class="small text-secondary ms-lg-auto align-self-center" id="hasilSummary">0 hasil</span>
                </div>
            </div>
            <div class="cbt-inline-feedback mt-2" id="hasilFeedback" role="status" aria-live="polite"></div>
        </div>
    </section>

    <section class="manager-section-card">
        <div class="table-responsive">
            <table class="table manager-table align-middle mb-0">
                <thead>
                <tr>
                    <th>No Peserta</th>
                    <th>Nama</th>
                    <th>Rombel</th>
                    <th>Mata Pelajaran</th>
                    <th>Nilai Klik</th>
                    <th>Nilai Ketik</th>
                    <th>Nilai Akhir</th>
                    <th>Status</th>
                    <th>Aksi</th>
                </tr>
                </thead>
                <tbody id="hasilRows">
                <tr><td colspan="9" class="text-center text-secondary py-4">Memuat data hasil...</td></tr>
                </tbody>
            </table>
        </div>
        <div class="card-body border-top d-flex flex-wrap justify-content-between align-items-center gap-2">
            <span class="small text-secondary" id="hasilPageInfo">Halaman 1</span>
            <div class="btn-group btn-group-sm" role="group" aria-label="Navigasi halaman">
                <button class="btn btn-outline-secondary" id="hasilPrev" type="button">Sebelumnya</button>
                <button class="btn btn-outline-secondary" id="hasilNext" type="button">Berikutnya</button>
            </div>
        </div>
    </section>
</div>

<div class="modal fade" id="hasilDetailModal" tabindex="-1" aria-labelledby="hasilDetailTitle" aria-hidden="true">
    <div class="modal-dialog modal-xl modal-dialog-scrollable">
        <div class="modal-content">
            <div class="modal-header">
                <div>
                    <div class="manager-page-kicker mb-1">Detail Hasil Resmi</div>
                    <h2 class="modal-title fs-5" id="hasilDetailTitle">Hasil Peserta</h2>
                </div>
                <button class="btn-close" type="button" data-bs-dismiss="modal" aria-label="Tutup"></button>
            </div>
            <div class="modal-body" id="hasilDetailBody"></div>
        </div>
    </div>
</div>
<?= $this->endSection() ?>

<?= $this->section('pageScripts') ?>
<script src="<?= base_url('assets/js/manager-results.js') ?>" defer></script>
<?= $this->endSection() ?>
