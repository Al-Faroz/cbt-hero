(() => {
    'use strict';

    const app = document.getElementById('mapelApp');
    if (!app) return;
    const base = app.dataset.apiUrl;
    const $ = (id) => document.getElementById(id);
    const state = {page: 1, pages: 1, editing: null, seq: 0};
    const modal = bootstrap.Modal.getOrCreateInstance($('mapelModal'));
    let debounce;

    const feedback = (id, message, error = false) => {
        const node = $(id);
        node.textContent = message;
        node.className = 'cbt-inline-feedback' + (message ? (error ? ' is-error' : ' is-info') : '');
    };
    const api = async (url, method = 'GET', payload = null) => {
        const headers = {'Accept': 'application/json'};
        if (payload !== null) headers['Content-Type'] = 'application/json';
        if (method !== 'GET') headers['X-CSRF-TOKEN'] = document.querySelector('meta[name="csrf-token"]').content;
        const response = await fetch(url, {method, credentials: 'same-origin', headers,
            ...(payload !== null ? {body: JSON.stringify(payload)} : {})});
        const result = await response.json().catch(() => null);
        if (!response.ok || result?.ok !== true) {
            const fields = result?.error?.fields ?? {};
            throw new Error(Object.values(fields)[0] ?? result?.error?.message ?? 'Permintaan gagal.');
        }
        return result.data;
    };
    const button = (label, classes, handler) => {
        const node = document.createElement('button');
        node.type = 'button';
        node.className = classes;
        node.textContent = label;
        node.addEventListener('click', handler);
        return node;
    };
    const cell = (value) => {
        const node = document.createElement('td');
        node.textContent = value == null || value === '' ? '-' : String(value);
        return node;
    };
    const resetForm = () => {
        state.editing = null;
        $('mapelForm').reset();
        $('mapelUrutan').value = '0';
        $('mapelFormTitle').textContent = 'Tambah Mata Pelajaran';
        feedback('mapelFormFeedback', '');
    };
    const load = async () => {
        const seq = ++state.seq;
        const params = new URLSearchParams({page: state.page, per_page: $('mapelPageSize').value,
            q: $('mapelSearch').value.trim(), status: $('mapelStatusFilter').value,
            sort: $('mapelSort').value});
        feedback('mapelListFeedback', 'Memuat Mata Pelajaran...');
        try {
            const data = await api(base + '?' + params);
            if (seq !== state.seq) return;
            const body = $('mapelRows');
            body.replaceChildren();
            for (const item of data.items) {
                const tr = document.createElement('tr');
                tr.append(cell(item.urutan), cell(item.kode_mapel), cell(item.nama_mapel),
                    cell(item.singkatan), cell(item.status === 'ACTIVE' ? 'Aktif' : 'Nonaktif'));
                const actions = document.createElement('td');
                actions.className = 'text-nowrap';
                actions.append(button('Edit', 'btn btn-outline-primary btn-sm me-1', () => {
                    state.editing = Number(item.id);
                    $('mapelKode').value = item.kode_mapel;
                    $('mapelNama').value = item.nama_mapel;
                    $('mapelSingkatan').value = item.singkatan ?? '';
                    $('mapelUrutan').value = item.urutan;
                    $('mapelFormTitle').textContent = 'Edit ' + item.nama_mapel;
                    feedback('mapelFormFeedback', '');
                    modal.show();
                }));
                actions.append(button(item.status === 'ACTIVE' ? 'Nonaktifkan' : 'Aktifkan',
                    'btn btn-outline-secondary btn-sm me-1', async () => {
                        try {
                            await api(base + '/' + item.id + '/status', 'PATCH',
                                {status: item.status === 'ACTIVE' ? 'INACTIVE' : 'ACTIVE'});
                            await load();
                        } catch (error) { feedback('mapelListFeedback', error.message, true); }
                    }));
                actions.append(button('Hapus', 'btn btn-outline-danger btn-sm', async () => {
                    if (!window.confirm('Hapus Mata Pelajaran ' + item.nama_mapel + '? Mapel yang digunakan Bank Soal tidak dapat dihapus.')) return;
                    try {
                        await api(base + '/' + item.id, 'DELETE');
                        await load();
                        feedback('mapelListFeedback', 'Mata Pelajaran berhasil dihapus.');
                    } catch (error) { feedback('mapelListFeedback', error.message, true); }
                }));
                tr.append(actions);
                body.append(tr);
            }
            if (data.items.length === 0) {
                const tr = document.createElement('tr');
                const td = cell('Tidak ada Mata Pelajaran sesuai filter.');
                td.colSpan = 6;
                td.className = 'text-center text-secondary py-4';
                tr.append(td);
                body.append(tr);
            }
            state.page = Number(data.pagination.page);
            state.pages = Number(data.pagination.pages);
            $('mapelCount').textContent = data.pagination.filtered + ' dari ' + data.pagination.total + ' Mata Pelajaran';
            $('mapelPageInfo').textContent = state.page + ' / ' + state.pages;
            $('mapelPrevious').disabled = state.page <= 1;
            $('mapelNext').disabled = state.page >= state.pages;
            feedback('mapelListFeedback', '');
        } catch (error) { if (seq === state.seq) feedback('mapelListFeedback', error.message, true); }
    };
    $('mapelAdd').addEventListener('click', resetForm);
    $('mapelModal').addEventListener('hidden.bs.modal', resetForm);
    $('mapelModal').addEventListener('shown.bs.modal', () => $('mapelKode').focus());
    $('mapelForm').addEventListener('submit', async (event) => {
        event.preventDefault();
        const submit = $('mapelSubmit');
        submit.disabled = true;
        try {
            await api(state.editing === null ? base : base + '/' + state.editing,
                state.editing === null ? 'POST' : 'PUT', {
                    kode_mapel: $('mapelKode').value.trim().toUpperCase(),
                    nama_mapel: $('mapelNama').value.trim(),
                    singkatan: $('mapelSingkatan').value.trim().toUpperCase(),
                    urutan: $('mapelUrutan').value
                });
            modal.hide();
            state.page = 1;
            await load();
            feedback('mapelListFeedback', 'Mata Pelajaran berhasil disimpan.');
        } catch (error) { feedback('mapelFormFeedback', error.message, true); }
        finally { submit.disabled = false; }
    });
    $('mapelSearch').addEventListener('input', () => {
        clearTimeout(debounce);
        debounce = setTimeout(() => {state.page = 1; load();}, 300);
    });
    for (const id of ['mapelStatusFilter', 'mapelSort', 'mapelPageSize']) {
        $(id).addEventListener('change', () => {state.page = 1; load();});
    }
    $('mapelResetFilters').addEventListener('click', () => {
        $('mapelSearch').value = '';
        $('mapelStatusFilter').value = '';
        $('mapelSort').value = 'urutan';
        $('mapelPageSize').value = '25';
        state.page = 1;
        load();
    });
    $('mapelPrevious').addEventListener('click', () => {if (state.page > 1) {state.page--; load();}});
    $('mapelNext').addEventListener('click', () => {if (state.page < state.pages) {state.page++; load();}});
    load();
})();
