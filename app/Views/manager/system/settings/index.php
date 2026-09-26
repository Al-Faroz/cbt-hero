<?= $this->extend('manager/layouts/main') ?>
<?= $this->section('content') ?>
<section class="manager-page-header">
    <div>
        <div class="manager-page-kicker">Sistem</div>
        <h1 class="manager-page-title">Pengaturan</h1>
        <p class="manager-page-description">Default Tahun Pelajaran dan Semester untuk Kegiatan Ujian baru.</p>
    </div>
    <span class="cbt-badge">ADMIN ONLY</span>
</section>
<section class="manager-section-card" id="academicSettingsApp" data-api="<?= esc(base_url('manager/api/settings/academic'), 'attr') ?>">
    <div class="card-header"><div class="fw-bold">Default akademik</div></div>
    <div class="card-body">
        <p class="text-secondary">Nilai ini dipakai sebagai isian awal Kegiatan baru. Kegiatan yang sudah dibuat menyimpan Tahun Pelajaran dan Semester sendiri.</p>
        <form id="academicSettingsForm" class="row g-3 align-items-end">
            <div class="col-md-5">
                <label class="cbt-form-label" for="academicYear">Tahun Pelajaran</label>
                <input class="form-control" id="academicYear" placeholder="2026/2027" maxlength="9" pattern="20[0-9]{2}/20[0-9]{2}" required>
            </div>
            <div class="col-md-4">
                <label class="cbt-form-label" for="academicSemester">Semester</label>
                <select class="form-select" id="academicSemester" required>
                    <option value="">Pilih Semester</option><option value="GANJIL">Ganjil</option><option value="GENAP">Genap</option>
                </select>
            </div>
            <div class="col-md-3"><button class="btn btn-cbt-primary w-100" id="academicSave" type="submit">Simpan</button></div>
        </form>
        <div id="academicFeedback" class="cbt-inline-feedback mt-3" role="status" aria-live="polite"></div>
    </div>
</section>
<?= $this->endSection() ?>
<?= $this->section('pageScripts') ?>
<script src="<?= base_url('assets/js/manager-settings-academic.js') ?>" defer></script>
<?= $this->endSection() ?>
