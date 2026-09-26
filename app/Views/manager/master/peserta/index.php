<?= $this->extend('manager/layouts/main') ?>
<?= $this->section('content') ?>
<section class="manager-page-header">
    <div>
        <div class="manager-page-kicker">Master Data</div>
        <h1 class="manager-page-title">Peserta</h1>
        <p class="manager-page-description">Data identitas Peserta. Account Login dan impor disiapkan pada langkah berikutnya.</p>
    </div>
</section>
<div class="row g-3 align-items-start" id="pesertaApp" data-api-url="<?= esc(base_url('manager/api/peserta'), 'attr') ?>">
    <div class="col-xl-8">
        <section class="manager-section-card">
            <div class="card-body">
                <div class="row g-2 align-items-end">
                    <div class="col-md-4">
                        <label class="cbt-form-label" for="pesertaSearch">Cari NISN / Nama / Keterangan</label>
                        <input class="form-control" id="pesertaSearch" autocomplete="off">
                    </div>
                    <div class="col-6 col-md-3">
                        <label class="cbt-form-label" for="pesertaRombelFilter">Rombel</label>
                        <select class="form-select" id="pesertaRombelFilter"><option value="">Semua</option></select>
                    </div>
                    <div class="col-6 col-md-3">
                        <label class="cbt-form-label" for="pesertaStatusFilter">Status</label>
                        <select class="form-select" id="pesertaStatusFilter"><option value="">Semua</option><option value="ACTIVE">Aktif</option><option value="INACTIVE">Nonaktif</option></select>
                    </div>
                    <div class="col-6 col-md-2">
                        <label class="cbt-form-label" for="pesertaPageSize">Per halaman</label>
                        <select class="form-select" id="pesertaPageSize"><option>50</option><option>25</option><option>100</option></select>
                    </div>
                </div>
                <button type="button" class="btn btn-outline-secondary btn-sm mt-3" id="pesertaResetFilters">Reset Filter</button>
                <div id="pesertaListFeedback" class="cbt-inline-feedback" role="status" aria-live="polite"></div>
            </div>
            <div class="table-responsive">
                <table class="table manager-table mb-0">
                    <thead><tr><th>NISN</th><th>Nama</th><th>JK</th><th>Rombel</th><th>Status</th><th>Keterangan</th><th>Aksi</th></tr></thead>
                    <tbody id="pesertaRows"></tbody>
                </table>
            </div>
            <div class="card-body d-flex flex-wrap justify-content-between align-items-center gap-2">
                <span id="pesertaCount" class="small text-secondary"></span>
                <div class="d-flex align-items-center gap-2">
                    <button type="button" class="btn btn-outline-secondary btn-sm" id="pesertaPrevious">Sebelumnya</button>
                    <span id="pesertaPageInfo" class="small"></span>
                    <button type="button" class="btn btn-outline-secondary btn-sm" id="pesertaNext">Berikutnya</button>
                </div>
            </div>
        </section>
    </div>
    <div class="col-xl-4">
        <section class="manager-section-card">
            <div class="card-header fw-bold" id="pesertaFormTitle">Tambah Peserta</div>
            <div class="card-body">
                <form id="pesertaForm">
                    <div class="mb-3">
                        <label class="cbt-form-label" for="pesertaNisn">NISN</label>
                        <input class="form-control" id="pesertaNisn" inputmode="numeric" maxlength="30" pattern="[0-9]{1,30}" required>
                    </div>
                    <div class="mb-3">
                        <label class="cbt-form-label" for="pesertaNama">Nama</label>
                        <input class="form-control" id="pesertaNama" maxlength="180" required>
                    </div>
                    <div class="row g-2 mb-3">
                        <div class="col-5">
                            <label class="cbt-form-label" for="pesertaJk">Jenis Kelamin</label>
                            <select class="form-select" id="pesertaJk" required><option value="">Pilih</option><option value="L">Laki-laki</option><option value="P">Perempuan</option></select>
                        </div>
                        <div class="col-7">
                            <label class="cbt-form-label" for="pesertaRombel">Rombel aktif</label>
                            <select class="form-select" id="pesertaRombel" required><option value="">Pilih Rombel</option></select>
                        </div>
                    </div>
                    <div class="mb-3">
                        <label class="cbt-form-label" for="pesertaKeterangan">Keterangan (opsional)</label>
                        <input class="form-control" id="pesertaKeterangan" maxlength="255">
                    </div>
                    <div class="d-flex gap-2">
                        <button class="btn btn-cbt-primary" type="submit" id="pesertaSubmit">Simpan</button>
                        <button class="btn btn-outline-secondary" type="button" id="pesertaCancel" hidden>Batal Edit</button>
                    </div>
                    <div id="pesertaFormFeedback" class="cbt-inline-feedback" role="alert"></div>
                </form>
            </div>
        </section>
    </div>
</div>
<?= $this->endSection() ?>
<?= $this->section('pageScripts') ?>
<script src="<?= base_url('assets/js/manager-peserta.js') ?>" defer></script>
<?= $this->endSection() ?>
