<?= $this->extend('manager/layouts/main') ?>
<?= $this->section('content') ?>
<section class="manager-page-header">
    <div>
        <div class="manager-page-kicker">Master Ujian / Kegiatan</div>
        <h1 class="manager-page-title">Kesiapan Kegiatan</h1>
        <p class="manager-page-description"><?= esc($kegiatan['nama']) ?> · <?= esc($kegiatan['tahun_pelajaran']) ?> <?= esc($kegiatan['semester']) ?> · <?= esc($kegiatan['status']) ?></p>
    </div>
    <div class="d-flex flex-wrap gap-2">
        <a class="btn btn-outline-primary" href="<?= base_url('manager/master-ujian/kegiatan/' . $kegiatan['id'] . '/peserta') ?>">Kelola Peserta</a>
        <a class="btn btn-outline-secondary" href="<?= base_url('manager/master-ujian/kegiatan') ?>">Kembali ke Kegiatan</a>
    </div>
</section>
<section class="manager-section-card mb-3">
    <div class="card-header fw-bold">Pemeriksaan Administratif</div>
    <div class="card-body">
        <div class="alert <?= $administrativeReady ? 'alert-success' : 'alert-warning' ?> mb-3" role="status">
            <?= $administrativeReady ? 'Data administratif yang diperiksa sudah lengkap.' : 'Masih ada data administratif yang perlu dilengkapi.' ?>
        </div>
        <div class="row g-2">
            <div class="col-md-3"><div class="border rounded p-3 h-100"><span class="text-secondary">Anggota Kegiatan</span><div class="fs-4 fw-bold"><?= esc((string) $total) ?></div></div></div>
            <div class="col-md-3"><div class="border rounded p-3 h-100"><span class="text-secondary">Peserta perlu diperbaiki</span><div class="fs-4 fw-bold"><?= esc((string) $affected) ?></div><small>Jumlah peserta berbeda yang memiliki kekurangan.</small></div></div>
            <div class="col-md-3"><div class="border rounded p-3 h-100"><span class="text-secondary">Total temuan</span><div class="fs-4 fw-bold"><?= esc((string) $findings) ?></div><small>Jumlah kekurangan pada data peserta.</small></div></div>
            <div class="col-md-3"><div class="border rounded p-3 h-100"><span class="text-secondary">Identitas Kartu</span><div class="fw-bold"><?= $identityComplete ? 'Lengkap' : 'Belum lengkap' ?></div><?php if (! $identityComplete): ?><small>ADMIN: Sistem → Pengaturan</small><?php endif ?></div></div>
        </div>
        <?php if ($findings > $affected): ?><p class="small text-secondary mt-2 mb-0">Satu peserta bisa memiliki beberapa temuan, misalnya belum punya nomor peserta dan ruang.</p><?php endif ?>
        <?php if ($total === 0): ?><p class="mt-3 mb-0">Tambahkan anggota melalui halaman Peserta Ujian.</p><?php endif ?>
        <p class="small text-secondary mb-0 mt-3">Pemeriksaan ini mencakup data peserta, nomor, ruang, credential cetak, dan identitas kartu. Kesiapan Bank Soal dan Jadwal baru dapat diperiksa setelah modul tersebut tersedia. Status Kegiatan tidak diubah oleh halaman ini.</p>
    </div>
</section>
<?php foreach ($issues as $issue): ?>
<section class="manager-section-card mb-3">
    <div class="card-header d-flex justify-content-between gap-2"><strong><?= esc($issue['label']) ?></strong><span><?= esc((string) $issue['count']) ?> anggota</span></div>
    <?php if ($issue['count'] > 0): ?>
        <div class="table-responsive"><table class="table manager-table mb-0">
            <thead><tr><th>Nama Peserta</th><th>Rombel</th><th>Nomor Peserta</th></tr></thead>
            <tbody><?php foreach ($issue['samples'] as $sample): ?><tr>
                <td><?= esc($sample['nama']) ?></td><td><?= esc($sample['rombel']) ?></td><td><?= esc($sample['nomor'] ?: '—') ?></td>
            </tr><?php endforeach ?></tbody>
        </table></div>
        <?php if ($issue['count'] > 20): ?><div class="card-body small text-secondary">Menampilkan 20 anggota pertama dari <?= esc((string) $issue['count']) ?> yang perlu diperiksa.</div><?php endif ?>
    <?php else: ?><div class="card-body text-secondary">Tidak ada masalah pada kategori ini.</div><?php endif ?>
</section>
<?php endforeach ?>
<?= $this->endSection() ?>
