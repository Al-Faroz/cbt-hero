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
        <div class="participant-result-grid mb-3">
            <div class="participant-result-card">
                <span>NILAI PILIHAN GANDA</span>
                <strong>
                    <?= $snapshot['click_score'] === null
                        ? '—'
                        : esc(number_format((float) $snapshot['click_score'], 2, ',', '.')) ?>
                </strong>
                <small>PG, PG Kompleks, PG Bertingkat, dan Menjodohkan</small>
            </div>

            <div class="participant-result-card">
                <span>NILAI ISIAN &amp; URAIAN</span>
                <?php if (($typedScoreState ?? 'IN_PROCESS') === 'COMPLETE' && $snapshot['typed_score'] !== null): ?>
                    <strong><?= esc(number_format((float) $snapshot['typed_score'], 2, ',', '.')) ?></strong>
                    <small>Penilaian bagian ketik sudah selesai.</small>
                <?php else: ?>
                    <strong class="participant-result-processing">DALAM PROSES</strong>
                    <small>Bagian yang memerlukan pemeriksaan manual belum selesai dinilai.</small>
                <?php endif; ?>
            </div>
        </div>
    <?php endif; ?>

    <a class="btn btn-cbt-primary w-100" href="<?= base_url('ujian') ?>">Kembali ke Daftar Ujian</a>
</section>
<?= $this->endSection() ?>
