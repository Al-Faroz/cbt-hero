<?php
$base = base_url('manager/master-ujian/kegiatan/' . $kegiatan['id'] . '/kartu');
$url = static fn (int $target): string => $base . '?' . http_build_query([
    'scope' => $scope, 'value' => $value, 'page' => $target,
]);
$readyIdentity = trim($identity['institution_name']) !== ''
    && trim($identity['institution_address']) !== '' && trim($identity['cbt_url']) !== '';
?>
<!doctype html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width,initial-scale=1">
    <meta name="robots" content="noindex,nofollow">
    <title>Kartu Ujian — <?= esc($kegiatan['nama']) ?></title>
    <link rel="stylesheet" href="<?= base_url('assets/css/manager-kartu-ujian.css') ?>">
</head>
<body>
    <header class="toolbar">
        <div><strong>Kartu Ujian · <?= esc($kegiatan['nama']) ?></strong>
            <p><?= esc((string) $total) ?> peserta · batch <?= esc((string) $page) ?>/<?= esc((string) $pages) ?> · maksimal 100 kartu per batch</p>
        </div>
        <div class="toolbar-actions"><a href="<?= base_url('manager/master-ujian/kegiatan/' . $kegiatan['id'] . '/peserta') ?>">Kembali ke Peserta</a>
            <button type="button" onclick="window.print()"<?= $cards === [] ? ' disabled' : '' ?>>Cetak / Simpan PDF</button></div>
    </header>
    <form class="filters" method="get" action="<?= esc($base, 'attr') ?>">
        <label>Cakupan
            <select name="scope" id="cardScope">
                <option value="ALL"<?= $scope === 'ALL' ? ' selected' : '' ?>>Semua anggota</option>
                <option value="ROMBEL"<?= $scope === 'ROMBEL' ? ' selected' : '' ?>>Rombel</option>
                <option value="RUANG"<?= $scope === 'RUANG' ? ' selected' : '' ?>>Ruang</option>
            </select>
        </label>
        <label id="cardRombelWrap">Rombel
            <select id="cardRombel"<?= $scope === 'ROMBEL' ? ' name="value"' : '' ?>>
                <?php foreach ($rombelOptions as $option): ?>
                    <option value="<?= esc($option, 'attr') ?>"<?= $scope === 'ROMBEL' && $value === $option ? ' selected' : '' ?>><?= esc($option) ?></option>
                <?php endforeach ?>
            </select>
        </label>
        <label id="cardRuangWrap">Ruang
            <select id="cardRuang"<?= $scope === 'RUANG' ? ' name="value"' : '' ?>>
                <option value="NONE"<?= $scope === 'RUANG' && $value === 'NONE' ? ' selected' : '' ?>>Tanpa Ruang</option>
                <?php foreach ($roomOptions as $option): ?>
                    <option value="<?= esc($option['id'], 'attr') ?>"<?= $scope === 'RUANG' && $value === (string) $option['id'] ? ' selected' : '' ?>><?= esc($option['kode'] . ' — ' . $option['nama']) ?></option>
                <?php endforeach ?>
            </select>
        </label>
        <button type="submit">Tampilkan</button>
    </form>
    <?php if (! $readyIdentity): ?><p class="notice">Identitas kartu belum lengkap. Isi Nama Instansi, Alamat, dan URL CBT di Sistem → Pengaturan sebelum mencetak.</p><?php endif ?>
    <?php if ($incomplete > 0): ?><p class="notice"><?= esc((string) $incomplete) ?> kartu dalam batch ini belum lengkap (nomor peserta, username, password cetak, atau ruang). Periksa isian bertanda “Belum tersedia” sebelum dibagikan.</p><?php endif ?>
    <?php if ($cards === []): ?><p class="notice">Tidak ada anggota dalam cakupan ini.</p><?php endif ?>
    <nav class="batch-nav" aria-label="Navigasi batch">
        <?php if ($page > 1): ?><a href="<?= esc($url($page - 1), 'attr') ?>">← Batch sebelumnya</a><?php endif ?>
        <span>Batch <?= esc((string) $page) ?> dari <?= esc((string) $pages) ?></span>
        <?php if ($page < $pages): ?><a href="<?= esc($url($page + 1), 'attr') ?>">Batch berikutnya →</a><?php endif ?>
    </nav>
    <main>
    <?php foreach (array_chunk($cards, 10) as $sheet): ?>
        <section class="sheet" aria-label="Lembar kartu ujian">
            <?php foreach ($sheet as $card): ?>
                <article class="exam-card">
                    <div class="card-head">
                        <img src="<?= esc($identity['logo_url'], 'attr') ?>" alt="Logo instansi" width="32" height="32">
                        <div class="card-heading">
                            <strong><?= esc($identity['institution_name'] ?: 'NAMA INSTANSI BELUM DIISI') ?></strong>
                            <small><?= esc($identity['institution_address'] ?: 'Alamat belum diisi') ?></small>
                        </div>
                    </div>
                    <div class="card-event"><?= esc($kegiatan['nama']) ?> · <?= esc($kegiatan['tahun_pelajaran']) ?> <?= esc($kegiatan['semester']) ?></div>
                    <div class="card-details">
                        <div class="card-label">Nama</div><strong><?= esc($card['nama_snapshot']) ?></strong>
                        <div class="card-label">Rombel / Ruang</div><span><?= esc($card['rombel_snapshot']) ?> / <?= esc($card['ruang_nama'] ?: 'Belum tersedia') ?></span>
                        <div class="card-label">Sesi</div><span>Ikuti jadwal ujian</span>
                        <div class="card-label">No. Peserta</div><b class="credential"><?= esc($card['nomor_peserta'] ?: 'Belum tersedia') ?></b>
                        <div class="card-label">Username</div><b class="credential"><?= esc($card['username'] ?: 'Belum tersedia') ?></b>
                        <div class="card-label">Password</div><b class="credential"><?= esc($card['password'] ?? 'Belum tersedia') ?></b>
                    </div>
                    <div class="card-url">CBT: <?= esc($identity['cbt_url'] ?: 'URL belum diisi') ?></div>
                </article>
            <?php endforeach ?>
        </section>
    <?php endforeach ?>
    </main>
    <script>
    (() => {
        const scope = document.getElementById('cardScope');
        const rombel = document.getElementById('cardRombel');
        const ruang = document.getElementById('cardRuang');
        const refresh = () => {
            document.getElementById('cardRombelWrap').hidden = scope.value !== 'ROMBEL';
            document.getElementById('cardRuangWrap').hidden = scope.value !== 'RUANG';
            rombel.name = scope.value === 'ROMBEL' ? 'value' : '';
            ruang.name = scope.value === 'RUANG' ? 'value' : '';
        };
        scope.addEventListener('change', refresh); refresh();
    })();
    </script>
</body>
</html>
