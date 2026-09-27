<?= $this->extend('manager/layouts/main') ?>
<?= $this->section('content') ?>
<section class="manager-page-header">
    <div><div class="manager-page-kicker">Master Ujian</div><h1 class="manager-page-title">Bank Soal</h1>
        <p class="manager-page-description">Wadah soal Akademik per Kegiatan, Mata Pelajaran, dan Tingkat.</p></div>
    <button class="btn btn-cbt-primary" type="button" id="bankAdd">Tambah Bank</button>
</section>
<div id="bankApp" data-api="<?= esc(base_url('manager/api/bank-soal'), 'attr') ?>" data-ui-base="<?= esc(base_url('manager/master-ujian/bank-soal'), 'attr') ?>">
    <section class="manager-section-card">
        <div class="card-body"><div class="row g-2 align-items-end">
            <div class="col-md-4"><label class="cbt-form-label" for="bankKegiatanFilter">Kegiatan Akademik</label><select class="form-select" id="bankKegiatanFilter"><option value="">Semua Kegiatan</option></select></div>
            <div class="col-md-4"><label class="cbt-form-label" for="bankSearch">Cari Bank / Mapel / Kegiatan</label><input class="form-control" id="bankSearch"></div>
            <div class="col-md-2"><label class="cbt-form-label" for="bankPageSize">Per halaman</label><select class="form-select" id="bankPageSize"><option>25</option><option>50</option><option>100</option></select></div>
            <div class="col-md-2"><button class="btn btn-outline-secondary w-100" id="bankReset" type="button">Reset</button></div>
        </div><div class="cbt-inline-feedback mt-2" id="bankFeedback" role="status" aria-live="polite"></div></div>
        <div class="table-responsive"><table class="table manager-table mb-0"><thead><tr><th>Bank Soal</th><th>Kegiatan</th><th>Mata Pelajaran</th><th>Tingkat</th><th>Status</th><th>Aksi</th></tr></thead><tbody id="bankRows"></tbody></table></div>
        <div class="card-body d-flex flex-wrap justify-content-between align-items-center gap-2"><span id="bankCount" class="small text-secondary"></span><div class="d-flex align-items-center gap-2"><button class="btn btn-outline-secondary btn-sm" id="bankPrevious" type="button">Sebelumnya</button><span id="bankPageInfo"></span><button class="btn btn-outline-secondary btn-sm" id="bankNext" type="button">Berikutnya</button></div></div>
    </section>
</div>
<div class="modal fade" id="bankModal" tabindex="-1" aria-labelledby="bankModalTitle" aria-hidden="true"><div class="modal-dialog modal-dialog-centered"><div class="modal-content">
    <div class="modal-header"><h2 class="modal-title fs-5" id="bankModalTitle">Tambah Bank Soal</h2><button class="btn-close" type="button" data-bs-dismiss="modal" aria-label="Tutup"></button></div>
    <div class="modal-body"><form id="bankForm">
        <div class="mb-3"><label class="cbt-form-label" for="bankKegiatan">Kegiatan Akademik</label><select class="form-select" id="bankKegiatan" required></select></div>
        <div class="mb-3"><label class="cbt-form-label" for="bankMapel">Mata Pelajaran</label><select class="form-select" id="bankMapel" required></select></div>
        <div class="mb-3"><label class="cbt-form-label" for="bankTingkat">Tingkat</label><select class="form-select" id="bankTingkat" required><option value="7">7</option><option value="8">8</option><option value="9">9</option></select></div>
        <div class="mb-3"><label class="cbt-form-label" for="bankNama">Nama Bank Soal</label><input class="form-control" id="bankNama" maxlength="180" required placeholder="Contoh: Matematika Kelas 7 — Paket Utama"></div>
        <p class="small text-secondary">Bank baru berstatus DRAFT. Setelah disimpan, atur Komposisi dan tambahkan soal sesuai tipe yang dipilih.</p>
        <div class="d-flex justify-content-end gap-2"><button class="btn btn-outline-secondary" type="button" data-bs-dismiss="modal">Batal</button><button class="btn btn-cbt-primary" id="bankSubmit" type="submit">Simpan</button></div>
        <div class="cbt-inline-feedback mt-2" id="bankFormFeedback" role="alert"></div>
    </form></div>
</div></div></div>
<?= $this->endSection() ?>
<?= $this->section('pageScripts') ?><script src="<?= base_url('assets/js/manager-bank-soal.js') ?>" defer></script><?= $this->endSection() ?>
