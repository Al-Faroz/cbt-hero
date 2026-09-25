<?= $this->extend('layouts/landing') ?>

<?= $this->section('landingContent') ?>
<section class="cbt-login-card" aria-labelledby="managerLoginTitle">
    <div class="cbt-login-eyebrow">Admin / Operator</div>

    <h2 id="managerLoginTitle">Masuk ke CBT-HERO</h2>

    <p class="cbt-login-intro">
        Akses Manager untuk mengelola persiapan, pelaksanaan, monitoring,
        dan hasil ujian.
    </p>

    <form
        data-manager-login-form
        data-login-url="<?= base_url('manager/api/auth/login') ?>"
        autocomplete="on"
    >
        <div class="mb-3">
            <label class="cbt-form-label" for="managerUsername">Username</label>
            <input
                class="form-control text-uppercase"
                id="managerUsername"
                name="username"
                type="text"
                maxlength="64"
                autocomplete="username"
                autocapitalize="characters"
                required
                autofocus
            >
        </div>

        <div class="mb-2">
            <label class="cbt-form-label" for="managerPassword">Password</label>
            <input
                class="form-control"
                id="managerPassword"
                name="password"
                type="password"
                autocomplete="current-password"
                required
            >
        </div>

        <button class="btn btn-cbt-primary w-100 mt-3" type="submit">
            Masuk Manager
        </button>

        <div
            class="cbt-inline-feedback"
            data-login-feedback
            role="alert"
        ></div>
    </form>

    <div class="cbt-login-meta">
        <span>Portal Admin / Operator</span>
        <a href="<?= base_url('/') ?>">Masuk sebagai Peserta →</a>
    </div>
</section>
<?= $this->endSection() ?>

<?= $this->section('pageScripts') ?>
<script src="<?= base_url('assets/js/manager-auth.js') ?>"></script>
<?= $this->endSection() ?>
