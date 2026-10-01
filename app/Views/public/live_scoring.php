<?php
$branding = config('Branding');
$initialJson = json_encode($initial ?? [], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_AMP | JSON_HEX_QUOT);
$snapshotJson = json_encode($snapshotUrl ?? '', JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_AMP | JSON_HEX_QUOT);
?>
<!doctype html>
<html lang="id">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width,initial-scale=1">
<meta name="referrer" content="no-referrer">
<meta name="robots" content="noindex,nofollow,noarchive">
<title>Live Scoring | CBT-HERO</title>
<style>
:root{color-scheme:dark}
*{box-sizing:border-box}
html,body{height:100%;margin:0}
body{font-family:Arial,sans-serif;background:#07111f;color:#f8fafc;overflow:hidden}
.live-shell{height:100%;display:grid;grid-template-rows:auto minmax(0,1fr);padding:18px 22px;gap:14px}
.live-header{display:flex;align-items:center;justify-content:space-between;gap:20px;border-bottom:1px solid rgba(255,255,255,.16);padding-bottom:14px}
.live-brand{display:flex;align-items:center;gap:14px;min-width:0}.live-brand img{width:46px;height:46px;object-fit:contain}
.live-copy{min-width:0}.live-kicker{font-size:12px;letter-spacing:.13em;text-transform:uppercase;color:#93c5fd;font-weight:700}
.live-title{margin:3px 0 0;font-size:clamp(22px,2.4vw,38px);white-space:nowrap;overflow:hidden;text-overflow:ellipsis}
.live-meta{text-align:right;color:#cbd5e1;font-size:clamp(12px,1.2vw,16px)}
.live-table-wrap{min-height:0;overflow:hidden;border:1px solid rgba(255,255,255,.14);border-radius:16px;background:rgba(15,23,42,.72)}
table{width:100%;border-collapse:collapse;table-layout:fixed}
thead{position:sticky;top:0;z-index:2;background:#142238}
th,td{padding:13px 14px;border-bottom:1px solid rgba(255,255,255,.09);font-size:clamp(14px,1.45vw,24px)}
th{text-align:left;font-size:clamp(12px,1.15vw,18px);text-transform:uppercase;letter-spacing:.05em;color:#bfdbfe}
th:nth-child(1),td:nth-child(1){width:8%;text-align:center}
th:nth-child(2),td:nth-child(2){width:18%}
th:nth-child(4),td:nth-child(4){width:14%;text-align:right;font-variant-numeric:tabular-nums;font-weight:800}
th:nth-child(5),td:nth-child(5){width:17%;text-align:center}
td:nth-child(3){font-weight:700;overflow-wrap:anywhere}
tr:last-child td{border-bottom:0}
.status{display:inline-flex;padding:6px 10px;border-radius:999px;font-size:.75em;font-weight:800;letter-spacing:.03em}
.status.active{background:#1d4ed8;color:#dbeafe}.status.done{background:#166534;color:#dcfce7}
.empty{display:grid;place-items:center;height:100%;min-height:300px;color:#94a3b8;text-align:center;padding:30px;font-size:clamp(16px,1.8vw,26px)}
.live-error{color:#fecaca}
@media(max-width:700px){.live-shell{padding:10px;gap:9px}.live-header{align-items:flex-start}.live-brand img{width:34px;height:34px}.live-meta{font-size:10px}th,td{padding:9px 7px}th:nth-child(1),td:nth-child(1){width:10%}th:nth-child(2),td:nth-child(2){width:23%}th:nth-child(4),td:nth-child(4){width:16%}th:nth-child(5),td:nth-child(5){width:21%}}
</style>
</head>
<body>
<div class="live-shell">
    <header class="live-header">
        <div class="live-brand">
            <img src="<?= base_url($branding->logo) ?>" alt="">
            <div class="live-copy">
                <div class="live-kicker">CBT-HERO · Live Scoring</div>
                <h1 class="live-title" id="livePublicTitle">Live Scoring</h1>
            </div>
        </div>
        <div class="live-meta">
            <div id="livePublicActivity">—</div>
            <div id="livePublicUpdated">Memuat snapshot...</div>
        </div>
    </header>
    <div class="live-table-wrap" id="livePublicViewport">
        <table id="livePublicTable">
            <thead><tr><th>No</th><th>No Peserta</th><th>Nama</th><th>Skor</th><th>Status</th></tr></thead>
            <tbody id="livePublicRows"></tbody>
        </table>
        <div class="empty" id="livePublicEmpty" hidden>Belum ada peserta yang mengerjakan.</div>
    </div>
</div>
<script>
(() => {
    'use strict';
    const initial = <?= $initialJson ?: '{}' ?>;
    const snapshotUrl = <?= $snapshotJson ?: '""' ?>;
    const viewport = document.getElementById('livePublicViewport');
    const table = document.getElementById('livePublicTable');
    const rowsNode = document.getElementById('livePublicRows');
    const empty = document.getElementById('livePublicEmpty');
    const title = document.getElementById('livePublicTitle');
    const activity = document.getElementById('livePublicActivity');
    const updated = document.getElementById('livePublicUpdated');
    let stopped = false;

    const fmt = value => Number(value || 0).toLocaleString('id-ID', {minimumFractionDigits:0, maximumFractionDigits:2});
    const esc = value => String(value ?? '').replaceAll('&','&amp;').replaceAll('<','&lt;').replaceAll('>','&gt;')
        .replaceAll('"','&quot;').replaceAll("'",'&#039;');
    const sleep = ms => new Promise(resolve => setTimeout(resolve, ms));

    function render(data) {
        const schedule = data.schedule || {};
        const rows = Array.isArray(data.rows) ? data.rows : [];
        title.textContent = schedule.ujian || 'Live Scoring';
        activity.textContent = [schedule.kegiatan, schedule.jenis_jadwal].filter(Boolean).join(' · ');
        updated.textContent = 'Snapshot ' + new Date(data.generated_at || Date.now()).toLocaleTimeString('id-ID');
        rowsNode.innerHTML = rows.map(row => '<tr>'
            + '<td>'+esc(row.no)+'</td><td>'+esc(row.no_peserta || '—')+'</td><td>'+esc(row.nama)+'</td>'
            + '<td>'+fmt(row.skor)+'</td><td><span class="status '+(row.status==='SELESAI'?'done':'active')+'">'+esc(row.status)+'</span></td>'
            + '</tr>').join('');
        const hasRows = rows.length > 0;
        table.hidden = !hasRows;
        empty.hidden = hasRows;
        viewport.scrollTop = 0;
    }

    async function fetchSnapshot() {
        const response = await fetch(snapshotUrl, {headers:{Accept:'application/json'}, cache:'no-store'});
        const payload = await response.json().catch(() => ({}));
        if (!response.ok || payload.ok === false) throw new Error(payload.error?.message || 'Live Scoring dihentikan.');
        return payload.data ?? payload;
    }

    async function scrollCycle() {
        await sleep(2200);
        const max = Math.max(0, viewport.scrollHeight - viewport.clientHeight);
        if (max <= 0) {
            await sleep(5200);
            return;
        }
        const speed = 34;
        const start = performance.now();
        await new Promise(resolve => {
            const step = now => {
                if (stopped) return resolve();
                const next = Math.min(max, ((now - start) / 1000) * speed);
                viewport.scrollTop = next;
                if (next >= max) return resolve();
                requestAnimationFrame(step);
            };
            requestAnimationFrame(step);
        });
        await sleep(1800);
    }

    async function loop(data) {
        render(data);
        while (!stopped) {
            await scrollCycle();
            if (stopped) break;
            try {
                const next = await fetchSnapshot();
                render(next);
            } catch (error) {
                stopped = true;
                table.hidden = true;
                empty.hidden = false;
                empty.classList.add('live-error');
                empty.textContent = error.message || 'Live Scoring dihentikan.';
                updated.textContent = 'Public display tidak aktif';
            }
        }
    }

    loop(initial);
})();
</script>
</body>
</html>
