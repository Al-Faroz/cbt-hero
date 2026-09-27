<?= $this->extend('manager/layouts/main') ?>
<?= $this->section('content') ?>
<section class="manager-page-header">
    <div>
        <div class="manager-page-kicker">Master Ujian</div>
        <h1 class="manager-page-title">Kegiatan Ujian</h1>
        <p class="manager-page-description">Siapkan wadah ujian akademik atau psikologis sebelum menambahkan peserta, bank, dan jadwal.</p>
    </div>
    <button class="btn btn-cbt-primary" type="button" id="kegiatanAdd">Tambah Kegiatan</button>
</section>
<div id="kegiatanApp" data-api="<?= esc(base_url('manager/api/kegiatan'), 'attr') ?>" data-ui-base="<?= esc(base_url('manager/master-ujian/kegiatan'), 'attr') ?>">
    <section class="manager-section-card">
        <div class="card-body">
            <div class="row g-2 align-items-end">
                <div class="col-md-5">
                    <label class="cbt-form-label" for="kegiatanSearch">Cari Nama / Tahun Pelajaran</label>
                    <input class="form-control" id="kegiatanSearch" autocomplete="off">
                </div>
                <div class="col-6 col-md-2">
                    <label class="cbt-form-label" for="kegiatanJenisFilter">Jenis</label>
                    <select class="form-select" id="kegiatanJenisFilter"><option value="">Semua</option><option value="AKADEMIK">Akademik</option><option value="PSIKOLOGIS">Psikologis</option></select>
                </div>
                <div class="col-6 col-md-2">
                    <label class="cbt-form-label" for="kegiatanStatusFilter">Status</label>
                    <select class="form-select" id="kegiatanStatusFilter"><option value="">Semua</option><option value="DRAFT">Draft</option><option value="BERJALAN">Berjalan</option><option value="SELESAI">Selesai</option></select>
                </div>
                <div class="col-6 col-md-2">
                    <label class="cbt-form-label" for="kegiatanPageSize">Per halaman</label>
                    <select class="form-select" id="kegiatanPageSize"><option>25</option><option>50</option><option>100</option></select>
                </div>
                <div class="col-6 col-md-1"><button class="btn btn-outline-secondary w-100" type="button" id="kegiatanReset">Reset</button></div>
            </div>
            <div class="cbt-inline-feedback mt-2" id="kegiatanFeedback" role="status" aria-live="polite"></div>
        </div>
        <div class="table-responsive">
            <table class="table manager-table mb-0">
                <thead><tr><th>Nama Kegiatan</th><th>Jenis</th><th>Tahun Pelajaran</th><th>Semester</th><th>Keterangan</th><th>Exam Browser</th><th>Status</th><th>Aksi</th></tr></thead>
                <tbody id="kegiatanRows"></tbody>
            </table>
        </div>
        <div class="card-body d-flex flex-wrap align-items-center justify-content-between gap-2">
            <span class="small text-secondary" id="kegiatanCount"></span>
            <div class="d-flex align-items-center gap-2">
                <button class="btn btn-outline-secondary btn-sm" type="button" id="kegiatanPrevious">Sebelumnya</button>
                <span class="small" id="kegiatanPageInfo"></span>
                <button class="btn btn-outline-secondary btn-sm" type="button" id="kegiatanNext">Berikutnya</button>
            </div>
        </div>
    </section>
</div>
<div class="modal fade" id="kegiatanModal" tabindex="-1" aria-labelledby="kegiatanModalTitle" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered modal-lg"><div class="modal-content">
        <div class="modal-header"><h2 class="modal-title fs-5" id="kegiatanModalTitle">Tambah Kegiatan</h2><button class="btn-close" type="button" data-bs-dismiss="modal" aria-label="Tutup"></button></div>
        <div class="modal-body">
            <form id="kegiatanForm">
                <div class="mb-3"><label class="cbt-form-label" for="kegiatanNama">Nama Kegiatan</label><input class="form-control" id="kegiatanNama" maxlength="180" required></div>
                <div class="row g-3 mb-3">
                    <div class="col-md-6"><label class="cbt-form-label" for="kegiatanJenis">Jenis</label><select class="form-select" id="kegiatanJenis" required><option value="AKADEMIK">Akademik</option><option value="PSIKOLOGIS">Psikologis</option></select></div>
                    <div class="col-md-6"><label class="cbt-form-label" for="kegiatanTahun">Tahun Pelajaran</label><input class="form-control" id="kegiatanTahun" placeholder="2026/2027" maxlength="9" pattern="20[0-9]{2}/20[0-9]{2}" required></div>
                    <div class="col-md-6"><label class="cbt-form-label" for="kegiatanSemester">Semester</label><select class="form-select" id="kegiatanSemester" required><option value="GANJIL">Ganjil</option><option value="GENAP">Genap</option></select></div>
                    <div class="col-md-6 d-flex align-items-end"><div class="form-check mb-2"><input class="form-check-input" type="checkbox" id="kegiatanBrowser"><label class="form-check-label" for="kegiatanBrowser">Wajib Exam Browser</label></div></div>
                </div>
                <div class="mb-3"><label class="cbt-form-label" for="kegiatanKeterangan">Keterangan (opsional)</label><textarea class="form-control" id="kegiatanKeterangan" maxlength="500" rows="3"></textarea></div>
                <p class="small text-secondary">Kegiatan baru berstatus DRAFT. Tahun Pelajaran dan Semester disalin dari Settings sebagai isian awal; Anda dapat menyesuaikannya sebelum menyimpan.</p>
                <div class="d-flex justify-content-end gap-2"><button class="btn btn-outline-secondary" type="button" data-bs-dismiss="modal">Batal</button><button class="btn btn-cbt-primary" type="submit" id="kegiatanSubmit">Simpan</button></div>
                <div class="cbt-inline-feedback mt-2" id="kegiatanFormFeedback" role="alert"></div>
            </form>
        </div>
    </div></div>
</div>
<?= $this->endSection() ?>
<?= $this->section('pageScripts') ?>
<script src="<?= base_url('assets/js/manager-kegiatan.js') ?>" defer></script>
<?= $this->endSection() ?>
