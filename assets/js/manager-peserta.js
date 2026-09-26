(() => {
    'use strict';

    const app = document.getElementById('pesertaApp');
    if (!app) return;
    const base = app.dataset.apiUrl;
    const $ = (id) => document.getElementById(id);
    const state = {page: 1, pages: 1, editing: null, seq: 0, options: []};
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
    const option = (value, label) => {
        const node = document.createElement('option');
        node.value = value;
        node.textContent = label;
        return node;
    };
    const button = (label, classes, handler) => {
        const node = document.createElement('button');
        node.type = 'button';
        node.className = classes;
        node.textContent = label;
        node.addEventListener('click', handler);
        return node;
    };
    const fillOptions = (current = null) => {
        const filter = $('pesertaRombelFilter');
        const field = $('pesertaRombel');
        const selectedFilter = filter.value;
        filter.replaceChildren(option('', 'Semua'));
        field.replaceChildren(option('', 'Pilih Rombel'));
        for (const row of state.options) {
            filter.append(option(row.id, row.display_name + (row.status === 'ACTIVE' ? '' : ' (nonaktif)')));
            if (row.status === 'ACTIVE' || Number(row.id) === Number(current?.rombel_id)) {
                field.append(option(row.id, row.display_name + (row.status === 'ACTIVE' ? '' : ' (nonaktif)')));
            }
        }
        filter.value = selectedFilter;
        if (current) field.value = current.rombel_id;
    };
    const loadOptions = async () => {
        const data = await api(base + '/rombel-options');
        state.options = data.items;
        fillOptions();
    };
    const resetForm = () => {
        state.editing = null;
        $('pesertaForm').reset();
        fillOptions();
        $('pesertaFormTitle').textContent = 'Tambah Peserta';
        $('pesertaCancel').hidden = true;
        feedback('pesertaFormFeedback', '');
    };
    const load = async () => {
        const seq = ++state.seq;
        const params = new URLSearchParams({
            page: state.page, per_page: $('pesertaPageSize').value,
            q: $('pesertaSearch').value.trim(), status: $('pesertaStatusFilter').value,
            rombel_id: $('pesertaRombelFilter').value
        });
        feedback('pesertaListFeedback', 'Memuat Peserta...');
        try {
            const data = await api(base + '?' + params);
            if (seq !== state.seq) return;
            const body = $('pesertaRows');
            body.replaceChildren();
            for (const item of data.items) {
                const tr = document.createElement('tr');
                for (const value of [item.nisn, item.nama, item.jenis_kelamin, item.rombel, item.status === 'ACTIVE' ? 'Aktif' : 'Nonaktif', item.keterangan ?? '-']) {
                    const td = document.createElement('td');
                    td.textContent = String(value);
                    tr.append(td);
                }
                const actions = document.createElement('td');
                actions.className = 'text-nowrap';
                actions.append(button('Edit', 'btn btn-outline-primary btn-sm me-1', () => {
                    state.editing = Number(item.id);
                    fillOptions(item);
                    $('pesertaNisn').value = item.nisn;
                    $('pesertaNama').value = item.nama;
                    $('pesertaJk').value = item.jenis_kelamin;
                    $('pesertaKeterangan').value = item.keterangan ?? '';
                    $('pesertaFormTitle').textContent = 'Edit ' + item.nama;
                    $('pesertaCancel').hidden = false;
                    feedback('pesertaFormFeedback', '');
                    $('pesertaNisn').focus();
                }));
                actions.append(button(item.status === 'ACTIVE' ? 'Nonaktifkan' : 'Aktifkan', 'btn btn-outline-secondary btn-sm', async () => {
                    try {
                        await api(base + '/' + item.id + '/status', 'PATCH', {status: item.status === 'ACTIVE' ? 'INACTIVE' : 'ACTIVE'});
                        await load();
                    } catch (error) { feedback('pesertaListFeedback', error.message, true); }
                }));
                tr.append(actions);
                body.append(tr);
            }
            if (data.items.length === 0) {
                const tr = document.createElement('tr');
                const td = document.createElement('td');
                td.colSpan = 7;
                td.className = 'text-center text-secondary py-4';
                td.textContent = 'Tidak ada Peserta sesuai filter.';
                tr.append(td);
                body.append(tr);
            }
            state.page = Number(data.pagination.page);
            state.pages = Number(data.pagination.pages);
            $('pesertaCount').textContent = data.pagination.filtered + ' dari ' + data.pagination.total + ' Peserta';
            $('pesertaPageInfo').textContent = state.page + ' / ' + state.pages;
            $('pesertaPrevious').disabled = state.page <= 1;
            $('pesertaNext').disabled = state.page >= state.pages;
            feedback('pesertaListFeedback', '');
        } catch (error) {
            if (seq === state.seq) feedback('pesertaListFeedback', error.message, true);
        }
    };
    $('pesertaForm').addEventListener('submit', async (event) => {
        event.preventDefault();
        const submit = $('pesertaSubmit');
        submit.disabled = true;
        try {
            await api(state.editing === null ? base : base + '/' + state.editing,
                state.editing === null ? 'POST' : 'PUT', {
                    nisn: $('pesertaNisn').value.trim(), nama: $('pesertaNama').value.trim(),
                    jenis_kelamin: $('pesertaJk').value, rombel_id: $('pesertaRombel').value,
                    keterangan: $('pesertaKeterangan').value.trim()
                });
            resetForm();
            state.page = 1;
            await load();
            feedback('pesertaFormFeedback', 'Data Peserta berhasil disimpan.');
        } catch (error) { feedback('pesertaFormFeedback', error.message, true); }
        finally { submit.disabled = false; }
    });
    $('pesertaCancel').addEventListener('click', resetForm);
    $('pesertaSearch').addEventListener('input', () => {
        clearTimeout(debounce);
        debounce = setTimeout(() => {state.page = 1; load();}, 300);
    });
    for (const id of ['pesertaRombelFilter', 'pesertaStatusFilter', 'pesertaPageSize']) {
        $(id).addEventListener('change', () => {state.page = 1; load();});
    }
    $('pesertaResetFilters').addEventListener('click', () => {
        $('pesertaSearch').value = '';
        $('pesertaRombelFilter').value = '';
        $('pesertaStatusFilter').value = '';
        $('pesertaPageSize').value = '50';
        state.page = 1;
        load();
    });
    $('pesertaPrevious').addEventListener('click', () => {if (state.page > 1) {state.page--; load();}});
    $('pesertaNext').addEventListener('click', () => {if (state.page < state.pages) {state.page++; load();}});
    loadOptions().catch((error) => feedback('pesertaFormFeedback', error.message, true));
    load();
})();
