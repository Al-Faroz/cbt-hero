(() => {
    'use strict';
    const app = document.getElementById('rekapNilaiApp');
    if (!app) return;

    const els = {
        kegiatan: document.getElementById('rekapKegiatan'),
        rombel: document.getElementById('rekapRombel'),
        load: document.getElementById('rekapLoad'),
        excel: document.getElementById('rekapExcel'),
        pdf: document.getElementById('rekapPdf'),
        feedback: document.getElementById('rekapFeedback'),
        title: document.getElementById('rekapTitle'),
        summary: document.getElementById('rekapSummary'),
        head: document.getElementById('rekapHead'),
        rows: document.getElementById('rekapRows'),
    };

    const esc = value => String(value ?? '').replaceAll('&','&amp;').replaceAll('<','&lt;')
        .replaceAll('>','&gt;').replaceAll('"','&quot;').replaceAll("'",'&#039;');
    const fmt = value => value === null || value === '' || value === undefined
        ? '—' : Number(value).toLocaleString('id-ID', {maximumFractionDigits:2});
    const params = () => {
        const p = new URLSearchParams();
        if (els.kegiatan.value) p.set('kegiatan_id', els.kegiatan.value);
        if (els.rombel.value) p.set('rombel', els.rombel.value);
        return p;
    };
    async function getJson(url) {
        const response = await fetch(url, {headers:{Accept:'application/json'}, credentials:'same-origin'});
        const payload = await response.json().catch(() => ({}));
        if (!response.ok || payload.ok === false) throw new Error(payload.error?.message || 'Data belum dapat dimuat.');
        return payload.data ?? payload;
    }
    function fill(select, rows, label, key='id') {
        const first = select.options[0]?.outerHTML || '<option value="">Pilih</option>';
        select.innerHTML = first + rows.map(r => '<option value="'+esc(r[key])+'">'+esc(label(r))+'</option>').join('');
    }
    async function options() {
        const data = await getJson(app.dataset.optionsApi);
        fill(els.kegiatan, data.kegiatan || [], r => r.nama + ' — ' + r.tahun_pelajaran + ' ' + r.semester);
        fill(els.rombel, data.rombel || [], r => r.nama, 'nama');
    }
    async function load() {
        if (!els.kegiatan.value) {
            els.feedback.textContent = 'Pilih Kegiatan terlebih dahulu.';
            els.feedback.className = 'cbt-inline-feedback mt-2 text-warning';
            return;
        }
        els.feedback.textContent = '';
        els.rows.innerHTML = '<tr><td colspan="4" class="text-center text-secondary py-4">Memuat rekap...</td></tr>';
        try {
            const data = await getJson(app.dataset.api + '?' + params());
            const columns = data.columns || [], rows = data.rows || [];
            const context = data.context || {};
            els.title.textContent = [context.nama, context.tahun_pelajaran, context.semester].filter(Boolean).join(' · ');
            els.summary.textContent = (data.summary?.participant_count || 0) + ' peserta · ' + (data.summary?.subject_count || 0) + ' mata pelajaran';
            const labelCounts = {};
            for (const column of columns) {
                const label = column.nama_mapel || column.nama_bank || 'Mapel';
                labelCounts[label] = (labelCounts[label] || 0) + 1;
            }
            const columnLabel = column => {
                const label = column.nama_mapel || column.nama_bank || 'Mapel';
                return labelCounts[label] > 1 ? label + ' #' + column.root_jadwal_id : label;
            };
            els.head.innerHTML = '<tr><th>No Peserta</th><th>Nama</th><th>Rombel</th>'
                + columns.map(column => '<th>'+esc(columnLabel(column))+'</th>').join('')
                + '<th>Rata-rata</th></tr>';
            if (!rows.length) {
                els.rows.innerHTML = '<tr><td colspan="'+(columns.length+4)+'" class="text-center text-secondary py-4">Belum ada hasil resmi.</td></tr>';
            } else {
                els.rows.innerHTML = rows.map(row => '<tr>'
                    + '<td>'+esc(row.nomor_peserta || '—')+'</td><td><strong>'+esc(row.nama)+'</strong></td><td>'+esc(row.rombel)+'</td>'
                    + columns.map(c => {
                        const score = row.scores?.[String(c.root_jadwal_id)];
                        const badge = score && !score.is_final ? '<span class="badge text-bg-warning ms-1">PROSES</span>' : '';
                        return '<td class="text-end">'+fmt(score?.final_score)+badge+'</td>';
                    }).join('')
                    + '<td class="text-end fw-semibold">'+fmt(row.average)+'</td></tr>').join('');
            }
            els.excel.disabled = false;
            els.pdf.disabled = false;
        } catch (error) {
            els.feedback.textContent = error.message;
            els.feedback.className = 'cbt-inline-feedback mt-2 text-danger';
            els.rows.innerHTML = '<tr><td colspan="4" class="text-center text-danger py-4">Rekap belum dapat dimuat.</td></tr>';
        }
    }
    els.load.addEventListener('click', load);
    els.excel.addEventListener('click', () => { window.location.assign(app.dataset.exportXlsx + '?' + params()); });
    els.pdf.addEventListener('click', () => { window.open(app.dataset.exportPdf + '?' + params(), '_blank', 'noopener'); });
    options().catch(error => {
        els.feedback.textContent = error.message;
        els.feedback.className = 'cbt-inline-feedback mt-2 text-danger';
    });
})();