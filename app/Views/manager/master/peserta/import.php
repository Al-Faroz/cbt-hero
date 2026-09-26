<?= $this->extend('manager/layouts/main') ?>
<?= $this->section('content') ?>
<section class="manager-page-header">
    <div>
        <div class="manager-page-kicker">Master Data</div>
        <h1 class="manager-page-title">Import Peserta</h1>
        <p class="manager-page-description">Upload → Parse → Validasi → Preview / Perbaiki → Commit.</p>
    </div>
    <a class="btn btn-outline-secondary" href="<?= base_url('manager/master-data/peserta') ?>">Kembali ke Peserta</a>
</section>
<div id="pesertaImportApp" data-api="<?= esc(base_url('manager/api/imports'), 'attr') ?>">
    <section class="manager-section-card mb-3">
        <div class="card-header fw-bold">1. Template dan Upload</div>
        <div class="card-body">
            <a class="btn btn-outline-primary btn-sm mb-3" href="<?= base_url('manager/import-template/peserta.xlsx') ?>">Unduh Template Excel</a>
            <form id="importUploadForm" class="row g-2 align-items-end">
                <div class="col-md-8">
                    <label class="cbt-form-label" for="importFile">Berkas XLSX (maksimal 5 MB)</label>
                    <input class="form-control" type="file" accept=".xlsx" id="importFile" required>
                </div>
                <div class="col-md-4"><button class="btn btn-cbt-primary w-100" type="submit">Upload</button></div>
            </form>
            <div id="importFeedback" class="cbt-inline-feedback" role="alert"></div>
        </div>
    </section>
    <section class="manager-section-card mb-3" id="importJobCard" hidden>
        <div class="card-header fw-bold">2. Staging dan Validasi</div>
        <div class="card-body">
            <p id="importJobSummary" class="mb-2"></p>
            <div class="d-flex flex-wrap gap-2">
                <button type="button" class="btn btn-outline-primary btn-sm" id="importParse">Parse</button>
                <button type="button" class="btn btn-outline-primary btn-sm" id="importValidate">Validasi / Validasi Ulang</button>
                <button type="button" class="btn btn-cbt-primary btn-sm" id="importCommit">Commit Peserta</button>
                <select class="form-select form-select-sm w-auto" id="importStatusFilter" aria-label="Filter status baris">
                    <option value="">Semua baris</option><option value="INVALID">Invalid</option>
                    <option value="VALID">Valid</option><option value="EXCLUDED">Excluded</option>
                </select>
            </div>
            <div id="importActionFeedback" class="cbt-inline-feedback" role="alert"></div>
        </div>
        <div class="table-responsive">
            <table class="table manager-table mb-0">
                <thead><tr><th>Baris</th><th>NISN</th><th>Nama</th><th>JK</th><th>Rombel</th><th>Username</th><th>Password</th><th>Status / Galat</th><th>Aksi</th></tr></thead>
                <tbody id="importRows"></tbody>
            </table>
        </div>
        <div class="card-body d-flex gap-2 align-items-center">
            <button type="button" class="btn btn-outline-secondary btn-sm" id="importPrevious">Sebelumnya</button>
            <span id="importPageInfo" class="small"></span>
            <button type="button" class="btn btn-outline-secondary btn-sm" id="importNext">Berikutnya</button>
        </div>
    </section>
    <section class="manager-section-card" id="importEditCard" hidden>
        <div class="card-header fw-bold" id="importEditTitle">Perbaiki Baris</div>
        <div class="card-body">
            <form id="importEditForm" class="row g-2">
                <div class="col-md-3"><label class="cbt-form-label" for="importEditNisn">NISN</label><input class="form-control" id="importEditNisn" required></div>
                <div class="col-md-5"><label class="cbt-form-label" for="importEditNama">Nama</label><input class="form-control" id="importEditNama" required></div>
                <div class="col-md-2"><label class="cbt-form-label" for="importEditJk">JK</label><select class="form-select" id="importEditJk"><option value="L">L</option><option value="P">P</option></select></div>
                <div class="col-md-2"><label class="cbt-form-label" for="importEditRombel">Rombel</label><input class="form-control" id="importEditRombel" required></div>
                <div class="col-md-4"><label class="cbt-form-label" for="importEditKeterangan">Keterangan</label><input class="form-control" id="importEditKeterangan"></div>
                <div class="col-md-4"><label class="cbt-form-label" for="importEditUsername">Username</label><input class="form-control" id="importEditUsername"></div>
                <div class="col-md-4"><label class="cbt-form-label" for="importEditPassword">Password baru (opsional)</label><input class="form-control" id="importEditPassword" type="password" autocomplete="new-password" placeholder="Kosong: pertahankan"></div>
                <div class="col-12"><label><input type="checkbox" id="importEditClearPassword"> Hapus Password dari baris ini</label></div>
                <div class="col-12 d-flex gap-2">
                    <button type="submit" class="btn btn-cbt-primary btn-sm">Simpan Perbaikan</button>
                    <button type="button" class="btn btn-outline-secondary btn-sm" id="importCancelEdit">Batal</button>
                </div>
            </form>
        </div>
    </section>
</div>
<?= $this->endSection() ?>
<?= $this->section('pageScripts') ?>
<script src="<?= base_url('assets/js/manager-peserta-import.js') ?>" defer></script>
<?= $this->endSection() ?>
