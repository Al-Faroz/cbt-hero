<?= $this->extend('manager/layouts/main') ?>
<?= $this->section('content') ?>
<section class="manager-page-header">
    <div>
        <div class="manager-page-kicker">Master Data</div>
        <h1 class="manager-page-title">Peserta</h1>
        <p class="manager-page-description">Kelola identitas dan akun login Peserta.</p>
    </div>
    <div class="d-flex flex-wrap gap-2">
        <a class="btn btn-outline-primary" href="<?= base_url('manager/master-data/peserta/import') ?>">Import Excel</a>
        <button type="button" class="btn btn-cbt-primary" id="pesertaAdd" data-bs-toggle="modal" data-bs-target="#pesertaModal">Tambah Peserta</button>
    </div>
</section>
<div id="pesertaApp" data-api-url="<?= esc(base_url('manager/api/peserta'), 'attr') ?>">
        <section class="manager-section-card">
            <div class="card-body">
                <div class="row g-2 align-items-end">
                    <div class="col-md-4">
                        <label class="cbt-form-label" for="pesertaSearch">Cari NISN / Nama / Username / Keterangan</label>
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
                <div class="d-flex flex-wrap align-items-center gap-2 mt-3">
                    <button type="button" class="btn btn-outline-primary btn-sm" id="pesertaSelectPage">Pilih Semua (Halaman Ini)</button>
                    <button type="button" class="btn btn-outline-secondary btn-sm" id="pesertaClearSelection">Batalkan Pilihan</button>
                    <span id="pesertaSelectedCount" class="small text-secondary" aria-live="polite">0 Peserta dipilih</span>
                    <button type="button" class="btn btn-outline-primary btn-sm" id="pesertaBulkUsername">Buat Username</button>
                    <button type="button" class="btn btn-outline-warning btn-sm" id="pesertaBulkRegenerate">Buat Ulang Username</button>
                    <button type="button" class="btn btn-outline-warning btn-sm" id="pesertaBulkPassword">Reset Password</button>
                </div>
                <div id="pesertaListFeedback" class="cbt-inline-feedback" role="status" aria-live="polite"></div>
            </div>
            <div class="table-responsive">
                <table class="table manager-table mb-0">
                    <thead><tr><th><input type="checkbox" id="pesertaSelectVisible" aria-label="Pilih semua Peserta pada halaman ini"></th><th>NISN</th><th>Nama</th><th>Username</th><th>Password</th><th>JK</th><th>Rombel</th><th>Status</th><th>Keterangan</th><th>Aksi</th></tr></thead>
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
<div class="modal fade" id="pesertaModal" tabindex="-1" aria-labelledby="pesertaFormTitle" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered"><div class="modal-content">
            <div class="modal-header"><h2 class="modal-title fs-5" id="pesertaFormTitle">Tambah Peserta</h2><button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Tutup"></button></div>
            <div class="modal-body">
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
                    <div class="d-flex gap-2 justify-content-end">
                        <button class="btn btn-cbt-primary" type="submit" id="pesertaSubmit">Simpan</button>
                        <button class="btn btn-outline-secondary" type="button" data-bs-dismiss="modal">Batal</button>
                    </div>
                    <div id="pesertaFormFeedback" class="cbt-inline-feedback" role="alert"></div>
                </form>
            </div>
    </div></div>
</div>
<div class="modal fade" id="pesertaAccountModal" tabindex="-1" aria-labelledby="pesertaAccountTitle" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered"><div class="modal-content">
        <div class="modal-header"><h2 class="modal-title fs-5" id="pesertaAccountTitle">Account Login</h2><button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Tutup"></button></div>
        <div class="modal-body">
            <p id="pesertaAccountName" class="fw-semibold"></p>
            <label class="cbt-form-label" for="pesertaAccountUsername">Username</label>
            <div class="input-group mb-3"><input class="form-control text-uppercase" id="pesertaAccountUsername" maxlength="64" autocomplete="off"><button class="btn btn-outline-primary" type="button" id="pesertaSaveUsername">Simpan</button></div>
            <button class="btn btn-outline-primary btn-sm mb-3" type="button" id="pesertaGenerateUsername">Generate Username</button>
            <label class="cbt-form-label" for="pesertaAccountPassword">Password manual (opsional)</label>
            <input class="form-control mb-2" id="pesertaAccountPassword" type="password" maxlength="64" autocomplete="new-password" placeholder="Kosong = generate otomatis">
            <button class="btn btn-outline-warning btn-sm" type="button" id="pesertaResetPassword">Generate / Reset Password</button>
            <div id="pesertaAccountFeedback" class="cbt-inline-feedback" role="alert"></div>
        </div>
    </div></div>
</div>
<?= $this->endSection() ?>
<?= $this->section('pageScripts') ?>
<script src="<?= base_url('assets/js/manager-peserta.js') ?>" defer></script>
<?= $this->endSection() ?>
