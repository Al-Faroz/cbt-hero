<?php
$branding = config('Branding');
?>
<!doctype html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="<?= esc(csrf_hash()) ?>">

    <title>Daftar Ujian | CBT-HERO</title>

    <link rel="icon" type="image/png" href="<?= base_url($branding->logo) ?>">
    <link rel="stylesheet" href="<?= base_url('assets/vendor/bootstrap/css/bootstrap.min.css') ?>">
    <link rel="stylesheet" href="<?= base_url('assets/css/cbt-hero-theme.css') ?>">
    <link rel="stylesheet" href="<?= base_url('assets/css/cbt-hero-branding.css') ?>">

    <style>
        body {
            background:
                radial-gradient(circle at 10% 0%, rgba(91,95,242,.10), transparent 30%),
                var(--cbt-bg);
        }

        .participant-checkpoint {
            width: min(100% - 28px, 720px);
            margin: 0 auto;
            padding: 28px 0;
        }

        .participant-checkpoint-brand {
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 16px;
            margin-bottom: 22px;
        }

        .participant-checkpoint-logo {
            width: 170px;
            height: auto;
        }

        .participant-checkpoint-card {
            padding: 24px;
        }

        .participant-checkpoint h1 {
            margin: 0 0 8px;
            font-size: clamp(25px, 5vw, 34px);
            font-weight: 820;
            letter-spacing: -.03em;
        }

        @media (max-width: 560px) {
            .participant-checkpoint {
                padding-top: 18px;
            }

            .participant-checkpoint-logo {
                width: 145px;
            }

            .participant-checkpoint-card {
                padding: 20px;
            }
        }
    </style>
</head>

<body>
<main class="participant-checkpoint">
    <div class="participant-checkpoint-brand">
        <img
            class="participant-checkpoint-logo"
            src="<?= base_url($branding->logoWithText) ?>"
            alt="CBT-HERO"
        >

        <span class="cbt-badge cbt-badge-success">AUTH OK</span>
    </div>

    <section class="cbt-card participant-checkpoint-card">
        <div class="cbt-login-eyebrow">Peserta Ujian</div>

        <h1>Autentikasi berhasil</h1>

        <p class="cbt-muted mb-4">
            Username <strong><?= esc($auth['username']) ?></strong> sudah masuk ke
            Participant Realm. Daftar Ujian final akan dibangun pada Phase 1E.
        </p>

        <div class="d-grid gap-2 d-sm-flex">
            <button
                class="btn btn-cbt-primary"
                type="button"
                data-participant-logout
                data-logout-url="<?= base_url('api/auth/logout') ?>"
            >
                Logout Peserta
            </button>

            <a class="btn btn-outline-secondary" href="<?= base_url('manager') ?>">
                Manager
            </a>
        </div>
    </section>
</main>

<script src="<?= base_url('assets/vendor/bootstrap/js/bootstrap.bundle.min.js') ?>"></script>
<script>
(() => {
    'use strict';

    const button = document.querySelector('[data-participant-logout]');
    const csrf = document.querySelector('meta[name="csrf-token"]').content;

    button.addEventListener('click', async () => {
        button.disabled = true;

        try {
            const response = await fetch(button.dataset.logoutUrl, {
                method: 'POST',
                credentials: 'same-origin',
                headers: {
                    'Accept': 'application/json',
                    'X-CSRF-TOKEN': csrf
                }
            });

            const result = await response.json();

            if (! response.ok || result.ok !== true) {
                throw new Error(result?.error?.message ?? 'Logout gagal.');
            }

            window.location.assign(result.data.redirect);
        } catch (error) {
            alert(error.message || 'Logout gagal.');
            button.disabled = false;
        }
    });
})();
</script>
</body>
</html>
