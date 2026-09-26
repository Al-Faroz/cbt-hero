<?= $this->extend('manager/layouts/main') ?>
<?= $this->section('content') ?>
<section class="manager-page-header">
    <div>
        <div class="manager-page-kicker">Master Data</div>
        <h1 class="manager-page-title">Rombel</h1>
        <p class="manager-page-description">Kelola tingkat dan kode rombel. Nama tampil dibuat otomatis, misalnya 7-A.</p>
    </div>
</section>
<div class="row g-3 align-items-start" id="rombelApp" data-api-url="<?= esc(base_url('manager/api/rombel'), 'attr') ?>">
    <div class="col-xl-8">
        <section class="manager-section-card">
            <div class="card-body">
                <div class="row g-2 align-items-end">
                    <div class="col-sm-5">
                        <label class="cbt-form-label" for="rombelSearch">Cari Rombel</label>
                        <input class="form-control" id="rombelSearch" placeholder="Contoh: 7-A">
                    </div>
                    <div class="col-6 col-sm-2">
                        <label class="cbt-form-label" for="rombelTingkatFilter">Tingkat</label>
                        <select class="form-select" id="rombelTingkatFilter"><option value="">Semua</option><option>7</option><option>8</option><option>9</option></select>
                    </div>
                    <div class="col-6 col-sm-3">
                        <label class="cbt-form-label" for="rombelStatusFilter">Status</label>
                        <select class="form-select" id="rombelStatusFilter"><option value="">Semua</option><option value="ACTIVE">Aktif</option><option value="INACTIVE">Nonaktif</option></select>
                    </div>
                    <div class="col-6 col-sm-2">
                        <label class="cbt-form-label" for="rombelPageSize">Per halaman</label>
                        <select class="form-select" id="rombelPageSize"><option>25</option><option>50</option><option>100</option></select>
                    </div>
                </div>
                <button type="button" class="btn btn-outline-secondary btn-sm mt-3" id="rombelResetFilters">Reset Filter</button>
                <div id="rombelListFeedback" class="cbt-inline-feedback" role="status" aria-live="polite"></div>
            </div>
            <div class="table-responsive">
                <table class="table manager-table mb-0">
                    <thead><tr><th>Rombel</th><th>Tingkat</th><th>Kode</th><th>Status</th><th>Diperbarui</th><th>Aksi</th></tr></thead>
                    <tbody id="rombelRows"></tbody>
                </table>
            </div>
            <div class="card-body d-flex flex-wrap align-items-center justify-content-between gap-2">
                <span id="rombelCount" class="small text-secondary"></span>
                <div class="d-flex align-items-center gap-2">
                    <button type="button" class="btn btn-outline-secondary btn-sm" id="rombelPrevious">Sebelumnya</button>
                    <span id="rombelPageInfo" class="small"></span>
                    <button type="button" class="btn btn-outline-secondary btn-sm" id="rombelNext">Berikutnya</button>
                </div>
            </div>
        </section>
    </div>
    <div class="col-xl-4">
        <section class="manager-section-card">
            <div class="card-header fw-bold" id="rombelFormTitle">Tambah Rombel</div>
            <div class="card-body">
                <form id="rombelForm">
                    <div class="mb-3">
                        <label class="cbt-form-label" for="rombelTingkat">Tingkat</label>
                        <select class="form-select" id="rombelTingkat" required><option value="">Pilih tingkat</option><option>7</option><option>8</option><option>9</option></select>
                    </div>
                    <div class="mb-3">
                        <label class="cbt-form-label" for="rombelKode">Kode Rombel</label>
                        <input class="form-control text-uppercase" id="rombelKode" maxlength="20" pattern="[A-Za-z0-9]{1,20}" placeholder="A" required>
                        <div class="form-text">Huruf/angka tanpa spasi; nama tampil dibuat otomatis.</div>
                    </div>
                    <div class="d-flex gap-2">
                        <button class="btn btn-cbt-primary" type="submit" id="rombelSubmit">Simpan</button>
                        <button class="btn btn-outline-secondary" type="button" id="rombelCancel" hidden>Batal Edit</button>
                    </div>
                    <div id="rombelFormFeedback" class="cbt-inline-feedback" role="alert"></div>
                </form>
            </div>
        </section>
    </div>
</div>
<?= $this->endSection() ?>
<?= $this->section('pageScripts') ?>
<script src="<?= base_url('assets/js/manager-rombel.js') ?>" defer></script>
<?= $this->endSection() ?>
