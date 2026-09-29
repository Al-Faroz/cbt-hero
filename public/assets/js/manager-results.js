(() => {
    'use strict';

    const app = document.getElementById('hasilUjianApp');
    if (!app) return;

    const api = app.dataset.api;
    const optionsApi = app.dataset.optionsApi;
    const els = {
        kegiatan: document.getElementById('hasilKegiatan'),
        jadwal: document.getElementById('hasilJadwal'),
        mapel: document.getElementById('hasilMapel'),
        rombel: document.getElementById('hasilRombel'),
        final: document.getElementById('hasilFinal'),
        search: document.getElementById('hasilSearch'),
        apply: document.getElementById('hasilApply'),
        reset: document.getElementById('hasilReset'),
        rows: document.getElementById('hasilRows'),
        summary: document.getElementById('hasilSummary'),
        feedback: document.getElementById('hasilFeedback'),
        pageInfo: document.getElementById('hasilPageInfo'),
        prev: document.getElementById('hasilPrev'),
        next: document.getElementById('hasilNext'),
        detailTitle: document.getElementById('hasilDetailTitle'),
        detailBody: document.getElementById('hasilDetailBody'),
    };

    const state = { page: 1, pages: 1, options: { jadwal: [] } };
    const modal = new bootstrap.Modal(document.getElementById('hasilDetailModal'));

    const esc = (value) => String(value ?? '')
        .replaceAll('&', '&amp;').replaceAll('<', '&lt;').replaceAll('>', '&gt;')
        .replaceAll('"', '&quot;').replaceAll("'", '&#039;');

    const fmt = (value) => {
        if (value === null || value === '' || value === undefined) return '—';
        const n = Number(value);
        return Number.isFinite(n)
            ? n.toLocaleString('id-ID', { minimumFractionDigits: 0, maximumFractionDigits: 2 })
            : esc(value);
    };

    async function getJson(url) {
        const response = await fetch(url, { headers: { Accept: 'application/json' }, credentials: 'same-origin' });
        const payload = await response.json().catch(() => ({}));
        if (!response.ok || payload.success === false) {
            throw new Error(payload.message || 'Data belum dapat dimuat.');
        }
        return payload.data ?? payload;
    }

    function fill(select, rows, labelFn, valueKey = 'id') {
        const first = select.options[0]?.outerHTML || '<option value="">Semua</option>';
        select.innerHTML = first + rows.map(row =>
            '<option value="' + esc(row[valueKey]) + '">' + esc(labelFn(row)) + '</option>'
        ).join('');
    }

    async function loadOptions() {
        const data = await getJson(optionsApi);
        state.options = data;

        fill(els.kegiatan, data.kegiatan || [], r => r.nama + ' — ' + r.tahun_pelajaran + ' ' + r.semester);
        fill(els.mapel, data.mapel || [], r => r.nama_mapel);
        fill(els.rombel, data.rombel || [], r => r.nama, 'nama');
        refreshJadwal();
    }

    function refreshJadwal() {
        const kegiatanId = els.kegiatan.value;
        const rows = (state.options.jadwal || []).filter(r => !kegiatanId || String(r.kegiatan_id) === kegiatanId);
        fill(els.jadwal, rows, r => (r.nama_mapel || r.nama_bank || 'Jadwal') + ' — ' + (r.mulai_at || ''));
    }

    function params() {
        const p = new URLSearchParams();
        p.set('page', state.page);
        p.set('per_page', 25);
        if (els.kegiatan.value) p.set('kegiatan_id', els.kegiatan.value);
        if (els.jadwal.value) p.set('jadwal_id', els.jadwal.value);
        if (els.mapel.value) p.set('mapel_id', els.mapel.value);
        if (els.rombel.value) p.set('rombel', els.rombel.value);
        if (els.final.value !== '') p.set('final', els.final.value);
        if (els.search.value.trim()) p.set('q', els.search.value.trim());
        return p;
    }

    async function loadResults() {
        els.feedback.textContent = '';
        els.rows.innerHTML = '<tr><td colspan="9" class="text-center text-secondary py-4">Memuat data hasil...</td></tr>';

        try {
            const data = await getJson(api + '?' + params().toString());
            const items = data.items || [];
            const pg = data.pagination || {};
            state.page = Number(pg.page || 1);
            state.pages = Number(pg.pages || 1);

            els.summary.textContent = (pg.total || 0) + ' hasil resmi';
            els.pageInfo.textContent = 'Halaman ' + state.page + ' dari ' + state.pages;
            els.prev.disabled = state.page <= 1;
            els.next.disabled = state.page >= state.pages;

            if (!items.length) {
                els.rows.innerHTML = '<tr><td colspan="9" class="text-center text-secondary py-4">Belum ada hasil resmi yang sesuai filter.</td></tr>';
                return;
            }

            els.rows.innerHTML = items.map(row => {
                const status = row.is_final
                    ? '<span class="badge text-bg-success">FINAL</span>'
                    : '<span class="badge text-bg-warning">BELUM FINAL</span>';
                return '<tr>'
                    + '<td>' + esc(row.nomor_peserta_snapshot || '—') + '</td>'
                    + '<td><strong>' + esc(row.nama_snapshot) + '</strong></td>'
                    + '<td>' + esc(row.rombel_snapshot) + '</td>'
                    + '<td>' + esc(row.nama_mapel || row.nama_bank || '—') + '</td>'
                    + '<td>' + fmt(row.click_score) + '</td>'
                    + '<td>' + fmt(row.typed_score) + '</td>'
                    + '<td><strong>' + fmt(row.final_score) + '</strong></td>'
                    + '<td>' + status + '</td>'
                    + '<td><button class="btn btn-outline-primary btn-sm" data-result-detail="' + esc(row.result_snapshot_id) + '">Detail</button></td>'
                    + '</tr>';
            }).join('');
        } catch (err) {
            els.rows.innerHTML = '<tr><td colspan="9" class="text-center text-danger py-4">Gagal memuat hasil.</td></tr>';
            els.feedback.textContent = err.message;
            els.feedback.className = 'cbt-inline-feedback mt-2 text-danger';
        }
    }

    async function showDetail(id) {
        els.detailTitle.textContent = 'Hasil Peserta';
        els.detailBody.innerHTML = '<div class="text-secondary py-4 text-center">Memuat detail...</div>';
        modal.show();

        try {
            const data = await getJson(api + '/' + encodeURIComponent(id));
            const r = data.result || {};
            const items = data.items || [];
            els.detailTitle.textContent = (r.nama_snapshot || 'Peserta') + ' — ' + (r.nama_mapel || r.nama_bank || 'Hasil');

            els.detailBody.innerHTML =
                '<div class="row g-3 mb-3">'
                + card('No Peserta', r.nomor_peserta_snapshot || '—')
                + card('Rombel', r.rombel_snapshot || '—')
                + card('Nilai Klik', fmt(r.click_score))
                + card('Nilai Ketik', fmt(r.typed_score))
                + card('Nilai Akhir', fmt(r.final_score))
                + card('Status', r.is_final ? 'FINAL' : 'BELUM FINAL')
                + '</div>'
                + '<div class="table-responsive"><table class="table manager-table align-middle mb-0">'
                + '<thead><tr><th>No</th><th>Tipe</th><th>Skor</th><th>Maks.</th><th>Bobot</th><th>Kontribusi</th><th>Status</th></tr></thead>'
                + '<tbody>' + (items.length ? items.map(i =>
                    '<tr><td>' + esc(i.sequence_no) + '</td><td>' + esc(i.question_type || '—') + '</td>'
                    + '<td>' + fmt(i.raw_score) + '</td><td>' + fmt(i.max_point) + '</td>'
                    + '<td>' + fmt(i.type_weight_percent) + '%</td><td>' + fmt(i.weighted_score) + '</td>'
                    + '<td>' + (i.voided ? '<span class="badge text-bg-secondary">VOID</span>' : 'Aktif') + '</td></tr>'
                ).join('') : '<tr><td colspan="7" class="text-center text-secondary py-4">Tidak ada item hasil.</td></tr>') + '</tbody>'
                + '</table></div>';
        } catch (err) {
            els.detailBody.innerHTML = '<div class="alert alert-danger mb-0">' + esc(err.message) + '</div>';
        }
    }

    function card(label, value) {
        return '<div class="col-lg-2 col-md-4 col-6"><div class="border rounded-3 p-3 h-100">'
            + '<div class="small text-secondary">' + esc(label) + '</div><div class="fw-semibold mt-1">' + esc(value) + '</div>'
            + '</div></div>';
    }

    els.kegiatan.addEventListener('change', () => { refreshJadwal(); });
    els.apply.addEventListener('click', () => { state.page = 1; loadResults(); });
    els.reset.addEventListener('click', () => {
        [els.kegiatan, els.jadwal, els.mapel, els.rombel, els.final].forEach(el => el.value = '');
        els.search.value = '';
        state.page = 1;
        refreshJadwal();
        loadResults();
    });
    els.prev.addEventListener('click', () => { if (state.page > 1) { state.page--; loadResults(); } });
    els.next.addEventListener('click', () => { if (state.page < state.pages) { state.page++; loadResults(); } });
    els.search.addEventListener('keydown', e => { if (e.key === 'Enter') { state.page = 1; loadResults(); } });
    els.rows.addEventListener('click', e => {
        const button = e.target.closest('[data-result-detail]');
        if (button) showDetail(button.dataset.resultDetail);
    });

    (async () => {
        try {
            await loadOptions();
            await loadResults();
        } catch (err) {
            els.feedback.textContent = err.message;
            els.feedback.className = 'cbt-inline-feedback mt-2 text-danger';
        }
    })();
})();
