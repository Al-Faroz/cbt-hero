<?php
$branding = config('Branding');
$pageTitle = $pageTitle ?? $branding->appName;
$portalLabel = $portalLabel ?? $branding->productName;
?>
<!doctype html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="<?= esc(csrf_hash()) ?>">

    <title><?= esc($pageTitle) ?></title>

    <link
        rel="icon"
        type="image/png"
        href="<?= base_url($branding->logo) ?>"
    >

    <link
        rel="stylesheet"
        href="<?= base_url('assets/vendor/bootstrap/css/bootstrap.min.css') ?>"
    >
    <link
        rel="stylesheet"
        href="<?= base_url('assets/css/cbt-hero-theme.css') ?>"
    >
    <link
        rel="stylesheet"
        href="<?= base_url('assets/css/cbt-hero-branding.css') ?>"
    >
</head>

<body>
<div class="cbt-landing">
    <div class="cbt-landing-shell">

        <section class="cbt-landing-hero">
            <a
                class="cbt-landing-brand"
                href="<?= base_url('/') ?>"
                aria-label="<?= esc($branding->appName) ?>"
            >
                <img
                    class="cbt-brand-image-full"
                    src="<?= base_url($branding->logoWithText) ?>"
                    alt="<?= esc($branding->appName) ?>"
                >
            </a>

            <div class="cbt-landing-hero-copy">
                <div class="cbt-landing-kicker">
                    <?= esc($branding->productName) ?>
                </div>

                <h1><?= esc($branding->tagline) ?></h1>

                <p><?= esc($branding->description) ?></p>

                <div class="cbt-landing-points" aria-label="Karakter CBT-HERO">
                    <span class="cbt-landing-point">Ringan</span>
                    <span class="cbt-landing-point">Stabil</span>
                    <span class="cbt-landing-point">Siap Skala Besar</span>
                </div>
            </div>

            <div class="cbt-landing-hero-foot">
                <?= esc($branding->footer) ?>
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
