<?= $this->extend('manager/layouts/main') ?>
<?= $this->section('content') ?>
<section class="manager-page-header">
    <div>
        <div class="manager-page-kicker">Master Data</div>
        <h1 class="manager-page-title">Mata Pelajaran</h1>
        <p class="manager-page-description">Kelola kode, nama, singkatan, dan urutan Mata Pelajaran untuk Bank Soal.</p>
    </div>
    <button type="button" class="btn btn-cbt-primary" id="mapelAdd" data-bs-toggle="modal" data-bs-target="#mapelModal">Tambah Mata Pelajaran</button>
</section>
<div id="mapelApp" data-api-url="<?= esc(base_url('manager/api/mapel'), 'attr') ?>">
    <section class="manager-section-card">
        <div class="card-body">
            <div class="row g-2 align-items-end">
                <div class="col-sm-5">
                    <label class="cbt-form-label" for="mapelSearch">Cari Kode / Nama / Singkatan</label>
                    <input class="form-control" id="mapelSearch" autocomplete="off">
                </div>
                <div class="col-6 col-sm-3">
                    <label class="cbt-form-label" for="mapelStatusFilter">Status</label>
                    <select class="form-select" id="mapelStatusFilter"><option value="">Semua</option><option value="ACTIVE">Aktif</option><option value="INACTIVE">Nonaktif</option></select>
                </div>
                <div class="col-6 col-sm-2">
                    <label class="cbt-form-label" for="mapelSort">Urutkan</label>
                    <select class="form-select" id="mapelSort"><option value="urutan">Urutan</option><option value="kode">Kode</option><option value="nama">Nama</option></select>
                </div>
                <div class="col-6 col-sm-2">
                    <label class="cbt-form-label" for="mapelPageSize">Per halaman</label>
                    <select class="form-select" id="mapelPageSize"><option>25</option><option>50</option><option>100</option></select>
                </div>
            </div>
            <button type="button" class="btn btn-outline-secondary btn-sm mt-3" id="mapelResetFilters">Reset Filter</button>
            <div id="mapelListFeedback" class="cbt-inline-feedback" role="status" aria-live="polite"></div>
        </div>
        <div class="table-responsive">
            <table class="table manager-table mb-0">
                <thead><tr><th>Urutan</th><th>Kode</th><th>Nama Mata Pelajaran</th><th>Singkatan</th><th>Status</th><th>Aksi</th></tr></thead>
                <tbody id="mapelRows"></tbody>
            </table>
        </div>
        <div class="card-body d-flex flex-wrap align-items-center justify-content-between gap-2">
            <span id="mapelCount" class="small text-secondary"></span>
            <div class="d-flex align-items-center gap-2">
                <button type="button" class="btn btn-outline-secondary btn-sm" id="mapelPrevious">Sebelumnya</button>
                <span id="mapelPageInfo" class="small"></span>
                <button type="button" class="btn btn-outline-secondary btn-sm" id="mapelNext">Berikutnya</button>
            </div>
        </div>
    </section>
</div>
<div class="modal fade" id="mapelModal" tabindex="-1" aria-labelledby="mapelFormTitle" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered"><div class="modal-content">
        <div class="modal-header"><h2 class="modal-title fs-5" id="mapelFormTitle">Tambah Mata Pelajaran</h2><button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Tutup"></button></div>
        <div class="modal-body">
            <form id="mapelForm">
                <div class="mb-3">
                    <label class="cbt-form-label" for="mapelKode">Kode Mapel</label>
                    <input class="form-control text-uppercase" id="mapelKode" maxlength="50" pattern="[A-Za-z0-9_-]{1,50}" required>
                    <div class="form-text">Huruf, angka, minus, atau garis bawah tanpa spasi.</div>
                </div>
                <div class="mb-3">
                    <label class="cbt-form-label" for="mapelNama">Nama Mata Pelajaran</label>
                    <input class="form-control" id="mapelNama" maxlength="150" required>
                </div>
                <div class="row g-2 mb-3">
                    <div class="col-7">
                        <label class="cbt-form-label" for="mapelSingkatan">Singkatan (opsional)</label>
                        <input class="form-control text-uppercase" id="mapelSingkatan" maxlength="50">
                    </div>
                    <div class="col-5">
                        <label class="cbt-form-label" for="mapelUrutan">Urutan</label>
                        <input class="form-control" type="number" id="mapelUrutan" min="0" max="99999" step="1" value="0" required>
                    </div>
                </div>
                <div class="d-flex justify-content-end gap-2">
                    <button class="btn btn-cbt-primary" type="submit" id="mapelSubmit">Simpan</button>
                    <button class="btn btn-outline-secondary" type="button" data-bs-dismiss="modal">Batal</button>
                </div>
                <div id="mapelFormFeedback" class="cbt-inline-feedback" role="alert"></div>
            </form>
        </div>
    </div></div>
</div>
<?= $this->endSection() ?>
<?= $this->section('pageScripts') ?>
<script src="<?= base_url('assets/js/manager-mapel.js') ?>" defer></script>
<?= $this->endSection() ?>
