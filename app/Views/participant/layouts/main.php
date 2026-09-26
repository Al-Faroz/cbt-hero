<?php
$branding = config('Branding');
$pageTitle = $pageTitle ?? 'Peserta Ujian';
$participant = $participant ?? [];
$participantName = (string) ($participant['nama'] ?? 'Peserta');
$participantUsername = (string) ($participant['username'] ?? '');
$participantRombel = (string) ($participant['rombel'] ?? '');
$initial = mb_strtoupper(mb_substr(trim($participantName) !== '' ? trim($participantName) : 'P', 0, 1));
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
        href="<?= base_url('assets/css/participant-shell.css') ?>"
    >

    <?= $this->renderSection('pageStyles') ?>
</head>

<body class="participant-body">
<header class="participant-topbar">
    <div class="participant-topbar-inner">
        <a
            class="participant-brand"
            href="<?= base_url('ujian') ?>"
            aria-label="CBT-HERO"
        >
            <img
                src="<?= base_url($branding->logoWithText) ?>"
                alt="CBT-HERO"
            >
        </a>

        <div class="dropdown participant-account">
            <button
                class="participant-account-button dropdown-toggle"
                type="button"
                data-bs-toggle="dropdown"
                aria-expanded="false"
            >
                <span class="participant-avatar">
                    <?= esc($initial) ?>
                </span>

                <span class="participant-account-copy">
                    <strong><?= esc($participantName) ?></strong>
                    <span>
                        <?= esc($participantUsername) ?>
                        <?php if ($participantRombel !== ''): ?>
                            · <?= esc($participantRombel) ?>
                        <?php endif; ?>
                    </span>
                </span>
            </button>

            <ul class="dropdown-menu dropdown-menu-end">
                <li class="px-2 py-2">
                    <div class="small fw-semibold text-dark">
                        <?= esc($participantName) ?>
                    </div>
                    <div class="small text-secondary">
                        <?= esc($participantUsername) ?>
                        <?php if ($participantRombel !== ''): ?>
                            · <?= esc($participantRombel) ?>
                        <?php endif; ?>
                    </div>
                </li>

                <li><hr class="dropdown-divider"></li>

                <li>
                    <button
                        class="dropdown-item text-danger"
                        type="button"
                        data-participant-logout
                        data-logout-url="<?= base_url('api/auth/logout') ?>"
                    >
                        Logout
                    </button>
                </li>
            </ul>
        </div>
    </div>
</header>

<main class="participant-main">
    <?= $this->renderSection('content') ?>
</main>

<script src="<?= base_url('assets/vendor/bootstrap/js/bootstrap.bundle.min.js') ?>"></script>
<script src="<?= base_url('assets/js/participant-shell.js') ?>"></script>
<?= $this->renderSection('pageScripts') ?>
</body>
</html>
