(() => {
    'use strict';
    const app = document.getElementById('ruangApp');
    if (!app) return;
    const $ = (id) => document.getElementById(id);
    const base = app.dataset.api;
    const modal = bootstrap.Modal.getOrCreateInstance($('ruangModal'));
    const state = {page: 1, pages: 1, editing: null, seq: 0};
    let debounce;
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
        if (!response.ok || result?.ok !== true)
            throw new Error(Object.values(result?.error?.fields ?? {})[0] ?? result?.error?.message ?? 'Permintaan gagal.');
        return result.data;
    };
    const button = (label, className, handler) => {
        const node = document.createElement('button'); node.type = 'button';
        node.className = className; node.textContent = label; node.addEventListener('click', handler); return node;
    };
    const open = (item = null) => {
        state.editing = item ? Number(item.id) : null;
        $('ruangForm').reset();
        $('ruangModalTitle').textContent = item ? 'Edit Ruang' : 'Tambah Ruang';
        $('ruangKode').value = item?.kode ?? '';
        $('ruangNama').value = item?.nama ?? '';
        feedback('ruangFormFeedback', ''); modal.show();
    };
    const load = async () => {
        const seq = ++state.seq;
        feedback('ruangFeedback', 'Memuat Ruang...');
        const params = new URLSearchParams({page: state.page, per_page: $('ruangPageSize').value,
            q: $('ruangSearch').value.trim(), status: $('ruangStatusFilter').value});
        try {
            const data = await api(base + '?' + params);
            if (seq !== state.seq) return;
            const body = $('ruangRows'); body.replaceChildren();
            for (const item of data.items) {
                const tr = document.createElement('tr');
                for (const value of [item.kode, item.nama, item.status === 'ACTIVE' ? 'Aktif' : 'Nonaktif', item.updated_at ?? '-']) {
                    const td = document.createElement('td'); td.textContent = value; tr.append(td);
                }
                const actions = document.createElement('td'); actions.className = 'text-nowrap';
                actions.append(button('Edit', 'btn btn-outline-primary btn-sm me-1', () => open(item)));
                actions.append(button(item.status === 'ACTIVE' ? 'Nonaktifkan' : 'Aktifkan',
                    'btn btn-outline-secondary btn-sm me-1', async (event) => {
                        const action = event.currentTarget; action.disabled = true;
                        try { await api(base + '/' + item.id + '/status', 'PATCH',
                            {status: item.status === 'ACTIVE' ? 'INACTIVE' : 'ACTIVE'}); await load(); }
                        catch (error) { feedback('ruangFeedback', error.message, true); action.disabled = false; }
                    }));
                actions.append(button('Hapus', 'btn btn-outline-danger btn-sm', async (event) => {
                    if (!window.confirm('Hapus Ruang ' + item.kode + '? Ruang yang masih dipakai tidak dapat dihapus.')) return;
                    const action = event.currentTarget; action.disabled = true;
                    try { await api(base + '/' + item.id, 'DELETE'); await load(); }
                    catch (error) { feedback('ruangFeedback', error.message, true); action.disabled = false; }
                }));
                tr.append(actions); body.append(tr);
            }
            if (!data.items.length) {
                const tr = document.createElement('tr'), td = document.createElement('td');
                td.colSpan = 5; td.className = 'text-center text-secondary py-4';
                td.textContent = 'Tidak ada Ruang sesuai filter.'; tr.append(td); body.append(tr);
            }
            state.page = Number(data.pagination.page); state.pages = Number(data.pagination.pages);
            $('ruangCount').textContent = data.pagination.filtered + ' dari ' + data.pagination.total + ' Ruang';
            $('ruangPageInfo').textContent = state.page + ' / ' + state.pages;
            $('ruangPrevious').disabled = state.page <= 1; $('ruangNext').disabled = state.page >= state.pages;
            feedback('ruangFeedback', '');
        } catch (error) { if (seq === state.seq) feedback('ruangFeedback', error.message, true); }
    };
    $('ruangAdd').addEventListener('click', () => open());
    $('ruangForm').addEventListener('submit', async (event) => {
        event.preventDefault(); const submit = $('ruangSubmit'); submit.disabled = true;
        try {
            await api(state.editing === null ? base : base + '/' + state.editing,
                state.editing === null ? 'POST' : 'PUT',
                {kode: $('ruangKode').value.trim().toUpperCase(), nama: $('ruangNama').value.trim()});
            modal.hide(); state.page = 1; await load(); feedback('ruangFeedback', 'Ruang berhasil disimpan.');
        } catch (error) { feedback('ruangFormFeedback', error.message, true); }
        finally { submit.disabled = false; }
    });
    $('ruangSearch').addEventListener('input', () => {
        clearTimeout(debounce); debounce = setTimeout(() => {state.page = 1; load();}, 300);
    });
    for (const id of ['ruangStatusFilter', 'ruangPageSize'])
        $(id).addEventListener('change', () => {state.page = 1; load();});
    $('ruangReset').addEventListener('click', () => {
        $('ruangSearch').value = ''; $('ruangStatusFilter').value = ''; $('ruangPageSize').value = '25';
        state.page = 1; load();
    });
    $('ruangPrevious').addEventListener('click', () => {if (state.page > 1) {state.page--; load();}});
    $('ruangNext').addEventListener('click', () => {if (state.page < state.pages) {state.page++; load();}});
    load();
})();
