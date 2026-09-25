<?php
$auth = $auth ?? session()->get('manager_auth') ?? [];
$pageTitle = $pageTitle ?? 'Manager';
$pageSubtitle = $pageSubtitle ?? 'CBT-HERO Manager';
$activeMenu = $activeMenu ?? '';
$branding = config('Branding');
?>
<!doctype html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="<?= esc(csrf_hash()) ?>">

    <title><?= esc($pageTitle) ?> | CBT-HERO</title>

    <link
        rel="icon"
        type="image/png"
        href="<?= base_url($branding->logo) ?>"
    >

    <script>
    (() => {
        try {
            if (
                window.matchMedia('(min-width: 992px)').matches
                && localStorage.getItem('cbthero.manager.sidebar.collapsed') === '1'
            ) {
                document.documentElement.classList.add('manager-sidebar-collapsed-preset');
            }
        } catch (_) {
            //
        }
    })();
    </script>

    <link
        rel="stylesheet"
        href="<?= base_url('assets/vendor/bootstrap/css/bootstrap.min.css') ?>"
    >
    <link
        rel="stylesheet"
        href="<?= base_url('assets/vendor/bootstrap-icons/font/bootstrap-icons.min.css') ?>"
    >
    <link
        rel="stylesheet"
        href="<?= base_url('assets/css/cbt-hero-theme.css') ?>"
    >
    <link
        rel="stylesheet"
        href="<?= base_url('assets/css/cbt-hero-branding.css') ?>"
    >
    <link
        rel="stylesheet"
        href="<?= base_url('assets/css/manager-shell.css') ?>"
    >
    <link
        rel="stylesheet"
        href="<?= base_url('assets/css/manager-sidebar-patch.css') ?>"
    >
    <link
        rel="stylesheet"
        href="<?= base_url('assets/css/manager-ui-standard.css') ?>"
    >

    <style>
        @media (min-width: 992px) {
            html.manager-sidebar-collapsed-preset .manager-sidebar {
                width: var(--manager-sidebar-collapsed-width);
                --bs-offcanvas-width: var(--manager-sidebar-collapsed-width);
            }

            html.manager-sidebar-collapsed-preset .manager-main {
                margin-left: var(--manager-sidebar-collapsed-width);
            }
        }
    </style>

    <?= $this->renderSection('pageStyles') ?>
</head>

<body class="manager-body">

<?= $this->include('manager/partials/sidebar') ?>

<div class="manager-main">
    <?= $this->include('manager/partials/topbar') ?>

    <main class="manager-content">
        <?= $this->renderSection('content') ?>
    </main>
</div>

<script src="<?= base_url('assets/vendor/bootstrap/js/bootstrap.bundle.min.js') ?>"></script>
<script src="<?= base_url('assets/js/manager-shell.js') ?>"></script>
<?= $this->renderSection('pageScripts') ?>
</body>
</html>
