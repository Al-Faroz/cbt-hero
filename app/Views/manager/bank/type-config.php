<?= $this->extend('manager/layouts/main') ?>
<?= $this->section('content') ?>
<section class="manager-page-header">
    <div><div class="manager-page-kicker">Master Ujian / Bank Soal</div>
        <h1 class="manager-page-title">Komposisi Tipe Soal</h1>
        <p class="manager-page-description" id="typeContext">Memuat Bank Soal...</p></div>
    <a class="btn btn-outline-secondary" href="<?= base_url('manager/master-ujian/bank-soal') ?>">Kembali ke Bank Soal</a>
</section>
<section class="manager-section-card" id="typeApp" data-api="<?= esc(base_url('manager/api/bank-soal/' . $bankId . '/type-config'), 'attr') ?>">
    <div class="card-header fw-bold">Rencana Komposisi Bank</div>
    <div class="card-body">
        <p class="small text-secondary mb-2">Centang tipe yang digunakan, isi jumlah soal yang dibuat di Bank, jumlah pilihan untuk tipe PG, serta bobotnya. Jumlah soal yang diambil peserta diatur pada Jadwal. Pada DRAFT total bobot boleh belum 100%, tetapi tidak boleh melebihi 100%.</p>
        <div class="cbt-inline-feedback" id="typeFeedback" role="status" aria-live="polite"></div>
    </div>
    <form id="typeForm"><div class="table-responsive"><table class="table manager-table mb-0">
        <thead><tr><th>Gunakan</th><th>Tipe Soal</th><th>Jumlah soal Bank</th><th>Jumlah pilihan</th><th>Bobot (%)</th><th>Acak soal</th><th>Acak opsi/pasangan</th><th>Penilaian Matching</th></tr></thead>
        <tbody id="typeRows"></tbody>
    </table></div>
    <div class="card-body d-flex flex-wrap align-items-center justify-content-between gap-2">
        <div><strong id="typeTotal">Total bobot: 0%</strong><div id="typeStatus" class="small text-secondary"></div></div>
        <button class="btn btn-cbt-primary" id="typeSave" type="submit" disabled>Simpan Komposisi</button>
    </div></form>
</section>
<?= $this->endSection() ?>
<?= $this->section('pageScripts') ?><script src="<?= base_url('assets/js/manager-bank-type-config.js') ?>" defer></script><?= $this->endSection() ?>
