<?= $this->extend('manager/layouts/main') ?>

<?= $this->section('content') ?>

<section class="manager-page-header">
    <div>
        <div class="manager-page-kicker">Manager Console</div>

        <h1 class="manager-page-title">
            Selamat datang, <?= esc($auth['nama']) ?>
        </h1>

        <p class="manager-page-description">
            Fondasi Manager sudah aktif. Shell ini menjadi layout baku untuk seluruh
            Master Data, Master Ujian, Pelaksanaan, Hasil & Laporan, dan Sistem.
        </p>
    </div>

    <span class="manager-role-chip">
        <?= esc($auth['role']) ?>
    </span>
</section>

<div class="row g-3 mb-4">
    <div class="col-6 col-xl-3">
        <div class="manager-metric-card">
            <div class="manager-metric-label">Role Aktif</div>
            <div class="manager-metric-value"><?= esc($auth['role']) ?></div>
        </div>
    </div>

    <div class="col-6 col-xl-3">
        <div class="manager-metric-card">
            <div class="manager-metric-label">Session</div>
            <div class="manager-metric-value">Valid</div>
        </div>
    </div>

    <div class="col-6 col-xl-3">
        <div class="manager-metric-card">
            <div class="manager-metric-label">Auth</div>
            <div class="manager-metric-value">Aktif</div>
        </div>
    </div>

    <div class="col-6 col-xl-3">
        <div class="manager-metric-card">
            <div class="manager-metric-label">UI Shell</div>
            <div class="manager-metric-value">Ready</div>
        </div>
    </div>
</div>

<div class="row g-3">
    <div class="col-xl-7">
        <section class="manager-section-card h-100">
            <div class="card-header">
                <div class="fw-bold">Fondasi Modul</div>
                <div class="small text-secondary mt-1">
                    Menu akan aktif bertahap mengikuti phase implementasi.
                </div>
            </div>

            <div class="card-body">
                <div class="manager-placeholder-grid">
                    <div class="manager-placeholder-item">
                        <strong>Master Data</strong>
                        <span>Periode, Rombel, Peserta, Mata Pelajaran</span>
                    </div>

                    <div class="manager-placeholder-item">
                        <strong>Master Ujian</strong>
                        <span>Kegiatan, Ruang, Bank Soal, Jadwal</span>
                    </div>

                    <div class="manager-placeholder-item">
                        <strong>Pelaksanaan</strong>
                        <span>Token, Monitoring, Live Scoring</span>
                    </div>

                    <div class="manager-placeholder-item">
                        <strong>Hasil & Laporan</strong>
                        <span>Nilai, Rekap, Analisis, Psikologis</span>
                    </div>

                    <div class="manager-placeholder-item">
                        <strong>Sistem</strong>
                        <span>Backup, Log, Settings, User Manager</span>
                    </div>

                    <div class="manager-placeholder-item">
                        <strong>Participant</strong>
                        <span>Login, daftar ujian, workspace mobile-first</span>
                    </div>
                </div>
            </div>
        </section>
    </div>

    <div class="col-xl-5">
        <section class="manager-section-card h-100">
            <div class="card-header">
                <div class="fw-bold">Akses Cepat</div>
                <div class="small text-secondary mt-1">
                    Hanya fungsi yang sudah tersedia yang dibuat aktif.
                </div>
            </div>

            <div class="card-body">
                <?php if (($auth['role'] ?? '') === 'ADMIN'): ?>
                    <a
                        class="btn btn-cbt-primary w-100"
                        href="<?= base_url('manager/system/users') ?>"
                    >
                        Kelola User Manager
                    </a>
                <?php endif; ?>

                <a
                    class="btn btn-outline-secondary w-100 mt-2"
                    href="<?= base_url('/') ?>"
                    target="_blank"
                    rel="noopener"
                >
                    Lihat Landing Peserta
                </a>

                <div class="alert alert-light border mt-3 mb-0 small text-secondary">
                    Landing Peserta dan Manager sekarang menggunakan design system
                    yang sama dengan Dashboard.
                </div>
            </div>
        </section>
    </div>
</div>

<?= $this->endSection() ?>
