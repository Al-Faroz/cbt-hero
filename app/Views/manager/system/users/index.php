<?= $this->extend('manager/layouts/main') ?>

<?= $this->section('content') ?>

<section class="manager-page-header">
    <div>
        <div class="manager-page-kicker">Sistem</div>
        <h1 class="manager-page-title">User Manager</h1>
        <p class="manager-page-description">
            Account Admin dan Operator. Phase 1B tetap menjadi authority;
            Phase 1C hanya memindahkan halaman ke Manager Shell yang konsisten.
        </p>
    </div>

    <span class="cbt-badge">ADMIN ONLY</span>
</section>

<div class="row g-3 align-items-start">
    <div class="col-xl-8">
        <section class="manager-section-card">
            <div class="card-header">
                <div class="fw-bold">Daftar Manager</div>
            </div>

            <div class="table-responsive">
                <table class="table manager-table">
                    <thead>
                        <tr>
                            <th>Username</th>
                            <th>Nama</th>
                            <th>Role</th>
                            <th>Status</th>
                            <th>Login Terakhir</th>
                        </tr>
                    </thead>

                    <tbody>
                        <?php foreach ($users as $user): ?>
                            <tr>
                                <td class="fw-semibold"><?= esc($user['username']) ?></td>
                                <td><?= esc($user['nama']) ?></td>
                                <td><span class="cbt-badge"><?= esc($user['role']) ?></span></td>
                                <td><?= esc($user['status']) ?></td>
                                <td><?= esc($user['last_login_at'] ?? '-') ?></td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </section>
    </div>

    <div class="col-xl-4">
        <section class="manager-section-card">
            <div class="card-header">
                <div class="fw-bold">Tambah Manager</div>
                <div class="small text-secondary mt-1">
                    Foundation LIST + CREATE dari Phase 1B.
                </div>
            </div>

            <div class="card-body">
                <form id="userForm">
                    <div class="mb-3">
                        <label class="cbt-form-label" for="username">Username</label>
                        <input
                            class="form-control text-uppercase"
                            id="username"
                            maxlength="64"
                            required
                        >
                    </div>

                    <div class="mb-3">
                        <label class="cbt-form-label" for="nama">Nama</label>
                        <input
                            class="form-control"
                            id="nama"
                            maxlength="150"
                            required
                        >
                    </div>

                    <div class="mb-3">
                        <label class="cbt-form-label" for="role">Role</label>
                        <select class="form-select" id="role">
                            <option value="OPERATOR">OPERATOR</option>
                            <option value="ADMIN">ADMIN</option>
                        </select>
                    </div>

                    <div class="mb-2">
                        <label class="cbt-form-label" for="password">Password</label>
                        <input
                            class="form-control"
                            id="password"
                            type="password"
                            minlength="10"
                            required
                        >
                    </div>

                    <button class="btn btn-cbt-primary w-100 mt-2" id="submitButton" type="submit">
                        Buat Akun
                    </button>

                    <div id="userFeedback" class="cbt-inline-feedback" role="alert"></div>
                </form>
            </div>
        </section>
    </div>
</div>

<?= $this->endSection() ?>

<?= $this->section('pageScripts') ?>
<script>
(() => {
    'use strict';

    const form = document.getElementById('userForm');
    const button = document.getElementById('submitButton');
    const feedback = document.getElementById('userFeedback');
    const csrfToken = document.querySelector('meta[name="csrf-token"]').content;

    form.addEventListener('submit', async (event) => {
        event.preventDefault();

        feedback.className = 'cbt-inline-feedback';
        feedback.textContent = '';
        button.disabled = true;
        button.textContent = 'Memproses...';

        try {
            const response = await fetch('<?= base_url('manager/api/users') ?>', {
                method: 'POST',
                credentials: 'same-origin',
                headers: {
                    'Accept': 'application/json',
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': csrfToken
                },
                body: JSON.stringify({
                    username: document.getElementById('username').value.trim().toUpperCase(),
                    nama: document.getElementById('nama').value.trim(),
                    role: document.getElementById('role').value,
                    password: document.getElementById('password').value
                })
            });

            const result = await response.json();

            if (! response.ok || result.ok !== true) {
                const fields = result?.error?.fields ?? {};
                const fieldMessage = Object.values(fields)[0];

                throw new Error(
                    fieldMessage
                    ?? result?.error?.message
                    ?? 'Akun Manager gagal dibuat.'
                );
            }

            feedback.textContent = 'Akun Manager berhasil dibuat.';
            feedback.className = 'cbt-inline-feedback is-info';

            window.setTimeout(() => window.location.reload(), 450);
        } catch (error) {
            feedback.textContent = error.message || 'Akun Manager gagal dibuat.';
            feedback.className = 'cbt-inline-feedback is-error';
        } finally {
            button.disabled = false;
            button.textContent = 'Buat Akun';
        }
    });
})();
</script>
<?= $this->endSection() ?>
