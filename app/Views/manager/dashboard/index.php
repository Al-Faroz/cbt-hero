<?= $this->extend('manager/layouts/main') ?>
<?= $this->section('content') ?>
<?php
$readyPercentage = $summary['participants'] > 0
    ? min(100, (int) round($summary['ready'] * 100 / $summary['participants'])) : 0;
$scheduleStatusNames = [
    'MENUNGGU_WAKTU' => 'Menunggu Waktu',
    'SEDANG_BERJALAN' => 'Sedang Berjalan',
    'DITAHAN' => 'Ditahan',
    'BATAS_MULAI_LEWAT' => 'Batas Mulai Lewat',
    'SELESAI' => 'Selesai',
];
?>
<section class="manager-page-header dashboard-heading">
    <div>
        <div class="manager-page-kicker">Ringkasan operasional</div>
        <h1 class="manager-page-title">Dashboard CBT-HERO</h1>
        <p class="manager-page-description">Ringkasan kesiapan data, pelaksanaan ujian hari ini, penilaian, dan hasil.</p>
    </div>
    <span class="manager-role-chip"><?= esc($auth['role'] ?? 'Manager') ?></span>
</section>

<div class="dashboard-operational-grid">
    <a class="dashboard-metric dashboard-metric--blue" href="<?= base_url('manager/master-data/peserta') ?>">
        <span class="dashboard-metric-icon"><i class="bi bi-people"></i></span>
        <span class="dashboard-metric-label">Peserta Aktif</span>
        <strong class="dashboard-metric-number"><?= number_format($summary['participants'], 0, ',', '.') ?></strong>
        <span class="dashboard-metric-detail"><?= number_format($summary['ready'], 0, ',', '.') ?> siap login</span>
    </a>
    <a class="dashboard-metric dashboard-metric--violet" href="<?= base_url('manager/master-ujian/jadwal') ?>">
        <span class="dashboard-metric-icon"><i class="bi bi-calendar2-event"></i></span>
        <span class="dashboard-metric-label">Jadwal Hari Ini</span>
        <strong class="dashboard-metric-number"><?= number_format($summary['today_schedules'], 0, ',', '.') ?></strong>
        <span class="dashboard-metric-detail">Jadwal utama pada tanggal hari ini</span>
    </a>
    <a class="dashboard-metric dashboard-metric--teal" href="<?= base_url('manager/pelaksanaan/monitoring') ?>">
        <span class="dashboard-metric-icon"><i class="bi bi-display"></i></span>
        <span class="dashboard-metric-label">Sedang Ujian</span>
        <strong class="dashboard-metric-number"><?= number_format($summary['active_attempts'], 0, ',', '.') ?></strong>
        <span class="dashboard-metric-detail"><?= number_format($summary['finished_today'], 0, ',', '.') ?> selesai hari ini</span>
    </a>
    <a class="dashboard-metric dashboard-metric--amber" href="<?= base_url('manager/pelaksanaan/penilaian') ?>">
        <span class="dashboard-metric-icon"><i class="bi bi-pencil-square"></i></span>
        <span class="dashboard-metric-label">Perlu Penilaian</span>
        <strong class="dashboard-metric-number"><?= number_format($summary['pending_scoring'], 0, ',', '.') ?></strong>
        <span class="dashboard-metric-detail">Attempt belum COMPLETE</span>
    </a>
    <a class="dashboard-metric dashboard-metric--blue" href="<?= base_url('manager/master-ujian/jadwal') ?>">
        <span class="dashboard-metric-icon"><i class="bi bi-box-seam"></i></span>
        <span class="dashboard-metric-label">Assignment Siap</span>
        <strong class="dashboard-metric-number"><?= number_format($summary['ready_assignments'], 0, ',', '.') ?></strong>
        <span class="dashboard-metric-detail">Prepared Assignment READY</span>
    </a>
    <a class="dashboard-metric dashboard-metric--violet" href="<?= base_url('manager/hasil/ujian') ?>">
        <span class="dashboard-metric-icon"><i class="bi bi-check2-circle"></i></span>
        <span class="dashboard-metric-label">Hasil Difinalkan</span>
        <strong class="dashboard-metric-number"><?= number_format($summary['finalized_schedules'], 0, ',', '.') ?></strong>
        <span class="dashboard-metric-detail">Jadwal dengan hasil final</span>
    </a>
</div>

<div class="row g-2 mt-2">
    <div class="col-xl-7">
        <section class="manager-section-card dashboard-panel h-100">
            <div class="card-header dashboard-panel-header">
                <div>
                    <h2>Daftar Jadwal Ujian</h2>
                    <p>Kesiapan Preparation dan status operasional jadwal terbaru.</p>
                </div>
                <a href="<?= base_url('manager/master-ujian/jadwal') ?>">Lihat semua</a>
            </div>
            <div class="card-body dashboard-activities">
                <?php if ($recentSchedules === []): ?>
                    <div class="dashboard-empty">
                        <strong>Belum ada Jadwal Ujian.</strong>
                        <a class="btn btn-cbt-primary btn-sm" href="<?= base_url('manager/master-ujian/jadwal') ?>">Buka Jadwal Ujian</a>
                    </div>
                <?php else: ?>
                    <ul class="dashboard-activity-list">
                        <?php foreach ($recentSchedules as $schedule): ?>
                            <li>
                                <span class="dashboard-activity-mark"><i class="bi bi-calendar-check"></i></span>
                                <div class="dashboard-activity-copy">
                                    <strong><?= esc($schedule['nama_mapel'] ?: $schedule['nama_bank']) ?></strong>
                                    <span>
                                        <?= esc($schedule['kegiatan_nama']) ?> · Tingkat <?= esc((string) ($schedule['tingkat'] ?? '-')) ?>
                                        · Urutan <?= (int) ($schedule['urutan_ujian'] ?? 1) ?>
                                        · <?= esc(date('d-m-Y H:i', strtotime((string) $schedule['mulai_at']))) ?>
                                    </span>
                                </div>
                                <div class="d-flex flex-column align-items-end gap-1">
                                    <span class="badge <?= $schedule['preparation_state'] === 'READY' ? 'text-bg-success' : 'text-bg-secondary' ?>">
                                        <?= esc($schedule['preparation_state']) ?>
                                    </span>
                                    <span class="small text-secondary">
                                        <?= esc($scheduleStatusNames[$schedule['operational_state']] ?? $schedule['operational_state']) ?>
                                    </span>
                                </div>
                            </li>
                        <?php endforeach; ?>
                    </ul>
                <?php endif; ?>
            </div>
        </section>
    </div>

    <div class="col-xl-5">
        <section class="manager-section-card dashboard-panel h-100">
            <div class="card-header dashboard-panel-header">
                <div>
                    <h2>Jalur Kerja Akademik</h2>
                    <p>Akses cepat sesuai alur operasional.</p>
                </div>
            </div>
            <div class="card-body">
                <div class="dashboard-quick-grid">
                    <a href="<?= base_url('manager/master-data/peserta/import') ?>"><i class="bi bi-file-earmark-arrow-up"></i><span>Import Peserta</span></a>
                    <a href="<?= base_url('manager/master-ujian/kegiatan') ?>"><i class="bi bi-clipboard2-plus"></i><span>Kegiatan</span></a>
                    <a href="<?= base_url('manager/master-ujian/bank-soal') ?>"><i class="bi bi-journal-text"></i><span>Bank Soal</span></a>
                    <a href="<?= base_url('manager/master-ujian/jadwal') ?>"><i class="bi bi-calendar-check"></i><span>Jadwal</span></a>
                    <a href="<?= base_url('manager/pelaksanaan/monitoring') ?>"><i class="bi bi-display"></i><span>Monitoring</span></a>
                    <a href="<?= base_url('manager/hasil/ujian') ?>"><i class="bi bi-bar-chart-line"></i><span>Hasil Ujian</span></a>
                </div>

                <div class="dashboard-readiness mt-3">
                    <div class="dashboard-readiness-value">
                        <strong><?= $readyPercentage ?>%</strong>
                        <span><?= number_format($summary['ready'], 0, ',', '.') ?> dari <?= number_format($summary['participants'], 0, ',', '.') ?> peserta siap login</span>
                    </div>
                    <div class="progress dashboard-progress">
                        <div class="progress-bar" style="width: <?= $readyPercentage ?>%"></div>
                    </div>
                </div>
            </div>
        </section>
    </div>
</div>
<?= $this->endSection() ?>
