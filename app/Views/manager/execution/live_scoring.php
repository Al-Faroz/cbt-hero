<?= $this->extend('manager/layouts/main') ?>
<?= $this->section('content') ?>
<section class="manager-page-header">
    <div>
        <div class="manager-page-kicker">Pelaksanaan Ujian</div>
        <h1 class="manager-page-title">Live Scoring</h1>
        <p class="manager-page-description">Kontrol layar skor publik. Satu layar hanya menampilkan satu Jadwal dan hanya kelompok soal Klik.</p>
    </div>
</section>

<div id="liveScoringManager"
     data-api="<?= esc(base_url('manager/api/live-scoring'), 'attr') ?>"
     data-options-api="<?= esc(base_url('manager/api/live-scoring/options'), 'attr') ?>">
    <div class="row g-3">
        <div class="col-xl-7">
            <section class="manager-section-card h-100">
                <div class="card-body">
                    <label class="cbt-form-label" for="liveJadwal">Jadwal yang ditampilkan</label>
                    <select class="form-select" id="liveJadwal"><option value="">Pilih Jadwal</option></select>
                    <div class="d-flex flex-wrap gap-2 mt-3">
                        <button class="btn btn-success" id="liveStart" type="button"><i class="bi bi-play-fill me-1"></i>START Public Display</button>
                        <button class="btn btn-outline-danger" id="liveStop" type="button"><i class="bi bi-stop-fill me-1"></i>STOP</button>
                    </div>
                    <div class="cbt-inline-feedback mt-3" id="liveFeedback" role="status" aria-live="polite"></div>
                </div>
            </section>
        </div>
        <div class="col-xl-5">
            <section class="manager-section-card h-100">
                <div class="card-body">
                    <div class="d-flex align-items-center justify-content-between gap-2">
                        <div><div class="small text-secondary">Status</div><strong id="liveState">OFF</strong></div>
                        <span class="badge text-bg-secondary" id="liveBadge">OFF</span>
                    </div>
                    <hr>
                    <label class="cbt-form-label" for="liveUrl">URL Public</label>
                    <div class="input-group">
                        <input class="form-control" id="liveUrl" readonly placeholder="URL dibuat saat Live Scoring disiapkan">
                        <button class="btn btn-outline-secondary" id="liveCopy" type="button">Salin</button>
                    </div>
                    <div class="d-flex flex-wrap gap-2 mt-2">
                        <button class="btn btn-outline-secondary btn-sm" id="liveOpen" type="button" disabled>Buka Fullscreen</button>
                        <button class="btn btn-outline-warning btn-sm" id="liveRegenerate" type="button">Ganti URL</button>
                    </div>
                    <p class="small text-secondary mt-3 mb-0">Mengganti URL langsung menonaktifkan URL lama. Public display mengambil snapshot baru setelah satu siklus scroll selesai.</p>
                </div>
            </section>
        </div>
    </div>
</div>
<?= $this->endSection() ?>

<?= $this->section('pageScripts') ?>
<script src="<?= base_url('assets/js/manager-live-scoring.js') ?>" defer></script>
<?= $this->endSection() ?>
