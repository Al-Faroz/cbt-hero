(() => {
    'use strict';

    const app = document.getElementById('pesertaApp');
    if (!app) return;
    const base = app.dataset.apiUrl;
    const $ = (id) => document.getElementById(id);
    const state = {page: 1, pages: 1, editing: null, account: null, accountBusy: false, seq: 0, options: [], items: [], selected: new Set()};
    const modal = bootstrap.Modal.getOrCreateInstance($('pesertaModal'));
    const accountModal = bootstrap.Modal.getOrCreateInstance($('pesertaAccountModal'));
    let debounce;

    const feedback = (id, message, error = false) => {
        const node = $(id);
        node.textContent = message;
        node.className = 'cbt-inline-feedback' + (message ? (error ? ' is-error' : ' is-info') : '');
    };
    const api = async (url, method = 'GET', payload = null, extraHeaders = {}) => {
        const headers = {'Accept': 'application/json', ...extraHeaders};
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
    const cell = (value) => {
        const td = document.createElement('td');
        td.textContent = value == null || value === '' ? '-' : String(value);
        return td;
    };
    const fillOptions = (current = null) => {
        const filter = $('pesertaRombelFilter');
        const field = $('pesertaRombel');
        const previous = filter.value;
        filter.replaceChildren(option('', 'Semua'));
        field.replaceChildren(option('', 'Pilih Rombel'));
        for (const row of state.options) {
            const label = row.display_name + (row.status === 'ACTIVE' ? '' : ' (nonaktif)');
            filter.append(option(row.id, label));
            if (row.status === 'ACTIVE' || Number(row.id) === Number(current?.rombel_id)) field.append(option(row.id, label));
        }
        filter.value = previous;
        if (current) field.value = current.rombel_id;
    };
    const resetForm = () => {
        state.editing = null;
        $('pesertaForm').reset();
        fillOptions();
        $('pesertaFormTitle').textContent = 'Tambah Peserta';
        feedback('pesertaFormFeedback', '');
    };
    const updateSelection = () => {
        const visible = state.items.map((item) => Number(item.id));
        const checked = visible.filter((id) => state.selected.has(id)).length;
        $('pesertaSelectVisible').checked = visible.length > 0 && checked === visible.length;
        $('pesertaSelectVisible').indeterminate = checked > 0 && checked < visible.length;
        $('pesertaSelectedCount').textContent = state.selected.size + ' Peserta dipilih (maksimal 100)';
        for (const id of ['pesertaBulkUsername', 'pesertaBulkRegenerate', 'pesertaBulkPassword']) {
            $(id).disabled = state.selected.size === 0;
        }
    };
    const selectPage = (selected) => {
        const ids = state.items.map((item) => Number(item.id));
        if (selected && new Set([...state.selected, ...ids]).size > 100) {
            feedback('pesertaListFeedback', 'Maksimal 100 Peserta dalam satu operasi. Batalkan pilihan lain dahulu.', true);
            updateSelection();
            return;
        }
        for (const id of ids) selected ? state.selected.add(id) : state.selected.delete(id);
        $('pesertaRows').querySelectorAll('input[data-peserta-id]').forEach((box) => { box.checked = state.selected.has(Number(box.dataset.pesertaId)); });
        updateSelection();
    };
    const openAccount = async (item) => {
        state.account = Number(item.id);
        $('pesertaAccountName').textContent = item.nama + ' · ' + item.nisn;
        $('pesertaAccountUsername').value = item.username ?? '';
        $('pesertaAccountPassword').value = '';
        feedback('pesertaAccountFeedback', '');
        accountModal.show();
    };
    const revealPassword = async (item, td) => {
        const trigger = td.querySelector('button');
        trigger.disabled = true;
        try {
            const data = await api(base + '/' + item.id + '/account/printable');
            const value = document.createElement('span');
            value.className = 'font-monospace me-1';
            value.textContent = data.printable.password;
            td.replaceChildren(value, button('Sembunyikan', 'btn btn-outline-secondary btn-sm', () => {
                td.replaceChildren(button('Lihat', 'btn btn-outline-secondary btn-sm', () => revealPassword(item, td)));
            }));
        } catch (error) {
            feedback('pesertaListFeedback', error.message, true);
            trigger.disabled = false;
        }
    };
    const load = async () => {
        const seq = ++state.seq;
        const params = new URLSearchParams({page: state.page, per_page: $('pesertaPageSize').value,
            q: $('pesertaSearch').value.trim(), status: $('pesertaStatusFilter').value,
            rombel_id: $('pesertaRombelFilter').value});
        feedback('pesertaListFeedback', 'Memuat Peserta...');
        try {
            const data = await api(base + '?' + params);
            if (seq !== state.seq) return;
            state.items = data.items;
            const body = $('pesertaRows');
            body.replaceChildren();
            for (const item of state.items) {
                const tr = document.createElement('tr');
                const select = document.createElement('td');
                const box = document.createElement('input');
                box.type = 'checkbox';
                box.dataset.pesertaId = item.id;
                box.setAttribute('aria-label', 'Pilih ' + item.nama);
                box.checked = state.selected.has(Number(item.id));
                box.addEventListener('change', () => {
                    const id = Number(item.id);
                    if (box.checked && state.selected.size >= 100 && !state.selected.has(id)) {
                        box.checked = false;
                        feedback('pesertaListFeedback', 'Maksimal 100 Peserta dalam satu operasi.', true);
                        return;
                    }
                    box.checked ? state.selected.add(id) : state.selected.delete(id);
                    updateSelection();
                });
                select.append(box);
                tr.append(select, cell(item.nisn), cell(item.nama), cell(item.username));
                const password = document.createElement('td');
                if (item.credential_status === 'READY') {
                    password.append(button('Lihat', 'btn btn-outline-secondary btn-sm', () => revealPassword(item, password)));
                } else password.textContent = 'Belum dibuat';
                tr.append(password, cell(item.jenis_kelamin), cell(item.rombel),
                    cell(item.status === 'ACTIVE' ? 'Aktif' : 'Nonaktif'), cell(item.keterangan));
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
                    feedback('pesertaFormFeedback', '');
                    modal.show();
                }));
                actions.append(button('Akun', 'btn btn-outline-primary btn-sm me-1', () => openAccount(item)));
                actions.append(button(item.status === 'ACTIVE' ? 'Nonaktifkan' : 'Aktifkan', 'btn btn-outline-secondary btn-sm', async () => {
                    try {
                        await api(base + '/' + item.id + '/status', 'PATCH', {status: item.status === 'ACTIVE' ? 'INACTIVE' : 'ACTIVE'});
                        await load();
                    } catch (error) { feedback('pesertaListFeedback', error.message, true); }
                }));
                tr.append(actions);
                body.append(tr);
            }
            if (state.items.length === 0) {
                const tr = document.createElement('tr');
                const td = cell('Tidak ada Peserta sesuai filter.');
                td.colSpan = 10;
                td.className = 'text-center text-secondary py-4';
                tr.append(td);
                body.append(tr);
            }
            state.page = Number(data.pagination.page);
            state.pages = Number(data.pagination.pages);
            $('pesertaCount').textContent = data.pagination.filtered + ' dari ' + data.pagination.total + ' Peserta';
            $('pesertaPageInfo').textContent = state.page + ' / ' + state.pages;
            $('pesertaPrevious').disabled = state.page <= 1;
            $('pesertaNext').disabled = state.page >= state.pages;
            updateSelection();
            feedback('pesertaListFeedback', '');
        } catch (error) { if (seq === state.seq) feedback('pesertaListFeedback', error.message, true); }
    };
    const clearSelection = () => {state.selected.clear(); selectPage(false);};
    const bulk = async (path, label) => {
        const ids = [...state.selected];
        if (!ids.length) return;
        if (!window.confirm(label + ' untuk ' + ids.length + ' Peserta?')) return;
        if (!window.confirm('Konfirmasi kedua: perubahan akun ini dapat mengubah akses login. Lanjutkan?')) return;
        const key = crypto.randomUUID().replaceAll('-', '');
        const control = $(path === 'generate-usernames' ? 'pesertaBulkUsername' :
            path === 'regenerate-usernames' ? 'pesertaBulkRegenerate' : 'pesertaBulkPassword');
        control.disabled = true;
        try {
            const data = await api(base + '/accounts/' + path, 'POST', {ids}, {'Idempotency-Key': key});
            clearSelection();
            await load();
            feedback('pesertaListFeedback', data.affected + ' Peserta berhasil diproses.');
        } catch (error) { feedback('pesertaListFeedback', error.message, true); }
        finally {updateSelection();}
    };
    const accountAction = async (endpoint, method, payload, label) => {
        if (state.account === null || state.accountBusy || !window.confirm(label + '?')) return;
        state.accountBusy = true;
        try {
            const data = await api(base + '/' + state.account + '/account/' + endpoint, method, payload);
            $('pesertaAccountUsername').value = data.account.username ?? '';
            $('pesertaAccountPassword').value = '';
            await load();
            feedback('pesertaAccountFeedback', data.password ? 'Password baru: ' + data.password : 'Akun berhasil diperbarui.');
        } catch (error) { feedback('pesertaAccountFeedback', error.message, true); }
        finally { state.accountBusy = false; }
    };
    $('pesertaAdd').addEventListener('click', resetForm);
    $('pesertaModal').addEventListener('hidden.bs.modal', resetForm);
    $('pesertaModal').addEventListener('shown.bs.modal', () => $('pesertaNisn').focus());
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
            modal.hide();
            state.page = 1;
            await load();
            feedback('pesertaListFeedback', 'Data Peserta berhasil disimpan.');
        } catch (error) { feedback('pesertaFormFeedback', error.message, true); }
        finally { submit.disabled = false; }
    });
    $('pesertaSelectPage').addEventListener('click', () => selectPage(true));
    $('pesertaSelectVisible').addEventListener('change', (event) => selectPage(event.target.checked));
    $('pesertaClearSelection').addEventListener('click', clearSelection);
    $('pesertaBulkUsername').addEventListener('click', () => bulk('generate-usernames', 'Buat Username yang belum ada'));
    $('pesertaBulkRegenerate').addEventListener('click', () => bulk('regenerate-usernames', 'Buat ulang Username'));
    $('pesertaBulkPassword').addEventListener('click', () => bulk('reset-passwords', 'Reset Password'));
    $('pesertaSaveUsername').addEventListener('click', () => accountAction('username', 'PUT',
        {username: $('pesertaAccountUsername').value.trim().toUpperCase()}, 'Simpan Username'));
    $('pesertaGenerateUsername').addEventListener('click', () => accountAction('generate-username', 'POST',
        {replace: $('pesertaAccountUsername').value !== ''}, 'Generate Username'));
    $('pesertaResetPassword').addEventListener('click', () => accountAction('reset-password', 'POST',
        {password: $('pesertaAccountPassword').value || null}, 'Generate / Reset Password'));
    $('pesertaSearch').addEventListener('input', () => {
        clearTimeout(debounce);
        debounce = setTimeout(() => {clearSelection(); state.page = 1; load();}, 300);
    });
    for (const id of ['pesertaRombelFilter', 'pesertaStatusFilter', 'pesertaPageSize']) {
        $(id).addEventListener('change', () => {clearSelection(); state.page = 1; load();});
    }
    $('pesertaResetFilters').addEventListener('click', () => {
        $('pesertaSearch').value = '';
        $('pesertaRombelFilter').value = '';
        $('pesertaStatusFilter').value = '';
        $('pesertaPageSize').value = '50';
        clearSelection(); state.page = 1; load();
    });
    $('pesertaPrevious').addEventListener('click', () => {if (state.page > 1) {state.page--; load();}});
    $('pesertaNext').addEventListener('click', () => {if (state.page < state.pages) {state.page++; load();}});
    api(base + '/rombel-options').then((data) => {state.options = data.items; fillOptions();})
        .catch((error) => feedback('pesertaListFeedback', error.message, true));
    load();
})();
