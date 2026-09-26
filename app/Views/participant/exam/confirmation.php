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

        <h1 class="participant-page-title">
            <?= esc($exam['nama_ujian']) ?>
        </h1>

        <p class="participant-page-description">
            <?= esc($exam['nama_kegiatan']) ?>
        </p>
    </div>

    <div class="participant-identity">
        <div>
            <p class="participant-identity-name">
                <?= esc($participant['nama']) ?>
            </p>

            <div class="participant-identity-meta">
                <?= esc($participant['username']) ?>
                · <?= esc($participant['rombel']) ?>
            </div>
        </div>

        <span class="participant-identity-badge">
            <?= esc($exam['tipe']) ?>
        </span>
    </div>
</section>

<div class="participant-confirm-grid">
    <section class="participant-confirm-card">
        <h2 class="participant-confirm-title">Informasi Ujian</h2>

        <dl class="participant-confirm-list">
            <div class="participant-confirm-row">
                <dt>Kegiatan</dt>
                <dd><?= esc($exam['nama_kegiatan']) ?></dd>
            </div>

            <div class="participant-confirm-row">
                <dt>Ujian</dt>
                <dd><?= esc($exam['nama_ujian']) ?></dd>
            </div>

            <div class="participant-confirm-row">
                <dt>No Peserta</dt>
                <dd><?= esc($exam['nomor_peserta'] ?? '-') ?></dd>
            </div>

            <div class="participant-confirm-row">
                <dt>Ruang</dt>
                <dd><?= esc($exam['ruang'] ?? '-') ?></dd>
            </div>

            <div class="participant-confirm-row">
                <dt>Mulai</dt>
                <dd data-format-date="<?= esc($exam['mulai_at']) ?>"></dd>
            </div>

            <div class="participant-confirm-row">
                <dt>Batas Mulai</dt>
                <dd data-format-date="<?= esc($exam['batas_mulai_at']) ?>"></dd>
            </div>

            <div class="participant-confirm-row">
                <dt>Durasi</dt>
                <dd data-duration="<?= (int) $exam['durasi_seconds'] ?>"></dd>
            </div>

            <div class="participant-confirm-row">
                <dt>Status</dt>
                <dd><?= esc($exam['availability_message']) ?></dd>
            </div>
        </dl>
    </section>

    <section class="participant-confirm-card">
        <h2 class="participant-confirm-title">
            <?= $isResume ? 'Lanjutkan Ujian' : 'Sebelum Memulai' ?>
        </h2>

        <p class="participant-confirm-instruction">
            <?= esc($confirmation['instruction']) ?>
        </p>

        <?php if ($confirmation['eligible']): ?>
            <div class="participant-start-box">
                <?php if ($confirmation['token_enabled']): ?>
                    <div>
                        <label class="cbt-form-label" for="examToken">
                            Token Ujian
                        </label>

                        <input
                            class="form-control text-uppercase"
                            id="examToken"
                            type="text"
                            autocomplete="off"
                            placeholder="Masukkan token"
                            disabled
                        >
                    </div>
                <?php endif; ?>

                <div class="form-check">
                    <input
                        class="form-check-input"
                        id="examAgreement"
                        type="checkbox"
                        disabled
                    >

                    <label class="form-check-label" for="examAgreement">
                        Saya sudah memeriksa identitas dan informasi ujian.
                    </label>
                </div>

                <button
                    class="btn btn-cbt-primary"
                    type="button"
                    disabled
                >
                    <?= $isResume ? 'Lanjutkan Ujian' : 'Mulai Ujian' ?>
                </button>

                <?php if (ENVIRONMENT === 'development'): ?>
                    <p class="participant-dev-note">
                        UI Konfirmasi sudah siap. START/RESUME authoritative
                        diaktifkan saat Attempt Engine dikerjakan.
                    </p>
                <?php endif; ?>
            </div>
        <?php else: ?>
            <div class="alert alert-warning mb-0 small">
                <?= esc($exam['availability_message']) ?>
            </div>
        <?php endif; ?>

        <a
            class="btn btn-outline-secondary w-100 mt-3"
            href="<?= base_url('ujian') ?>"
        >
            Kembali ke Daftar Ujian
        </a>
    </section>
</div>

<?= $this->endSection() ?>

<?= $this->section('pageScripts') ?>
<script>
(() => {
    'use strict';

    const formatDate = (iso) => {
        const date = new Date(iso);

        if (Number.isNaN(date.getTime())) {
            return '-';
        }

        return new Intl.DateTimeFormat('id-ID', {
            day: '2-digit',
            month: 'long',
            year: 'numeric',
            hour: '2-digit',
            minute: '2-digit',
            hour12: false
        }).format(date);
    };

    document.querySelectorAll('[data-format-date]').forEach((node) => {
        node.textContent = formatDate(node.dataset.formatDate);
    });

    document.querySelectorAll('[data-duration]').forEach((node) => {
        const seconds = Number(node.dataset.duration) || 0;
        const minutes = Math.floor(seconds / 60);
        const hours = Math.floor(minutes / 60);
        const remainder = minutes % 60;

        node.textContent = hours > 0
            ? (remainder > 0
                ? `${hours} jam ${remainder} menit`
                : `${hours} jam`)
            : `${minutes} menit`;
    });
})();
</script>
<?= $this->endSection() ?>
