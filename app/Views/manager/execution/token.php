<?= $this->extend('manager/layouts/main') ?>
<?= $this->section('content') ?>
<section class="manager-page-header">
    <div>
        <div class="manager-page-kicker">Pelaksanaan Ujian</div>
        <h1 class="manager-page-title">Token Ujian</h1>
        <p class="manager-page-description">Token global untuk START dan masuk kembali ke ujian saat Token aktif.</p>
    </div>
</section>

<div id="tokenApp" data-api="<?= esc(base_url('manager/api/token'), 'attr') ?>">
    <section class="manager-section-card mb-3">
        <div class="card-body">
            <div class="row g-3 align-items-stretch">
                <div class="col-md-4">
                    <div class="border rounded p-3 h-100">
                        <div class="small text-secondary mb-2">Status Token</div>
                        <div class="d-flex align-items-center gap-2 mb-3">
                            <span class="badge fs-6" id="tokenStateBadge">Memuat...</span>
                        </div>
                        <button class="btn btn-cbt-primary" id="tokenToggle" type="button">Ubah Status</button>
                    </div>
                </div>
                <div class="col-md-4">
                    <div class="border rounded p-3 h-100">
                        <div class="small text-secondary">Token Sekarang</div>
                        <div class="display-6 fw-semibold font-monospace my-2" id="tokenCurrent">------</div>
                        <div class="small text-secondary" id="tokenRotatedAt">-</div>
                    </div>
                </div>
                <div class="col-md-4">
                    <div class="border rounded p-3 h-100">
                        <div class="small text-secondary">Token Berikutnya</div>
                        <div class="display-6 fw-semibold font-monospace my-2" id="tokenNext">------</div>
                        <div class="small text-secondary" id="tokenNextAt">Rotasi otomatis tidak aktif.</div>
                    </div>
                </div>
            </div>
        </div>
    </section>

    <section class="manager-section-card">
        <div class="card-body">
            <div class="row g-3 align-items-end">
                <div class="col-md-5">
                    <label class="cbt-form-label" for="tokenAutoRotate">Rotasi otomatis</label>
                    <select class="form-select" id="tokenAutoRotate">
                        <option value="">Nonaktif</option>
                        <option value="5">Setiap 5 menit</option>
                        <option value="10">Setiap 10 menit</option>
                        <option value="15">Setiap 15 menit</option>
                        <option value="30">Setiap 30 menit</option>
                        <option value="60">Setiap 60 menit</option>
                    </select>
                    <div class="form-text">Rotasi tidak mengeluarkan peserta yang sudah berada di workspace.</div>
                </div>
                <div class="col-md-7">
                    <div class="d-flex flex-wrap gap-2">
                        <button class="btn btn-outline-primary" id="tokenSaveAuto" type="button">Simpan Rotasi</button>
                        <button class="btn btn-outline-secondary" id="tokenRotate" type="button">Rotasi Sekarang</button>
                        <button class="btn btn-outline-danger" id="tokenGenerate" type="button">Buat Token Baru</button>
                    </div>
                </div>
            </div>
            <div class="cbt-inline-feedback mt-3" id="tokenFeedback" role="status" aria-live="polite"></div>
        </div>
    </section>
</div>
<?= $this->endSection() ?>

<?= $this->section('pageScripts') ?>
<script src="<?= base_url('assets/js/manager-token.js') ?>" defer></script>
<?= $this->endSection() ?>
