<?php
$role = (string) ($auth['role'] ?? '');
$isAdmin = $role === 'ADMIN';

$active = static function (string $key) use ($activeMenu): string {
    return $activeMenu === $key ? ' active' : '';
};

$systemOpen = $activeMenu === 'system-users';
?>
<aside
    class="offcanvas-lg offcanvas-start manager-sidebar"
    tabindex="-1"
    id="managerSidebar"
    aria-labelledby="managerSidebarLabel"
>
    <div class="offcanvas-header">
        <a class="cbt-brand" href="<?= base_url('manager/dashboard') ?>">
            <span class="cbt-brand-mark">H</span>
            <span class="cbt-brand-copy">
                <span class="cbt-brand-title" id="managerSidebarLabel">CBT-HERO</span>
                <span class="cbt-brand-subtitle">Manager Console</span>
            </span>
        </a>

        <button
            type="button"
            class="manager-sidebar-close"
            data-bs-dismiss="offcanvas"
            data-bs-target="#managerSidebar"
            aria-label="Tutup menu"
        >×</button>
    </div>

    <div class="manager-sidebar-body">
        <nav class="manager-nav" aria-label="Navigasi Manager">

            <a
                class="manager-nav-link<?= $active('dashboard') ?>"
                href="<?= base_url('manager/dashboard') ?>"
            >
                <span class="manager-nav-icon icon-dashboard"></span>
                <span>Dashboard</span>
            </a>

            <div class="manager-nav-section">Master Data</div>

            <button
                class="manager-nav-toggle"
                type="button"
                data-bs-toggle="collapse"
                data-bs-target="#navMasterData"
                aria-expanded="false"
                aria-controls="navMasterData"
            >
                <span class="manager-nav-icon icon-master-data"></span>
                <span>Master Data</span>
                <span class="manager-nav-chevron"></span>
            </button>

            <div class="collapse manager-subnav" id="navMasterData">
                <span class="manager-subnav-link is-unavailable">Periode</span>
                <span class="manager-subnav-link is-unavailable">Rombel</span>
                <span class="manager-subnav-link is-unavailable">Peserta</span>
                <span class="manager-subnav-link is-unavailable">Mata Pelajaran</span>
            </div>

            <div class="manager-nav-section">Master Ujian</div>

            <button
                class="manager-nav-toggle"
                type="button"
                data-bs-toggle="collapse"
                data-bs-target="#navMasterUjian"
                aria-expanded="false"
                aria-controls="navMasterUjian"
            >
                <span class="manager-nav-icon icon-master-ujian"></span>
                <span>Master Ujian</span>
                <span class="manager-nav-chevron"></span>
            </button>

            <div class="collapse manager-subnav" id="navMasterUjian">
                <span class="manager-subnav-link is-unavailable">Kegiatan Ujian</span>
                <span class="manager-subnav-link is-unavailable">Peserta Ujian</span>
                <span class="manager-subnav-link is-unavailable">Ruang</span>
                <span class="manager-subnav-link is-unavailable">Bank Soal</span>
                <span class="manager-subnav-link is-unavailable">Instrumen Psikologis</span>
                <span class="manager-subnav-link is-unavailable">Jadwal Ujian</span>
            </div>

            <div class="manager-nav-section">Pelaksanaan</div>

            <button
                class="manager-nav-toggle"
                type="button"
                data-bs-toggle="collapse"
                data-bs-target="#navPelaksanaan"
                aria-expanded="false"
                aria-controls="navPelaksanaan"
            >
                <span class="manager-nav-icon icon-pelaksanaan"></span>
                <span>Pelaksanaan Ujian</span>
                <span class="manager-nav-chevron"></span>
            </button>

            <div class="collapse manager-subnav" id="navPelaksanaan">
                <span class="manager-subnav-link is-unavailable">Token</span>
                <span class="manager-subnav-link is-unavailable">Monitoring Ujian</span>
                <span class="manager-subnav-link is-unavailable">Live Scoring</span>
            </div>

            <div class="manager-nav-section">Hasil & Laporan</div>

            <button
                class="manager-nav-toggle"
                type="button"
                data-bs-toggle="collapse"
                data-bs-target="#navHasil"
                aria-expanded="false"
                aria-controls="navHasil"
            >
                <span class="manager-nav-icon icon-hasil"></span>
                <span>Hasil & Laporan</span>
                <span class="manager-nav-chevron"></span>
            </button>

            <div class="collapse manager-subnav" id="navHasil">
                <span class="manager-subnav-link is-unavailable">Hasil Ujian</span>
                <span class="manager-subnav-link is-unavailable">Rekap Nilai</span>
                <span class="manager-subnav-link is-unavailable">Analisis Soal</span>
                <span class="manager-subnav-link is-unavailable">Hasil Psikologis</span>
            </div>

            <div class="manager-nav-section">Sistem</div>

            <button
                class="manager-nav-toggle<?= $systemOpen ? ' is-open' : '' ?>"
                type="button"
                data-bs-toggle="collapse"
                data-bs-target="#navSystem"
                aria-expanded="<?= $systemOpen ? 'true' : 'false' ?>"
                aria-controls="navSystem"
            >
                <span class="manager-nav-icon icon-system"></span>
                <span>Sistem</span>
                <span class="manager-nav-chevron"></span>
            </button>

            <div class="collapse manager-subnav<?= $systemOpen ? ' show' : '' ?>" id="navSystem">
                <?php if ($isAdmin): ?>
                    <a
                        class="manager-subnav-link<?= $active('system-users') ?>"
                        href="<?= base_url('manager/system/users') ?>"
                    >
                        User Manager
                    </a>

                    <span class="manager-subnav-link is-unavailable">Pengaturan</span>
                    <span class="manager-subnav-link is-unavailable">Pengosongan Data</span>
                <?php endif; ?>

                <span class="manager-subnav-link is-unavailable">Backup</span>
                <span class="manager-subnav-link is-unavailable">Log</span>
            </div>

        </nav>
    </div>
</aside>
