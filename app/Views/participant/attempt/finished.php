<?= $this->extend('participant/layouts/main') ?>

<?= $this->section('content') ?>
<section class="participant-welcome">
    <div>
        <div class="participant-kicker">Ujian Selesai</div>
        <h1 class="participant-page-title"><?= esc($attempt['nama_mapel'] ?: $attempt['nama_bank']) ?></h1>
        <p class="participant-page-description">
            Jawaban yang sudah diterima server tersimpan pada Attempt ini.
        </p>
    </div>
</section>

<section class="participant-confirm-card mx-auto" style="max-width:760px">
    <div class="text-center py-3">
        <div class="fs-1 mb-2"><i class="bi bi-check-circle"></i></div>
        <h2 class="fs-4">Terima kasih</h2>
        <p class="text-secondary">
            Status: <?= esc($attempt['finish_reason'] ?? 'SELESAI') ?>
        </p>
    </div>

    <?php if ($showResult && is_array($snapshot)): ?>
        <div class="border rounded p-3 mb-3">
            <div class="small text-secondary">Nilai</div>
            <?php if ($snapshot['final_score'] !== null): ?>
                <div class="display-6 fw-semibold"><?= esc(number_format((float) $snapshot['final_score'], 2, ',', '.')) ?></div>
            <?php else: ?>
                <div class="fw-semibold">Penilaian masih diproses</div>
                <div class="small text-secondary">Soal yang memerlukan koreksi manual belum selesai dinilai.</div>
            <?php endif; ?>
        </div>
    <?php endif; ?>

    <a class="btn btn-cbt-primary w-100" href="<?= base_url('ujian') ?>">Kembali ke Daftar Ujian</a>
</section>
<?= $this->endSection() ?>
