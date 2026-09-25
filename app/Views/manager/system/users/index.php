<!doctype html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="<?= esc(csrf_hash()) ?>">
    <title>User Manager | CBT-HERO</title>
    <style>
        :root {
            --bg: #f5f7fb;
            --panel: #fff;
            --text: #172033;
            --muted: #667085;
            --line: #e4e7ec;
            --primary: #4f46e5;
            --danger: #b42318;
            --success: #067647;
        }
        * { box-sizing: border-box; }
        body {
            margin: 0;
            font-family: Inter, system-ui, -apple-system, BlinkMacSystemFont, "Segoe UI", sans-serif;
            color: var(--text);
            background: var(--bg);
        }
        .topbar {
            min-height: 64px;
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 16px;
            padding: 0 24px;
            color: #fff;
            background: #101828;
        }
        .brand { font-weight: 800; letter-spacing: .04em; }
        .topbar a { color: #d0d5dd; text-decoration: none; }
        .wrap {
            width: min(1180px, calc(100% - 32px));
            margin: 32px auto;
        }
        .grid {
            display: grid;
            grid-template-columns: minmax(0, 1.45fr) minmax(320px, .55fr);
            gap: 20px;
            align-items: start;
        }
        .card {
            padding: 24px;
            border: 1px solid var(--line);
            border-radius: 16px;
            background: var(--panel);
            box-shadow: 0 10px 30px rgba(16, 24, 40, .04);
        }
        h1, h2 { margin-top: 0; }
        .muted { color: var(--muted); }
        table {
            width: 100%;
            border-collapse: collapse;
        }
        th, td {
            padding: 11px 10px;
            border-bottom: 1px solid var(--line);
            text-align: left;
            vertical-align: top;
            font-size: 14px;
        }
        th {
            color: #475467;
            font-size: 12px;
            text-transform: uppercase;
            letter-spacing: .04em;
        }
        .badge {
            display: inline-block;
            padding: 4px 8px;
            border-radius: 999px;
            background: #eef2ff;
            color: #4338ca;
            font-size: 12px;
            font-weight: 800;
        }
        label {
            display: block;
            margin: 14px 0 6px;
            font-size: 13px;
            font-weight: 700;
        }
        input, select {
            width: 100%;
            min-height: 43px;
            padding: 9px 11px;
            border: 1px solid #d0d5dd;
            border-radius: 9px;
            background: #fff;
            font: inherit;
        }
        button {
            width: 100%;
            min-height: 44px;
            margin-top: 18px;
            border: 0;
            border-radius: 9px;
            color: #fff;
            background: var(--primary);
            font: inherit;
            font-weight: 800;
            cursor: pointer;
        }
        button:disabled { opacity: .65; cursor: wait; }
        .message {
            display: none;
            margin-top: 14px;
            padding: 10px 12px;
            border-radius: 8px;
            font-size: 13px;
            line-height: 1.45;
        }
        .message.error { color: var(--danger); background: #fef3f2; }
        .message.success { color: var(--success); background: #ecfdf3; }
        @media (max-width: 860px) {
            .grid { grid-template-columns: 1fr; }
            .table-wrap { overflow-x: auto; }
        }
    </style>
</head>
<body>
    <header class="topbar">
        <div class="brand">CBT-HERO</div>
        <a href="<?= base_url('manager/dashboard') ?>">← Dashboard</a>
    </header>

    <main class="wrap">
        <h1>User Manager</h1>
        <p class="muted">
            Phase 1B foundation. Halaman ini Admin-only dan dipakai untuk
            membuktikan role/permission server-side. Fitur status/reset password
            lengkap tetap diselesaikan pada modul System.
        </p>

        <div class="grid">
            <section class="card">
                <h2>Daftar Manager</h2>
                <div class="table-wrap">
                    <table>
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
                                    <td><strong><?= esc($user['username']) ?></strong></td>
                                    <td><?= esc($user['nama']) ?></td>
                                    <td><span class="badge"><?= esc($user['role']) ?></span></td>
                                    <td><?= esc($user['status']) ?></td>
                                    <td><?= esc($user['last_login_at'] ?? '-') ?></td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </section>

            <section class="card">
                <h2>Tambah Manager</h2>
                <p class="muted">
                    Gunakan ini untuk membuat akun OPERATOR saat acceptance test.
                </p>

                <form id="userForm">
                    <label for="username">Username</label>
                    <input id="username" maxlength="64" required>

                    <label for="nama">Nama</label>
                    <input id="nama" maxlength="150" required>

                    <label for="role">Role</label>
                    <select id="role">
                        <option value="OPERATOR">OPERATOR</option>
                        <option value="ADMIN">ADMIN</option>
                    </select>

                    <label for="password">Password</label>
                    <input id="password" type="password" minlength="10" required>

                    <button id="submitButton" type="submit">Buat Akun</button>

                    <div id="errorBox" class="message error"></div>
                    <div id="successBox" class="message success"></div>
                </form>
            </section>
        </div>
    </main>

    <script>
    (() => {
        'use strict';

        const form = document.getElementById('userForm');
        const button = document.getElementById('submitButton');
        const errorBox = document.getElementById('errorBox');
        const successBox = document.getElementById('successBox');
        const csrfToken = document.querySelector('meta[name="csrf-token"]').content;

        form.addEventListener('submit', async (event) => {
            event.preventDefault();

            errorBox.style.display = 'none';
            successBox.style.display = 'none';
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

                successBox.textContent = 'Akun Manager berhasil dibuat.';
                successBox.style.display = 'block';

                setTimeout(() => window.location.reload(), 500);
            } catch (error) {
                errorBox.textContent = error.message || 'Akun Manager gagal dibuat.';
                errorBox.style.display = 'block';
            } finally {
                button.disabled = false;
                button.textContent = 'Buat Akun';
            }
        });
    })();
    </script>
</body>
</html>
