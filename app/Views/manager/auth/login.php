<!doctype html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="<?= esc(csrf_hash()) ?>">
    <title>Manager Login | CBT-HERO</title>

    <style>
        :root {
            --bg: #f5f7fb;
            --panel: #ffffff;
            --text: #172033;
            --muted: #667085;
            --line: #e4e7ec;
            --primary: #4f46e5;
            --primary-hover: #4338ca;
            --danger-bg: #fef3f2;
            --danger: #b42318;
        }

        * { box-sizing: border-box; }

        html, body { min-height: 100%; }

        body {
            margin: 0;
            min-height: 100vh;
            display: grid;
            grid-template-columns: minmax(0, 1.1fr) minmax(380px, .9fr);
            font-family: Inter, system-ui, -apple-system, BlinkMacSystemFont, "Segoe UI", sans-serif;
            color: var(--text);
            background: var(--bg);
        }

        .hero {
            position: relative;
            overflow: hidden;
            display: flex;
            flex-direction: column;
            justify-content: space-between;
            padding: clamp(32px, 5vw, 72px);
            color: #fff;
            background: #101828;
        }

        .hero::after {
            content: "";
            position: absolute;
            width: 420px;
            height: 420px;
            right: -160px;
            bottom: -180px;
            border-radius: 50%;
            background: rgba(79, 70, 229, .38);
        }

        .brand {
            position: relative;
            z-index: 1;
            font-size: 20px;
            font-weight: 800;
            letter-spacing: .04em;
        }

        .hero-copy {
            position: relative;
            z-index: 1;
            max-width: 640px;
            margin: 72px 0;
        }

        .hero-copy h1 {
            margin: 0 0 18px;
            font-size: clamp(36px, 5vw, 64px);
            line-height: 1.04;
            letter-spacing: -.04em;
        }

        .hero-copy p {
            max-width: 560px;
            margin: 0;
            color: #d0d5dd;
            font-size: 17px;
            line-height: 1.65;
        }

        .hero-note {
            position: relative;
            z-index: 1;
            color: #98a2b3;
            font-size: 13px;
        }

        .login-wrap {
            display: grid;
            place-items: center;
            padding: 32px;
        }

        .login-card {
            width: min(100%, 430px);
            background: var(--panel);
            border: 1px solid var(--line);
            border-radius: 18px;
            padding: clamp(26px, 4vw, 38px);
            box-shadow: 0 18px 50px rgba(16, 24, 40, .08);
        }

        .eyebrow {
            margin-bottom: 8px;
            color: var(--primary);
            font-size: 12px;
            font-weight: 800;
            letter-spacing: .12em;
            text-transform: uppercase;
        }

        .login-card h2 {
            margin: 0;
            font-size: 28px;
            letter-spacing: -.02em;
        }

        .subtitle {
            margin: 8px 0 26px;
            color: var(--muted);
            line-height: 1.55;
        }

        label {
            display: block;
            margin: 16px 0 7px;
            font-size: 14px;
            font-weight: 700;
        }

        input {
            width: 100%;
            min-height: 46px;
            padding: 11px 13px;
            border: 1px solid #d0d5dd;
            border-radius: 10px;
            outline: none;
            font: inherit;
            color: var(--text);
            background: #fff;
            transition: border-color .15s ease, box-shadow .15s ease;
        }

        input:focus {
            border-color: var(--primary);
            box-shadow: 0 0 0 4px rgba(79, 70, 229, .11);
        }

        #username { text-transform: uppercase; }

        button {
            width: 100%;
            min-height: 47px;
            margin-top: 22px;
            padding: 11px 16px;
            border: 0;
            border-radius: 10px;
            font: inherit;
            font-weight: 800;
            color: #fff;
            background: var(--primary);
            cursor: pointer;
            transition: background .15s ease, transform .05s ease;
        }

        button:hover { background: var(--primary-hover); }
        button:active { transform: translateY(1px); }
        button:disabled { opacity: .65; cursor: wait; }

        .error {
            display: none;
            margin-top: 16px;
            padding: 11px 12px;
            border: 1px solid #fecdca;
            border-radius: 9px;
            color: var(--danger);
            background: var(--danger-bg);
            font-size: 14px;
            line-height: 1.45;
        }

        .back-link {
            display: inline-block;
            margin-top: 20px;
            color: var(--muted);
            font-size: 13px;
            text-decoration: none;
        }

        .back-link:hover { color: var(--primary); }

        @media (max-width: 820px) {
            body { grid-template-columns: 1fr; }
            .hero { display: none; }
            .login-wrap { min-height: 100vh; padding: 22px; }
            .login-card { box-shadow: none; }
        }
    </style>
</head>
<body>
    <section class="hero" aria-hidden="true">
        <div class="brand">CBT-HERO</div>

        <div class="hero-copy">
            <h1>Manager Console</h1>
            <p>
                Pengelolaan ujian, peserta, bank soal, pelaksanaan,
                monitoring, hasil, dan sistem dalam satu alur kerja.
            </p>
        </div>

        <div class="hero-note">CBT-HERO · CodeIgniter 4</div>
    </section>

    <main class="login-wrap">
        <section class="login-card" aria-labelledby="loginTitle">
            <div class="eyebrow">Manager Area</div>
            <h2 id="loginTitle">Masuk ke CBT-HERO</h2>
            <p class="subtitle">Gunakan akun Admin atau Operator.</p>

            <form id="loginForm" autocomplete="on">
                <label for="username">Username</label>
                <input
                    id="username"
                    name="username"
                    type="text"
                    maxlength="64"
                    autocomplete="username"
                    autocapitalize="characters"
                    required
                    autofocus
                >

                <label for="password">Password</label>
                <input
                    id="password"
                    name="password"
                    type="password"
                    autocomplete="current-password"
                    required
                >

                <button id="submitButton" type="submit">Masuk</button>
                <div id="errorBox" class="error" role="alert"></div>
            </form>

            <a class="back-link" href="<?= base_url('/') ?>">← Halaman peserta</a>
        </section>
    </main>

    <script>
    (() => {
        'use strict';

        const form = document.getElementById('loginForm');
        const button = document.getElementById('submitButton');
        const errorBox = document.getElementById('errorBox');
        const usernameInput = document.getElementById('username');
        const passwordInput = document.getElementById('password');
        const csrfToken = document.querySelector('meta[name="csrf-token"]').content;

        form.addEventListener('submit', async (event) => {
            event.preventDefault();

            errorBox.style.display = 'none';
            errorBox.textContent = '';
            button.disabled = true;
            button.textContent = 'Memproses...';

            try {
                const response = await fetch('<?= base_url('manager/api/auth/login') ?>', {
                    method: 'POST',
                    credentials: 'same-origin',
                    headers: {
                        'Accept': 'application/json',
                        'Content-Type': 'application/json',
                        'X-CSRF-TOKEN': csrfToken
                    },
                    body: JSON.stringify({
                        username: usernameInput.value.trim().toUpperCase(),
                        password: passwordInput.value
                    })
                });

                let result = null;

                try {
                    result = await response.json();
                } catch (_) {
                    throw new Error('Respons server tidak valid.');
                }

                if (! response.ok || result.ok !== true) {
                    throw new Error(result?.error?.message ?? 'Login gagal.');
                }

                window.location.assign(result.data.redirect);
            } catch (error) {
                errorBox.textContent = error.message || 'Login gagal.';
                errorBox.style.display = 'block';
                passwordInput.value = '';
                passwordInput.focus();
            } finally {
                button.disabled = false;
                button.textContent = 'Masuk';
            }
        });
    })();
    </script>
</body>
</html>
