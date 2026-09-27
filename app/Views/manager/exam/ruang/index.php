<?= $this->extend('manager/layouts/main') ?>
<?= $this->section('content') ?>
<section class="manager-page-header">
    <div>
        <div class="manager-page-kicker">Master Ujian</div>
        <h1 class="manager-page-title">Ruang Ujian</h1>
        <p class="manager-page-description">Ruang dapat digunakan kembali pada beberapa Kegiatan. Penempatan peserta dilakukan dari halaman Peserta Ujian.</p>
    </div>
    <button type="button" class="btn btn-cbt-primary" id="ruangAdd">Tambah Ruang</button>
</section>
<section class="manager-section-card" id="ruangApp" data-api="<?= esc(base_url('manager/api/ruang'), 'attr') ?>">
    <div class="card-body">
        <div class="row g-2 align-items-end">
            <div class="col-md-5"><label class="cbt-form-label" for="ruangSearch">Cari Kode / Nama</label><input class="form-control" id="ruangSearch" autocomplete="off"></div>
            <div class="col-6 col-md-3"><label class="cbt-form-label" for="ruangStatusFilter">Status</label><select class="form-select" id="ruangStatusFilter"><option value="">Semua</option><option value="ACTIVE">Aktif</option><option value="INACTIVE">Nonaktif</option></select></div>
            <div class="col-6 col-md-2"><label class="cbt-form-label" for="ruangPageSize">Per halaman</label><select class="form-select" id="ruangPageSize"><option>25</option><option>50</option><option>100</option></select></div>
            <div class="col-md-2"><button class="btn btn-outline-secondary w-100" type="button" id="ruangReset">Reset Filter</button></div>
        </div>
        <div class="cbt-inline-feedback mt-2" id="ruangFeedback" role="status" aria-live="polite"></div>
    </div>
    <div class="table-responsive"><table class="table manager-table mb-0">
        <thead><tr><th>Kode</th><th>Nama Ruang</th><th>Status</th><th>Diperbarui</th><th>Aksi</th></tr></thead>
        <tbody id="ruangRows"></tbody>
    </table></div>
    <div class="card-body d-flex flex-wrap justify-content-between align-items-center gap-2">
        <span class="small text-secondary" id="ruangCount"></span>
        <div class="d-flex gap-2 align-items-center"><button class="btn btn-outline-secondary btn-sm" type="button" id="ruangPrevious">Sebelumnya</button><span class="small" id="ruangPageInfo"></span><button class="btn btn-outline-secondary btn-sm" type="button" id="ruangNext">Berikutnya</button></div>
    </div>
</section>
<div class="modal fade" id="ruangModal" tabindex="-1" aria-labelledby="ruangModalTitle" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered"><div class="modal-content">
        <div class="modal-header"><h2 class="modal-title fs-5" id="ruangModalTitle">Tambah Ruang</h2><button class="btn-close" type="button" data-bs-dismiss="modal" aria-label="Tutup"></button></div>
        <div class="modal-body"><form id="ruangForm">
            <div class="mb-3"><label class="cbt-form-label" for="ruangKode">Kode Ruang</label><input class="form-control text-uppercase" id="ruangKode" maxlength="50" pattern="[A-Za-z0-9][A-Za-z0-9._-]{0,49}" placeholder="LAB-1" required></div>
            <div class="mb-3"><label class="cbt-form-label" for="ruangNama">Nama Ruang</label><input class="form-control" id="ruangNama" maxlength="150" placeholder="Laboratorium Komputer 1" required></div>
            <div class="d-flex justify-content-end gap-2"><button class="btn btn-outline-secondary" type="button" data-bs-dismiss="modal">Batal</button><button class="btn btn-cbt-primary" type="submit" id="ruangSubmit">Simpan</button></div>
            <div class="cbt-inline-feedback mt-2" id="ruangFormFeedback" role="alert"></div>
        </form></div>
    </div></div>
</div>
<?= $this->endSection() ?>
<?= $this->section('pageScripts') ?>
<script src="<?= base_url('assets/js/manager-ruang.js') ?>" defer></script>
<?= $this->endSection() ?>
