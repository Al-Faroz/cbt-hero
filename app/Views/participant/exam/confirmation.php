<?= $this->extend('participant/layouts/main') ?>

<?= $this->section('content') ?>

<?php
$exam = $confirmation['exam'];
$isStart = $confirmation['mode'] === 'START';
$isResume = $confirmation['mode'] === 'RESUME';
?>

<section class="participant-welcome">
    <div>
        <div class="participant-kicker">Konfirmasi Ujian</div>
        <h1 class="participant-page-title"><?= esc($exam['nama_ujian']) ?></h1>
        <p class="participant-page-description"><?= esc($exam['nama_kegiatan']) ?></p>
    </div>

    <div class="participant-identity">
        <div>
            <p class="participant-identity-name"><?= esc($participant['nama']) ?></p>
            <div class="participant-identity-meta">
                <?= esc($participant['username']) ?> · <?= esc($participant['rombel']) ?>
            </div>
        </div>
        <span class="participant-identity-badge"><?= esc($exam['tipe']) ?></span>
    </div>
</section>

<div class="participant-confirm-grid">
    <section class="participant-confirm-card">
        <h2 class="participant-confirm-title">Informasi Ujian</h2>

        <dl class="participant-confirm-list">
            <div class="participant-confirm-row"><dt>Kegiatan</dt><dd><?= esc($exam['nama_kegiatan']) ?></dd></div>
            <div class="participant-confirm-row"><dt>Ujian</dt><dd><?= esc($exam['nama_ujian']) ?></dd></div>
            <div class="participant-confirm-row"><dt>No Peserta</dt><dd><?= esc($exam['nomor_peserta'] ?? '-') ?></dd></div>
            <div class="participant-confirm-row"><dt>Ruang</dt><dd><?= esc($exam['ruang'] ?? '-') ?></dd></div>
            <div class="participant-confirm-row"><dt>Mulai</dt><dd data-format-date="<?= esc($exam['mulai_at']) ?>"></dd></div>
            <div class="participant-confirm-row"><dt>Batas Mulai</dt><dd data-format-date="<?= esc($exam['batas_mulai_at']) ?>"></dd></div>
            <div class="participant-confirm-row"><dt>Durasi</dt><dd data-duration="<?= (int) $exam['durasi_seconds'] ?>"></dd></div>
            <div class="participant-confirm-row"><dt>Status</dt><dd><?= esc($exam['availability_message']) ?></dd></div>
        </dl>
    </section>

    <section class="participant-confirm-card"
        id="examConfirmationApp"
        data-mode="<?= esc($confirmation['mode'], 'attr') ?>"
        data-jadwal-id="<?= (int) $exam['jadwal_id'] ?>"
        data-attempt-id="<?= $exam['attempt_id'] === null ? '' : (int) $exam['attempt_id'] ?>"
        data-client-generation="<?= $exam['client_generation'] === null ? '' : (int) $exam['client_generation'] ?>"
        data-start-url="<?= esc(base_url('api/ujian/' . (int) $exam['jadwal_id'] . '/start'), 'attr') ?>"
        data-resume-base="<?= esc(base_url('api/attempt'), 'attr') ?>"
        data-token-enabled="<?= $confirmation['token_enabled'] ? '1' : '0' ?>"
        data-exam-browser-required="<?= !empty($exam['exam_browser_required']) ? '1' : '0' ?>">

        <h2 class="participant-confirm-title"><?= $isResume ? 'Lanjutkan Ujian' : 'Sebelum Memulai' ?></h2>
        <p class="participant-confirm-instruction"><?= esc($confirmation['instruction']) ?></p>

        <?php if ($confirmation['eligible']): ?>
            <div class="participant-start-box">
                <?php if ($confirmation['token_enabled']): ?>
                    <div>
                        <label class="cbt-form-label" for="examToken">Token Ujian</label>
                        <input class="form-control text-uppercase" id="examToken" type="text"
                            autocomplete="off" maxlength="32" placeholder="Masukkan token">
                    </div>
                <?php endif; ?>

                <?php if (!empty($exam['exam_browser_required'])): ?>
                    <div class="alert alert-info small mb-0">
                        Ujian ini wajib dibuka melalui Exam Browser.
                    </div>
                <?php endif; ?>

                <div class="form-check">
                    <input class="form-check-input" id="examAgreement" type="checkbox">
                    <label class="form-check-label" for="examAgreement">
                        Saya sudah memeriksa identitas dan informasi ujian.
                    </label>
                </div>

                <button class="btn btn-cbt-primary" type="button" id="examStartButton">
                    <?= $isResume ? 'Lanjutkan Ujian' : 'Mulai Ujian' ?>
                </button>

                <div class="cbt-inline-feedback" id="examStartFeedback" role="alert" aria-live="polite"></div>
            </div>
        <?php else: ?>
            <div class="alert alert-warning mb-0 small"><?= esc($exam['availability_message']) ?></div>
        <?php endif; ?>

        <a class="btn btn-outline-secondary w-100 mt-3" href="<?= base_url('ujian') ?>">
            Kembali ke Daftar Ujian
        </a>
    </section>
</div>

<?= $this->endSection() ?>

<?= $this->section('pageScripts') ?>
<script src="<?= base_url('assets/js/participant-exam-confirm.js') ?>"></script>
<?= $this->endSection() ?>
