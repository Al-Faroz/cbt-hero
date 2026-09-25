<?php
$auth = session()->get('manager_auth') ?? [];
$pageTitle = 'Akses Ditolak';
$pageSubtitle = 'Permission Manager';
$activeMenu = '';
?>

<?= $this->extend('manager/layouts/main') ?>

<?= $this->section('content') ?>
<section class="manager-page-header">
    <div>
        <div class="manager-page-kicker">403 · Forbidden</div>
        <h1 class="manager-page-title">Akses tidak diizinkan</h1>
        <p class="manager-page-description">
            Account <?= esc($auth['username'] ?? 'Manager') ?> tidak mempunyai
            permission untuk fungsi ini. Server tetap menjadi authority meskipun
            URL dipanggil langsung.
        </p>
    </div>
</section>

<section class="manager-section-card">
    <div class="card-body p-4">
        <div class="alert alert-danger mb-3">
            Fungsi ini hanya tersedia untuk role yang mempunyai permission terkait.
        </div>

        <a class="btn btn-cbt-primary" href="<?= base_url('manager/dashboard') ?>">
            Kembali ke Dashboard
        </a>
    </div>
</section>
<?= $this->endSection() ?>
