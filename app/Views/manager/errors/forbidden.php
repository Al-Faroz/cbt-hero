<!doctype html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Akses Ditolak | CBT-HERO</title>
    <style>
        * { box-sizing: border-box; }
        body {
            margin: 0;
            min-height: 100vh;
            display: grid;
            place-items: center;
            padding: 24px;
            font-family: Inter, system-ui, -apple-system, BlinkMacSystemFont, "Segoe UI", sans-serif;
            color: #172033;
            background: #f5f7fb;
        }
        .card {
            width: min(100%, 520px);
            padding: 34px;
            border: 1px solid #e4e7ec;
            border-radius: 18px;
            background: #fff;
            box-shadow: 0 16px 44px rgba(16, 24, 40, .07);
        }
        .code {
            color: #b42318;
            font-size: 13px;
            font-weight: 800;
            letter-spacing: .1em;
        }
        h1 { margin: 10px 0 10px; }
        p { color: #667085; line-height: 1.6; }
        a {
            display: inline-block;
            margin-top: 14px;
            padding: 10px 14px;
            border-radius: 9px;
            color: #fff;
            background: #4f46e5;
            font-weight: 700;
            text-decoration: none;
        }
    </style>
</head>
<body>
    <main class="card">
        <div class="code">403 · FORBIDDEN</div>
        <h1>Akses tidak diizinkan</h1>
        <p>
            Akun Manager Anda tidak mempunyai permission untuk membuka fungsi ini.
            Menu yang terlihat di UI bukan sumber authority; izin tetap diperiksa
            oleh server.
        </p>
        <a href="<?= base_url('manager/dashboard') ?>">Kembali ke Dashboard</a>
    </main>
</body>
</html>
