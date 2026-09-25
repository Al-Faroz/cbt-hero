<?php
$portalLabel = $portalLabel ?? 'CBT-HERO';
$heroTitle = $heroTitle ?? 'Ujian digital yang fokus, ringan, dan jelas.';
$heroText = $heroText ?? 'Satu tampilan yang konsisten untuk peserta, operator, dan admin.';
?>
<!doctype html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="<?= esc(csrf_hash()) ?>">

    <title><?= esc($pageTitle ?? 'CBT-HERO') ?></title>

    <link
        rel="stylesheet"
        href="<?= base_url('assets/vendor/bootstrap/css/bootstrap.min.css') ?>"
    >
    <link
        rel="stylesheet"
        href="<?= base_url('assets/css/cbt-hero-theme.css') ?>"
    >
</head>

<body>
<div class="cbt-landing">
    <div class="cbt-landing-shell">

        <section class="cbt-landing-hero">
            <a class="cbt-brand" href="<?= base_url('/') ?>">
                <span class="cbt-brand-mark">H</span>
                <span class="cbt-brand-copy">
                    <span class="cbt-brand-title">CBT-HERO</span>
                    <span class="cbt-brand-subtitle"><?= esc($portalLabel) ?></span>
                </span>
            </a>

            <div class="cbt-landing-hero-copy">
                <div class="cbt-landing-kicker">CBT · CodeIgniter 4</div>

                <h1><?= esc($heroTitle) ?></h1>

                <p><?= esc($heroText) ?></p>

                <div class="cbt-landing-points" aria-label="Karakter CBT-HERO">
                    <span class="cbt-landing-point">Responsive</span>
                    <span class="cbt-landing-point">Operational First</span>
                    <span class="cbt-landing-point">Performance Focused</span>
                </div>
            </div>

            <div class="cbt-landing-hero-foot">
                CBT-HERO · Satu design system untuk seluruh portal
            </div>
        </section>

        <main class="cbt-landing-panel">
            <?= $this->renderSection('landingContent') ?>
        </main>

    </div>
</div>

<script src="<?= base_url('assets/vendor/bootstrap/js/bootstrap.bundle.min.js') ?>"></script>
<?= $this->renderSection('pageScripts') ?>
</body>
</html>
