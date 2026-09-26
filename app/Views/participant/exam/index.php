<?= $this->extend('participant/layouts/main') ?>

<?= $this->section('content') ?>

<section class="participant-welcome">
    <div>
        <div class="participant-kicker">Portal Peserta</div>

        <h1 class="participant-page-title">
            Daftar Ujian
        </h1>

        <p class="participant-page-description">
            Pilih ujian yang tersedia. Status akses akan mengikuti jadwal,
            persiapan ujian, dan Attempt Anda.
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
            PESERTA
        </span>
    </div>
</section>

<section>
    <div class="participant-section-header">
        <div>
            <h2 class="participant-section-title">Ujian Anda</h2>
            <p class="participant-section-subtitle">
                Daftar mengikuti hak peserta dan jadwal yang tersedia.
            </p>
        </div>
    </div>

    <div
        class="participant-exam-grid"
        data-exam-list
        data-api-url="<?= base_url('api/ujian') ?>"
        data-confirmation-base="<?= base_url('ujian') ?>"
    >
        <div class="participant-skeleton"></div>
        <div class="participant-skeleton"></div>
    </div>
</section>

<?= $this->endSection() ?>

<?= $this->section('pageScripts') ?>
<script>
document.documentElement.dataset.baseUrl = <?= json_encode(base_url('/')) ?>;
</script>
<script src="<?= base_url('assets/js/participant-exam-list.js') ?>"></script>
<?= $this->endSection() ?>
