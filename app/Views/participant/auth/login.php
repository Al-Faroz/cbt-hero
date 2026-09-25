<?= $this->extend('layouts/landing') ?>

<?= $this->section('landingContent') ?>
<section class="cbt-login-card" aria-labelledby="participantLoginTitle">
    <div class="cbt-login-eyebrow">Peserta Ujian</div>

    <h2 id="participantLoginTitle">Masuk ke CBT-HERO</h2>

    <p class="cbt-login-intro">
        Masukkan Username dan Password yang tertera pada kartu peserta
        untuk mengakses ujian.
    </p>

    <form data-participant-ui-form autocomplete="on">
        <div class="mb-3">
            <label class="cbt-form-label" for="participantUsername">Username</label>
            <input
                class="form-control text-uppercase"
                id="participantUsername"
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
            <label class="cbt-form-label" for="participantPassword">Password</label>
            <input
                class="form-control"
                id="participantPassword"
                name="password"
                type="password"
                autocomplete="current-password"
                required
            >
        </div>

        <button class="btn btn-cbt-primary w-100 mt-3" type="submit">
            Masuk Ujian
        </button>

        <div
            class="cbt-inline-feedback"
            data-participant-feedback
            role="status"
        ></div>
    </form>

    <div class="cbt-login-meta">
        <span>Portal Peserta</span>
        <a href="<?= base_url('manager') ?>">Admin / Operator →</a>
    </div>
</section>
<?= $this->endSection() ?>

<?= $this->section('pageScripts') ?>
<script src="<?= base_url('assets/js/participant-landing.js') ?>"></script>
<?= $this->endSection() ?>
