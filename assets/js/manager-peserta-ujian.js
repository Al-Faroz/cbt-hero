(() => {
    'use strict';
    const app = document.getElementById('pesertaUjianApp');
    if (!app) return;
    const $ = (id) => document.getElementById(id);
    const base = app.dataset.api;
    const state = {draft: false, members: {page: 1, pages: 1, seq: 0},
        candidates: {page: 1, pages: 1, seq: 0, ids: new Set()}};
    let memberDebounce, candidateDebounce;
    const feedback = (id, message, error = false) => {
        const node = $(id); node.textContent = message;
        node.className = 'cbt-inline-feedback' + (message ? (error ? ' is-error' : ' is-info') : '');
    };
    const api = async (url, method = 'GET', payload = null) => {
        const headers = {'Accept': 'application/json'};
        if (method !== 'GET') headers['X-CSRF-TOKEN'] = document.querySelector('meta[name="csrf-token"]').content;
        if (payload !== null) headers['Content-Type'] = 'application/json';
        const response = await fetch(url, {method, credentials: 'same-origin', headers,
            ...(payload === null ? {} : {body: JSON.stringify(payload)})});
        const result = await response.json().catch(() => null);
        if (!response.ok || result?.ok !== true) throw new Error(result?.error?.message ?? 'Permintaan gagal.');
        return result.data;
    };
    const cell = (value) => {
        const td = document.createElement('td');
        td.textContent = value == null || value === '' ? '-' : String(value);
        return td;
    };
    const empty = (body, count, message) => {
        const tr = document.createElement('tr'), td = cell(message);
        td.colSpan = count; td.className = 'text-center text-secondary py-4'; tr.append(td); body.append(tr);
    };
    const updateSelectAll = () => {
        const boxes = [...$('pesertaUjianCandidateRows').querySelectorAll('input[type="checkbox"]')];
        $('pesertaUjianSelectAll').checked = boxes.length > 0 && boxes.every((node) => node.checked);
        $('pesertaUjianSelectAll').indeterminate = boxes.some((node) => node.checked) && !boxes.every((node) => node.checked);
    };
    const loadMembers = async () => {
        const s = state.members, seq = ++s.seq;
        feedback('pesertaUjianFeedback', 'Memuat anggota...');
        const params = new URLSearchParams({page: s.page, per_page: $('pesertaUjianPageSize').value,
            q: $('pesertaUjianSearch').value.trim()});
        try {
            const data = await api(base + '?' + params);
            if (seq !== s.seq) return;
            state.draft = data.kegiatan.status === 'DRAFT';
            $('pesertaUjianContext').textContent = data.kegiatan.nama + ' · ' + data.kegiatan.jenis +
                ' · ' + data.kegiatan.tahun_pelajaran + ' / ' + data.kegiatan.semester +
                ' · ' + data.kegiatan.status;
            $('pesertaUjianAssignCard').hidden = !state.draft;
            const body = $('pesertaUjianRows'); body.replaceChildren();
            for (const item of data.items) {
                const row = document.createElement('tr');
                row.append(cell(item.nisn_snapshot), cell(item.nama_snapshot),
                    cell(item.jenis_kelamin_snapshot), cell(item.rombel_snapshot),
                    cell(item.nomor_peserta), cell(item.ruang_id));
                const actions = document.createElement('td');
                if (state.draft) {
                    const remove = document.createElement('button');
                    remove.type = 'button'; remove.className = 'btn btn-outline-danger btn-sm';
                    remove.textContent = 'Hapus';
                    remove.addEventListener('click', async () => {
                        if (!window.confirm('Hapus ' + item.nama_snapshot + ' dari Kegiatan ini?')) return;
                        remove.disabled = true;
                        try {
                            await api(base + '/' + item.id, 'DELETE');
                            await loadMembers();
                            if ($('pesertaUjianSelector').value === 'IDS') await loadCandidates();
                            feedback('pesertaUjianFeedback', 'Keanggotaan dihapus.');
                        } catch (error) {feedback('pesertaUjianFeedback', error.message, true); remove.disabled = false;}
                    });
                    actions.append(remove);
                } else actions.textContent = 'Terkunci';
                row.append(actions); body.append(row);
            }
            if (!data.items.length) empty(body, 7, 'Belum ada Peserta dalam Kegiatan ini.');
            s.page = Number(data.pagination.page); s.pages = Number(data.pagination.pages);
            $('pesertaUjianCount').textContent = data.pagination.filtered + ' dari ' + data.pagination.total + ' anggota';
            $('pesertaUjianPageInfo').textContent = s.page + ' / ' + s.pages;
            $('pesertaUjianPrevious').disabled = s.page <= 1;
            $('pesertaUjianNext').disabled = s.page >= s.pages;
            feedback('pesertaUjianFeedback', '');
        } catch (error) {if (seq === s.seq) feedback('pesertaUjianFeedback', error.message, true);}
    };
    const loadCandidates = async () => {
        const s = state.candidates, seq = ++s.seq;
        s.ids.clear(); $('pesertaUjianSelectAll').checked = false;
        $('pesertaUjianSelectAll').indeterminate = false;
        feedback('pesertaUjianCandidateFeedback', 'Memuat Peserta...');
        const params = new URLSearchParams({page: s.page, per_page: $('pesertaUjianCandidateSize').value,
            q: $('pesertaUjianCandidateSearch').value.trim(),
            rombel_id: $('pesertaUjianCandidateRombel').value});
        try {
            const data = await api(base + '/candidates?' + params);
            if (seq !== s.seq) return;
            const body = $('pesertaUjianCandidateRows'); body.replaceChildren();
            for (const item of data.items) {
                const row = document.createElement('tr'), first = document.createElement('td');
                row.dataset.id = item.id;
                const checkbox = document.createElement('input');
                checkbox.type = 'checkbox'; checkbox.className = 'form-check-input';
                checkbox.setAttribute('aria-label', 'Pilih ' + item.nama);
                checkbox.addEventListener('change', () => {
                    if (checkbox.checked) s.ids.add(Number(item.id));
                    else s.ids.delete(Number(item.id));
                    updateSelectAll();
                });
                first.append(checkbox);
                row.append(first, cell(item.nisn), cell(item.nama),
                    cell(item.jenis_kelamin), cell(item.rombel));
                body.append(row);
            }
            if (!data.items.length) empty(body, 5, 'Tidak ada Peserta aktif yang belum ditugaskan.');
            s.page = Number(data.pagination.page); s.pages = Number(data.pagination.pages);
            $('pesertaUjianCandidateCount').textContent = data.pagination.filtered + ' Peserta tersedia (pilihan hanya halaman ini)';
            $('pesertaUjianCandidatePageInfo').textContent = s.page + ' / ' + s.pages;
            $('pesertaUjianCandidatePrevious').disabled = s.page <= 1;
            $('pesertaUjianCandidateNext').disabled = s.page >= s.pages;
            feedback('pesertaUjianCandidateFeedback', '');
        } catch (error) {if (seq === s.seq) feedback('pesertaUjianCandidateFeedback', error.message, true);}
    };
    const selectorChanged = () => {
        const selector = $('pesertaUjianSelector').value;
        $('pesertaUjianTingkatWrap').hidden = selector !== 'TINGKAT';
        $('pesertaUjianRombelWrap').hidden = selector !== 'ROMBEL';
        $('pesertaUjianCandidatePanel').hidden = selector !== 'IDS';
        if (selector === 'IDS') {state.candidates.page = 1; loadCandidates();}
    };
    $('pesertaUjianSelector').addEventListener('change', selectorChanged);
    $('pesertaUjianSelectAll').addEventListener('change', (event) => {
        const s = state.candidates; s.ids.clear();
        for (const checkbox of $('pesertaUjianCandidateRows').querySelectorAll('input[type="checkbox"]')) {
            checkbox.checked = event.target.checked;
            if (checkbox.checked) s.ids.add(Number(checkbox.closest('tr').dataset.id));
        }
        updateSelectAll();
    });
    $('pesertaUjianAssign').addEventListener('click', async () => {
        const selector = $('pesertaUjianSelector').value;
        let value = null;
        if (selector === 'TINGKAT') value = $('pesertaUjianTingkat').value;
        if (selector === 'ROMBEL') value = $('pesertaUjianRombel').value;
        if (selector === 'IDS') value = [...state.candidates.ids];
        if (selector === 'IDS' && !value.length) {feedback('pesertaUjianAssignFeedback', 'Pilih Peserta terlebih dahulu.', true); return;}
        const label = selector === 'ALL' ? 'semua Peserta aktif' : selector === 'IDS' ? value.length + ' Peserta' : selector + ' ' + value;
        if (!window.confirm('Tambahkan ' + label + ' ke Kegiatan ini?')) return;
        const button = $('pesertaUjianAssign'); button.disabled = true;
        feedback('pesertaUjianAssignFeedback', 'Memproses...');
        try {
            const result = await api(base, 'POST', {selector, value});
            state.members.page = 1; await loadMembers();
            if (selector === 'IDS') await loadCandidates();
            feedback('pesertaUjianAssignFeedback', result.added + ' ditambahkan, ' + result.skipped + ' sudah menjadi anggota.');
        } catch (error) {feedback('pesertaUjianAssignFeedback', error.message, true);}
        finally {button.disabled = false;}
    });
    $('pesertaUjianSearch').addEventListener('input', () => {
        clearTimeout(memberDebounce);
        memberDebounce = setTimeout(() => {state.members.page = 1; loadMembers();}, 300);
    });
    $('pesertaUjianPageSize').addEventListener('change', () => {state.members.page = 1; loadMembers();});
    $('pesertaUjianPrevious').addEventListener('click', () => {if (state.members.page > 1) {state.members.page--; loadMembers();}});
    $('pesertaUjianNext').addEventListener('click', () => {if (state.members.page < state.members.pages) {state.members.page++; loadMembers();}});
    $('pesertaUjianCandidateSearch').addEventListener('input', () => {
        clearTimeout(candidateDebounce);
        candidateDebounce = setTimeout(() => {state.candidates.page = 1; loadCandidates();}, 300);
    });
    for (const id of ['pesertaUjianCandidateRombel', 'pesertaUjianCandidateSize']) {
        $(id).addEventListener('change', () => {state.candidates.page = 1; loadCandidates();});
    }
    $('pesertaUjianCandidatePrevious').addEventListener('click', () => {if (state.candidates.page > 1) {state.candidates.page--; loadCandidates();}});
    $('pesertaUjianCandidateNext').addEventListener('click', () => {if (state.candidates.page < state.candidates.pages) {state.candidates.page++; loadCandidates();}});
    api(app.dataset.rombelApi).then((data) => {
        for (const item of data.items.filter((row) => row.status === 'ACTIVE')) {
            for (const id of ['pesertaUjianRombel', 'pesertaUjianCandidateRombel']) {
                const option = document.createElement('option');
                option.value = item.id; option.textContent = item.display_name; $(id).append(option);
            }
        }
    }).catch((error) => feedback('pesertaUjianAssignFeedback', error.message, true));
    loadMembers();
})();
