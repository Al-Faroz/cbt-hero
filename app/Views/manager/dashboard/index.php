<!doctype html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="<?= esc(csrf_hash()) ?>">
    <title>Dashboard Manager | CBT-HERO</title>

    <style>
        :root {
            --bg: #f5f7fb;
            --panel: #fff;
            --text: #172033;
            --muted: #667085;
            --line: #e4e7ec;
            --primary: #4f46e5;
        }

        * { box-sizing: border-box; }

        body {
            margin: 0;
            min-height: 100vh;
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
        .user { color: #d0d5dd; font-size: 14px; }

        .wrap {
            width: min(1120px, calc(100% - 32px));
            margin: 42px auto;
        }

        .card {
            padding: 28px;
            border: 1px solid var(--line);
            border-radius: 16px;
            background: var(--panel);
            box-shadow: 0 12px 36px rgba(16, 24, 40, .06);
        }

        h1 { margin: 0 0 8px; font-size: 30px; }
        p { color: var(--muted); line-height: 1.6; }

        .status {
            display: inline-flex;
            align-items: center;
            gap: 7px;
            margin-top: 12px;
            padding: 7px 10px;
            border-radius: 999px;
            color: #067647;
            background: #ecfdf3;
            font-size: 13px;
            font-weight: 700;
        }

        .status::before {
            content: "";
            width: 8px;
            height: 8px;
            border-radius: 50%;
            background: #12b76a;
        }

        .actions {
            display: flex;
            flex-wrap: wrap;
            gap: 10px;
            margin-top: 24px;
        }

        button, .action-link {
            display: inline-block;
            padding: 10px 15px;
            border: 1px solid #d0d5dd;
            border-radius: 9px;
            color: #344054;
            background: #fff;
            font: inherit;
            font-weight: 700;
            text-decoration: none;
            cursor: pointer;
        }

        .action-link.primary {
            color: #fff;
            border-color: var(--primary);
            background: var(--primary);
        }
    </style>
</head>
<body>
    <header class="topbar">
        <div class="brand">CBT-HERO</div>
        <div class="user">
            <?= esc($auth['nama']) ?> · <?= esc($auth['role']) ?>
        </div>
    </header>

    <main class="wrap">
        <section class="card">
            <h1>Role & Permission Aktif</h1>
            <p>
                Authentication Manager sudah FIX. Pada Phase 1B, authority
                ADMIN/OPERATOR diperiksa kembali oleh server dan permission
                sensitif tidak bergantung pada menu yang terlihat di browser.
                Manager Shell final tetap dikerjakan pada Phase 1C.
            </p>

            <div class="status">SESSION + ROLE VALID</div>

            <p>
                Username: <strong><?= esc($auth['username']) ?></strong><br>
                Role: <strong><?= esc($auth['role']) ?></strong>
            </p>

            <div class="actions">
                <?php if (($auth['role'] ?? '') === 'ADMIN'): ?>
                    <a
                        class="action-link primary"
                        href="<?= base_url('manager/system/users') ?>"
                    >
                        User Manager
                    </a>
                <?php endif; ?>

                <button id="logoutButton" type="button">Logout</button>
            </div>
        </section>
    </main>

    <script>
    (() => {
        'use strict';

        const button = document.getElementById('logoutButton');
        const csrfToken = document.querySelector('meta[name="csrf-token"]').content;

        button.addEventListener('click', async () => {
            button.disabled = true;
            button.textContent = 'Memproses...';

            try {
                const response = await fetch('<?= base_url('manager/api/auth/logout') ?>', {
                    method: 'POST',
                    credentials: 'same-origin',
                    headers: {
                        'Accept': 'application/json',
                        'X-CSRF-TOKEN': csrfToken
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
                button.textContent = 'Logout';
            }
        });
    })();
    </script>
</body>
</html>
