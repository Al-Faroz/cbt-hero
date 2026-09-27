<?= $this->extend('manager/layouts/main') ?>
<?= $this->section('content') ?>
<section class="manager-page-header">
    <div>
        <div class="manager-page-kicker">Sistem</div>
        <h1 class="manager-page-title">Pengaturan</h1>
        <p class="manager-page-description">Default akademik dan identitas yang digunakan pada Kartu Ujian.</p>
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
<section class="manager-section-card mt-3" id="cardIdentityApp" data-api="<?= esc(base_url('manager/api/settings/card-identity'), 'attr') ?>">
    <div class="card-header"><div class="fw-bold">Identitas Kartu Ujian</div></div>
    <div class="card-body">
        <p class="text-secondary">Isi identitas madrasah dan URL login peserta sebelum mencetak kartu. Logo bawaan CBT-HERO dipakai hingga Anda mengunggah logo madrasah.</p>
        <form id="cardIdentityForm" enctype="multipart/form-data">
            <div class="row g-2">
                <div class="col-md-6"><label class="cbt-form-label" for="cardInstitutionName">Nama Instansi</label><input class="form-control" id="cardInstitutionName" maxlength="150" required></div>
                <div class="col-md-6"><label class="cbt-form-label" for="cardCbtUrl">URL CBT untuk peserta</label><input class="form-control" type="url" id="cardCbtUrl" maxlength="500" placeholder="https://cbt.sekolah.sch.id/" required></div>
                <div class="col-12"><label class="cbt-form-label" for="cardInstitutionAddress">Alamat Instansi</label><textarea class="form-control" id="cardInstitutionAddress" maxlength="300" rows="2" required></textarea></div>
                <div class="col-md-6"><label class="cbt-form-label" for="cardLogo">Logo madrasah (opsional)</label><input class="form-control" type="file" id="cardLogo" accept=".png,.jpg,.jpeg,image/png,image/jpeg"><div class="form-text">PNG/JPG maksimal 1 MB dan 2000×2000 piksel.</div><div class="form-check mt-2"><input class="form-check-input" type="checkbox" id="cardUseDefaultLogo"><label class="form-check-label" for="cardUseDefaultLogo">Gunakan kembali logo bawaan CBT-HERO</label></div></div>
                <div class="col-md-6 d-flex align-items-center gap-3"><img id="cardLogoPreview" src="<?= base_url(config('Branding')->logo) ?>" width="72" height="72" alt="Pratinjau logo kartu" style="object-fit:contain"><span class="small text-secondary" id="cardLogoStatus">Logo bawaan</span></div>
            </div>
            <div class="mt-3"><button class="btn btn-cbt-primary" id="cardIdentitySave" type="submit">Simpan Identitas</button></div>
            <div id="cardIdentityFeedback" class="cbt-inline-feedback mt-2" role="status" aria-live="polite"></div>
        </form>
    </div>
</section>
<?= $this->endSection() ?>
<?= $this->section('pageScripts') ?>
<script src="<?= base_url('assets/js/manager-settings-academic.js') ?>" defer></script>
<script src="<?= base_url('assets/js/manager-settings-card-identity.js') ?>" defer></script>
<?= $this->endSection() ?>
