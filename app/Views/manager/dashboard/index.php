<?= $this->extend('manager/layouts/main') ?>
<?= $this->section('content') ?>
<?php
$readyPercentage = $summary['participants'] > 0
    ? min(100, (int) round($summary['ready'] * 100 / $summary['participants'])) : 0;
$statusNames = ['DRAFT' => 'Draft', 'BERJALAN' => 'Berjalan', 'SELESAI' => 'Selesai'];
?>
<section class="manager-page-header dashboard-heading">
    <div>
        <div class="manager-page-kicker">Ringkasan operasional</div>
        <h1 class="manager-page-title">Selamat datang, <?= esc($auth['nama'] ?? 'Manager') ?></h1>
        <p class="manager-page-description">Pantau data peserta dan siapkan kegiatan ujian dari satu tempat.</p>
    </div>
    <span class="manager-role-chip"><?= esc($auth['role'] ?? 'Manager') ?></span>
</section>

<div class="row g-3 dashboard-metrics">
    <div class="col-sm-6 col-xl-3">
        <a class="dashboard-metric dashboard-metric--blue" href="<?= base_url('manager/master-data/peserta') ?>">
            <span class="dashboard-metric-icon"><i class="bi bi-people" aria-hidden="true"></i></span>
            <span class="dashboard-metric-label">Peserta aktif</span>
            <strong class="dashboard-metric-number"><?= number_format($summary['participants'], 0, ',', '.') ?></strong>
            <span class="dashboard-metric-detail"><?= number_format($summary['ready'], 0, ',', '.') ?> siap login <i class="bi bi-arrow-up-right" aria-hidden="true"></i></span>
        </a>
    </div>
    <div class="col-sm-6 col-xl-3">
        <a class="dashboard-metric dashboard-metric--violet" href="<?= base_url('manager/master-data/rombel') ?>">
            <span class="dashboard-metric-icon"><i class="bi bi-diagram-3" aria-hidden="true"></i></span>
            <span class="dashboard-metric-label">Rombel aktif</span>
            <strong class="dashboard-metric-number"><?= number_format($summary['classes'], 0, ',', '.') ?></strong>
            <span class="dashboard-metric-detail">Kelola rombel <i class="bi bi-arrow-up-right" aria-hidden="true"></i></span>
        </a>
    </div>
    <div class="col-sm-6 col-xl-3">
        <a class="dashboard-metric dashboard-metric--teal" href="<?= base_url('manager/master-data/mapel') ?>">
            <span class="dashboard-metric-icon"><i class="bi bi-book" aria-hidden="true"></i></span>
            <span class="dashboard-metric-label">Mata pelajaran aktif</span>
            <strong class="dashboard-metric-number"><?= number_format($summary['subjects'], 0, ',', '.') ?></strong>
            <span class="dashboard-metric-detail">Kelola mata pelajaran <i class="bi bi-arrow-up-right" aria-hidden="true"></i></span>
        </a>
    </div>
    <div class="col-sm-6 col-xl-3">
        <a class="dashboard-metric dashboard-metric--amber" href="<?= base_url('manager/master-ujian/kegiatan') ?>">
            <span class="dashboard-metric-icon"><i class="bi bi-clipboard-check" aria-hidden="true"></i></span>
            <span class="dashboard-metric-label">Kegiatan ujian</span>
            <strong class="dashboard-metric-number"><?= number_format($summary['activities'], 0, ',', '.') ?></strong>
            <span class="dashboard-metric-detail"><?= number_format($summary['drafts'], 0, ',', '.') ?> draft <i class="bi bi-arrow-up-right" aria-hidden="true"></i></span>
        </a>
    </div>
</div>

<div class="row g-3 mt-1">
    <div class="col-xl-7">
        <section class="manager-section-card dashboard-panel h-100" aria-labelledby="dashboard-kegiatan-heading">
            <div class="card-header dashboard-panel-header">
                <div><h2 id="dashboard-kegiatan-heading">Kegiatan terbaru</h2><p>Lima kegiatan yang terakhir dibuat.</p></div>
                <a href="<?= base_url('manager/master-ujian/kegiatan') ?>">Lihat semua <i class="bi bi-arrow-right" aria-hidden="true"></i></a>
            </div>
            <div class="card-body dashboard-activities">
                <?php if ($recentActivities === []): ?>
                    <div class="dashboard-empty"><i class="bi bi-clipboard-plus" aria-hidden="true"></i><strong>Belum ada kegiatan ujian</strong><span>Mulai dengan membuat kegiatan pertama.</span><a class="btn btn-cbt-primary btn-sm" href="<?= base_url('manager/master-ujian/kegiatan') ?>">Buka Kegiatan Ujian</a></div>
                <?php else: ?>
                    <ul class="dashboard-activity-list">
                        <?php foreach ($recentActivities as $activity): ?>
                            <li>
                                <span class="dashboard-activity-mark" aria-hidden="true"><i class="bi bi-clipboard-check"></i></span>
                                <div class="dashboard-activity-copy"><strong><?= esc($activity['nama']) ?></strong><span><?= esc($activity['tahun_pelajaran']) ?> · <?= esc($activity['semester']) ?></span></div>
                                <span class="dashboard-status dashboard-status--<?= esc(strtolower($activity['status']), 'attr') ?>"><?= esc($statusNames[$activity['status']] ?? $activity['status']) ?></span>
                            </li>
                        <?php endforeach; ?>
                    </ul>
                <?php endif; ?>
            </div>
        </section>
    </div>
    <div class="col-xl-5">
        <section class="manager-section-card dashboard-panel h-100" aria-labelledby="dashboard-readiness-heading">
            <div class="card-header dashboard-panel-header"><div><h2 id="dashboard-readiness-heading">Kesiapan login peserta</h2><p>Peserta aktif dengan kredensial siap.</p></div><span class="dashboard-readiness-icon"><i class="bi bi-shield-check" aria-hidden="true"></i></span></div>
            <div class="card-body dashboard-readiness">
                <div class="dashboard-readiness-value"><strong><?= $readyPercentage ?>%</strong><span><?= number_format($summary['ready'], 0, ',', '.') ?> dari <?= number_format($summary['participants'], 0, ',', '.') ?> peserta aktif</span></div>
                <div class="progress dashboard-progress" role="progressbar" aria-label="Peserta aktif siap login" aria-valuenow="<?= $readyPercentage ?>" aria-valuemin="0" aria-valuemax="100"><div class="progress-bar" style="width: <?= $readyPercentage ?>%"></div></div>
                <p><?= $summary['participants'] === 0 ? 'Tambahkan peserta untuk mulai menyiapkan akun.' : 'Periksa peserta yang belum siap login melalui halaman Peserta.' ?></p>
                <a class="btn btn-outline-primary" href="<?= base_url('manager/master-data/peserta') ?>">Buka data peserta <i class="bi bi-arrow-right" aria-hidden="true"></i></a>
            </div>
        </section>
    </div>
</div>
<?= $this->endSection() ?>
