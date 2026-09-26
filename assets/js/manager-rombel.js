(() => {
    'use strict';

    const app = document.getElementById('rombelApp');
    if (!app) return;
    const base = app.dataset.apiUrl;
    const $ = (id) => document.getElementById(id);
    const state = {page: 1, pages: 1, editing: null, seq: 0};
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
        const response = await fetch(url, {
            method, credentials: 'same-origin', headers,
            ...(payload !== null ? {body: JSON.stringify(payload)} : {})
        });
        const result = await response.json().catch(() => null);
        if (!response.ok || result?.ok !== true) {
            const fields = result?.error?.fields ?? {};
            throw new Error(Object.values(fields)[0] ?? result?.error?.message ?? 'Permintaan gagal.');
        }
        return result.data;
    };

    const button = (label, className, handler) => {
        const node = document.createElement('button');
        node.type = 'button';
        node.className = className;
        node.textContent = label;
        node.addEventListener('click', handler);
        return node;
    };

    const resetForm = () => {
        state.editing = null;
        $('rombelForm').reset();
        $('rombelFormTitle').textContent = 'Tambah Rombel';
        $('rombelCancel').hidden = true;
        feedback('rombelFormFeedback', '');
    };

    const load = async () => {
        const seq = ++state.seq;
        const params = new URLSearchParams({
            page: state.page, per_page: $('rombelPageSize').value,
            q: $('rombelSearch').value.trim(), tingkat: $('rombelTingkatFilter').value,
            status: $('rombelStatusFilter').value
        });
        feedback('rombelListFeedback', 'Memuat Rombel...');
        try {
            const data = await api(base + '?' + params);
            if (seq !== state.seq) return;
            const body = $('rombelRows');
            body.replaceChildren();
            for (const item of data.items) {
                const tr = document.createElement('tr');
                for (const value of [item.display_name, item.tingkat, item.kode_rombel, item.status === 'ACTIVE' ? 'Aktif' : 'Nonaktif', item.updated_at ?? '-']) {
                    const td = document.createElement('td');
                    td.textContent = String(value);
                    tr.append(td);
                }
                const actions = document.createElement('td');
                actions.className = 'text-nowrap';
                actions.append(button('Edit', 'btn btn-outline-primary btn-sm me-1', () => {
                    state.editing = Number(item.id);
                    $('rombelTingkat').value = item.tingkat;
                    $('rombelKode').value = item.kode_rombel;
                    $('rombelFormTitle').textContent = 'Edit ' + item.display_name;
                    $('rombelCancel').hidden = false;
                    feedback('rombelFormFeedback', '');
                    $('rombelTingkat').focus();
                }));
                actions.append(button(item.status === 'ACTIVE' ? 'Nonaktifkan' : 'Aktifkan', 'btn btn-outline-secondary btn-sm me-1', async () => {
                    try {
                        await api(base + '/' + item.id + '/status', 'PATCH', {status: item.status === 'ACTIVE' ? 'INACTIVE' : 'ACTIVE'});
                        await load();
                    } catch (error) { feedback('rombelListFeedback', error.message, true); }
                }));
                actions.append(button('Hapus', 'btn btn-outline-danger btn-sm', async () => {
                    if (!window.confirm('Hapus Rombel ' + item.display_name + '? Rombel yang sudah digunakan Peserta tidak dapat dihapus.')) return;
                    try {
                        await api(base + '/' + item.id, 'DELETE');
                        await load();
                    } catch (error) { feedback('rombelListFeedback', error.message, true); }
                }));
                tr.append(actions);
                body.append(tr);
            }
            if (data.items.length === 0) {
                const tr = document.createElement('tr');
                const td = document.createElement('td');
                td.colSpan = 6;
                td.className = 'text-center text-secondary py-4';
                td.textContent = 'Tidak ada Rombel sesuai filter.';
                tr.append(td);
                body.append(tr);
            }
            state.page = Number(data.pagination.page);
            state.pages = Number(data.pagination.pages);
            $('rombelCount').textContent = data.pagination.filtered + ' dari ' + data.pagination.total + ' Rombel';
            $('rombelPageInfo').textContent = state.page + ' / ' + state.pages;
            $('rombelPrevious').disabled = state.page <= 1;
            $('rombelNext').disabled = state.page >= state.pages;
            feedback('rombelListFeedback', '');
        } catch (error) {
            if (seq === state.seq) feedback('rombelListFeedback', error.message, true);
        }
    };

    $('rombelForm').addEventListener('submit', async (event) => {
        event.preventDefault();
        const submit = $('rombelSubmit');
        submit.disabled = true;
        try {
            await api(state.editing === null ? base : base + '/' + state.editing,
                state.editing === null ? 'POST' : 'PUT',
                {tingkat: $('rombelTingkat').value, kode_rombel: $('rombelKode').value.trim().toUpperCase()});
            resetForm();
            state.page = 1;
            await load();
            feedback('rombelFormFeedback', 'Rombel berhasil disimpan.');
        } catch (error) {
            feedback('rombelFormFeedback', error.message, true);
        } finally { submit.disabled = false; }
    });
    $('rombelCancel').addEventListener('click', resetForm);
    $('rombelSearch').addEventListener('input', () => {
        clearTimeout(debounce);
        debounce = setTimeout(() => {state.page = 1; load();}, 300);
    });
    for (const id of ['rombelTingkatFilter', 'rombelStatusFilter', 'rombelPageSize']) {
        $(id).addEventListener('change', () => {state.page = 1; load();});
    }
    $('rombelResetFilters').addEventListener('click', () => {
        $('rombelSearch').value = '';
        $('rombelTingkatFilter').value = '';
        $('rombelStatusFilter').value = '';
        $('rombelPageSize').value = '25';
        state.page = 1;
        load();
    });
    $('rombelPrevious').addEventListener('click', () => {if (state.page > 1) {state.page--; load();}});
    $('rombelNext').addEventListener('click', () => {if (state.page < state.pages) {state.page++; load();}});
    load();
})();
