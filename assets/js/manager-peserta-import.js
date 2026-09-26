(() => {
    'use strict';
    const app = document.getElementById('pesertaImportApp');
    if (!app) return;
    const $ = (id) => document.getElementById(id);
    const base = app.dataset.api;
    const state = {job: null, page: 1, pages: 1, editing: null, pendingCommit: null};
    const feedback = (id, message, error = false) => {
        const node = $(id);
        node.textContent = message;
        node.className = 'cbt-inline-feedback' + (message ? (error ? ' is-error' : ' is-info') : '');
    };
    const request = async (url, method = 'GET', body = null, key = '') => {
        const headers = {'Accept': 'application/json'};
        if (method !== 'GET') headers['X-CSRF-TOKEN'] = document.querySelector('meta[name="csrf-token"]').content;
        if (key) headers['Idempotency-Key'] = key;
        if (body !== null && !(body instanceof FormData)) headers['Content-Type'] = 'application/json';
        const response = await fetch(url, {method, credentials: 'same-origin', headers,
            ...(body === null ? {} : {body: body instanceof FormData ? body : JSON.stringify(body)})});
        const result = await response.json().catch(() => null);
        if (!response.ok || result?.ok !== true) {
            throw new Error(result?.error?.message ?? 'Permintaan gagal.');
        }
        return result.data;
    };
    const btn = (label, className, callback) => {
        const node = document.createElement('button');
        node.type = 'button'; node.className = className; node.textContent = label;
        node.addEventListener('click', callback); return node;
    };
    const refresh = async () => {
        if (!state.job) return;
        const data = await request(base + '/' + state.job.id);
        state.job = data.job;
        const job = state.job;
        $('importJobCard').hidden = false;
        $('importJobSummary').textContent = 'Job #' + job.id + ' · ' + job.original_filename +
            ' · ' + job.status + ' · Total ' + job.total_items + ' · Valid ' + job.valid_items +
            ' · Invalid ' + job.invalid_items;
        $('importParse').disabled = job.status !== 'UPLOADED';
        $('importValidate').disabled = !['PARSED', 'VALIDATED'].includes(job.status);
        $('importCommit').disabled = job.status !== 'VALIDATED' || Number(job.invalid_items) > 0 || Number(job.valid_items) < 1;
        await loadItems();
    };
    const loadItems = async () => {
        if (!state.job) return;
        const params = new URLSearchParams({page: state.page, status: $('importStatusFilter').value});
        const data = await request(base + '/' + state.job.id + '/items?' + params);
        const body = $('importRows'); body.replaceChildren();
        for (const item of data.items) {
            const tr = document.createElement('tr');
            const values = [item.item_no, item.payload.nisn, item.payload.nama,
                item.payload.jenis_kelamin, item.payload.rombel, item.payload.username || '-',
                item.password_set ? 'Diisi' : '-', item.validation_status +
                    (Object.keys(item.errors).length ? ': ' + Object.values(item.errors).join('; ') : '')];
            for (const value of values) {
                const td = document.createElement('td'); td.textContent = String(value ?? ''); tr.append(td);
            }
            const actions = document.createElement('td'); actions.className = 'text-nowrap';
            if (state.job.status !== 'COMMITTED') {
                actions.append(btn('Perbaiki', 'btn btn-outline-primary btn-sm me-1', () => {
                    state.editing = item.id;
                    $('importEditTitle').textContent = 'Perbaiki baris ' + item.item_no;
                    for (const [field, id] of Object.entries({nisn:'importEditNisn', nama:'importEditNama',
                        jenis_kelamin:'importEditJk', rombel:'importEditRombel',
                        keterangan:'importEditKeterangan', username:'importEditUsername'})) {
                        $(id).value = item.payload[field] ?? '';
                    }
                    $('importEditPassword').value = '';
                    $('importEditClearPassword').checked = false;
                    $('importEditCard').hidden = false;
                    $('importEditNisn').focus();
                }));
                const excluded = item.validation_status === 'EXCLUDED';
                actions.append(btn(excluded ? 'Sertakan' : 'Exclude', 'btn btn-outline-secondary btn-sm',
                    () => action(base + '/' + state.job.id + '/items/' + item.id + '/' +
                        (excluded ? 'include' : 'exclude'), 'POST')));
            }
            tr.append(actions); body.append(tr);
        }
        if (data.items.length === 0) {
            const tr = document.createElement('tr'), td = document.createElement('td');
            td.colSpan = 9; td.className = 'text-center text-secondary py-4';
            td.textContent = 'Tidak ada baris pada filter ini.'; tr.append(td); body.append(tr);
        }
        state.page = Number(data.pagination.page); state.pages = Number(data.pagination.pages);
        $('importPageInfo').textContent = state.page + ' / ' + state.pages;
        $('importPrevious').disabled = state.page <= 1;
        $('importNext').disabled = state.page >= state.pages;
    };
    const action = async (url, method, body = null, key = '') => {
        feedback('importActionFeedback', 'Memproses...');
        try {
            const data = await request(url, method, body, key);
            if (data.job) state.job = data.job;
            await refresh();
            feedback('importActionFeedback', 'Selesai.');
            return true;
        } catch (error) { feedback('importActionFeedback', error.message, true); return false; }
    };
    $('importUploadForm').addEventListener('submit', async (event) => {
        event.preventDefault();
        const file = $('importFile').files[0];
        if (!file) return;
        const form = new FormData(); form.append('import_type', 'PESERTA'); form.append('file', file);
        feedback('importFeedback', 'Mengunggah...');
        try {
            const data = await request(base, 'POST', form);
            state.job = data.job; state.page = 1; state.pendingCommit = null;
            localStorage.setItem('cbthero.import.peserta.job', String(data.job.id));
            await refresh(); feedback('importFeedback', 'Upload berhasil. Klik Parse.');
        } catch (error) { feedback('importFeedback', error.message, true); }
    });
    $('importParse').addEventListener('click', () => action(base + '/' + state.job.id + '/parse', 'POST'));
    $('importValidate').addEventListener('click', () => action(base + '/' + state.job.id + '/validate', 'POST'));
    $('importCommit').addEventListener('click', async () => {
        if (!window.confirm('Commit ' + state.job.valid_items + ' Peserta valid ke database?')) return;
        const key = state.pendingCommit ?? crypto.randomUUID().replaceAll('-', '');
        state.pendingCommit = key;
        await action(base + '/' + state.job.id + '/commit', 'POST', {}, key);
        if (state.job?.status === 'COMMITTED') state.pendingCommit = null;
    });
    $('importStatusFilter').addEventListener('change', () => {state.page = 1; loadItems().catch((e) => feedback('importActionFeedback', e.message, true));});
    $('importPrevious').addEventListener('click', () => {if (state.page > 1) {state.page--; loadItems();}});
    $('importNext').addEventListener('click', () => {if (state.page < state.pages) {state.page++; loadItems();}});
    $('importCancelEdit').addEventListener('click', () => {state.editing = null; $('importEditCard').hidden = true;});
    $('importEditForm').addEventListener('submit', async (event) => {
        event.preventDefault(); if (!state.editing) return;
        const data = {};
        for (const [field, id] of Object.entries({nisn:'importEditNisn', nama:'importEditNama',
            jenis_kelamin:'importEditJk', rombel:'importEditRombel',
            keterangan:'importEditKeterangan', username:'importEditUsername'})) data[field] = $(id).value.trim();
        if ($('importEditClearPassword').checked) data.password = '';
        else if ($('importEditPassword').value !== '') data.password = $('importEditPassword').value;
        if (await action(base + '/' + state.job.id + '/items/' + state.editing, 'PATCH', data)) {
            state.editing = null; $('importEditCard').hidden = true;
        }
    });
    const last = Number(localStorage.getItem('cbthero.import.peserta.job'));
    if (Number.isSafeInteger(last) && last > 0) {
        state.job = {id: last};
        refresh().catch(() => {state.job = null; localStorage.removeItem('cbthero.import.peserta.job');});
    }
})();
