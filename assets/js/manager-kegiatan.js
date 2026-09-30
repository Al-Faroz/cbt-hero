(() => {
    'use strict';
    const app = document.getElementById('kegiatanApp');
    if (!app) return;
    const base = app.dataset.api;
    const $ = (id) => document.getElementById(id);
    const modal = bootstrap.Modal.getOrCreateInstance($('kegiatanModal'));
    const state = {page: 1, pages: 1, editing: null, sequence: 0};
    let debounce;

    const feedback = (id, message, error = false) => {
        const node = $(id);
        node.textContent = message;
        node.className = 'cbt-inline-feedback' + (message ? (error ? ' is-error' : ' is-info') : '');
    };
    const api = async (url, method = 'GET', payload = null) => {
        const headers = {'Accept': 'application/json'};
        if (method !== 'GET') headers['X-CSRF-TOKEN'] = document.querySelector('meta[name="csrf-token"]').content;
        if (payload !== null) headers['Content-Type'] = 'application/json';
        const response = await fetch(url, {method, credentials: 'same-origin', headers,
            ...(payload === null ? {} : {body: JSON.stringify(payload)})});
        const result = await response.json().catch(() => null);
        if (!response.ok || result?.ok !== true) {
            const fields = result?.error?.fields ?? {};
            throw new Error(Object.values(fields)[0] ?? result?.error?.message ?? 'Permintaan gagal.');
        }
        return result.data;
    };
    const cell = (value) => {
        const td = document.createElement('td');
        td.textContent = value == null || value === '' ? '-' : String(value);
        return td;
    };
    const button = (label, className, handler) => {
        const node = document.createElement('button');
        node.type = 'button'; node.className = className; node.textContent = label;
        node.addEventListener('click', handler); return node;
    };
    const resetForm = () => {
        state.editing = null;
        $('kegiatanForm').reset();
        $('kegiatanModalTitle').textContent = 'Tambah Kegiatan';
        feedback('kegiatanFormFeedback', '');
    };
    const load = async () => {
        const sequence = ++state.sequence;
        feedback('kegiatanFeedback', 'Memuat Kegiatan...');
        const params = new URLSearchParams({page: state.page, per_page: $('kegiatanPageSize').value,
            q: $('kegiatanSearch').value.trim(), jenis: $('kegiatanJenisFilter').value});
        try {
            const data = await api(base + '?' + params);
            if (sequence !== state.sequence) return;
            const body = $('kegiatanRows'); body.replaceChildren();
            for (const item of data.items) {
                const row = document.createElement('tr');
                row.append(cell(item.nama), cell(item.jenis === 'AKADEMIK' ? 'Akademik' : 'Psikologis'),
                    cell(item.tahun_pelajaran), cell(item.semester === 'GANJIL' ? 'Ganjil' : 'Genap'),
                    cell(item.keterangan),
                    cell(Number(item.exam_browser_required) === 1 ? 'Wajib' : 'Tidak'));
                const actions = document.createElement('td'); actions.className = 'text-nowrap';
                const members = document.createElement('a');
                members.className = 'btn btn-outline-primary btn-sm me-1';
                members.href = app.dataset.uiBase + '/' + item.id + '/peserta';
                members.textContent = 'Peserta'; actions.append(members);
                const cards = document.createElement('a');
                cards.className = 'btn btn-outline-primary btn-sm me-1';
                cards.href = app.dataset.uiBase + '/' + item.id + '/kartu';
                cards.textContent = 'Kartu'; actions.append(cards);
                const preflight = document.createElement('a');
                preflight.className = 'btn btn-outline-primary btn-sm me-1';
                preflight.href = app.dataset.uiBase + '/' + item.id + '/preflight';
                preflight.textContent = 'Kesiapan'; actions.append(preflight);
                if (item.jenis === 'AKADEMIK') {
                    const banks = document.createElement('a');
                    banks.className = 'btn btn-outline-primary btn-sm me-1';
                    banks.href = app.dataset.bankBase + '?kegiatan_id=' + item.id;
                    banks.textContent = 'Bank Soal'; actions.append(banks);
                    const schedules = document.createElement('a');
                    schedules.className = 'btn btn-outline-primary btn-sm me-1';
                    schedules.href = app.dataset.jadwalBase + '?kegiatan_id=' + item.id;
                    schedules.textContent = 'Jadwal'; actions.append(schedules);
                }
                if (item.structural_editable === true) {
                    actions.append(button('Edit', 'btn btn-outline-primary btn-sm me-1', () => {
                        state.editing = Number(item.id);
                        $('kegiatanNama').value = item.nama;
                        $('kegiatanJenis').value = item.jenis;
                        $('kegiatanTahun').value = item.tahun_pelajaran;
                        $('kegiatanSemester').value = item.semester;
                        $('kegiatanBrowser').checked = Number(item.exam_browser_required) === 1;
                        $('kegiatanKeterangan').value = item.keterangan ?? '';
                        $('kegiatanModalTitle').textContent = 'Edit ' + item.nama;
                        feedback('kegiatanFormFeedback', ''); modal.show();
                    }));
                    actions.append(button('Hapus', 'btn btn-outline-danger btn-sm', async () => {
                        if (!window.confirm('Hapus Kegiatan ' + item.nama + '? Kegiatan yang sudah memiliki data terkait tidak dapat dihapus.')) return;
                        try {
                            await api(base + '/' + item.id, 'DELETE');
                            await load(); feedback('kegiatanFeedback', 'Kegiatan berhasil dihapus.');
                        } catch (error) { feedback('kegiatanFeedback', error.message, true); }
                    }));
                } else {
                    actions.textContent = 'Terkunci';
                }
                row.append(actions); body.append(row);
            }
            if (!data.items.length) {
                const row = document.createElement('tr'), td = cell('Tidak ada Kegiatan sesuai filter.');
                td.colSpan = 7; td.className = 'text-center text-secondary py-4';
                row.append(td); body.append(row);
            }
            state.page = Number(data.pagination.page); state.pages = Number(data.pagination.pages);
            $('kegiatanCount').textContent = data.pagination.filtered + ' dari ' + data.pagination.total + ' Kegiatan';
            $('kegiatanPageInfo').textContent = state.page + ' / ' + state.pages;
            $('kegiatanPrevious').disabled = state.page <= 1;
            $('kegiatanNext').disabled = state.page >= state.pages;
            feedback('kegiatanFeedback', '');
        } catch (error) {
            if (sequence === state.sequence) feedback('kegiatanFeedback', error.message, true);
        }
    };
    $('kegiatanAdd').addEventListener('click', async () => {
        resetForm();
        try {
            const data = await api(base + '/defaults');
            $('kegiatanTahun').value = data.defaults.default_tahun_pelajaran ?? '';
            $('kegiatanSemester').value = data.defaults.default_semester || 'GANJIL';
        } catch (error) { feedback('kegiatanFormFeedback', error.message, true); }
        modal.show();
    });
    $('kegiatanModal').addEventListener('hidden.bs.modal', resetForm);
    $('kegiatanModal').addEventListener('shown.bs.modal', () => $('kegiatanNama').focus());
    $('kegiatanForm').addEventListener('submit', async (event) => {
        event.preventDefault();
        const submit = $('kegiatanSubmit'); submit.disabled = true;
        try {
            await api(state.editing === null ? base : base + '/' + state.editing,
                state.editing === null ? 'POST' : 'PUT', {
                    nama: $('kegiatanNama').value.trim(), jenis: $('kegiatanJenis').value,
                    tahun_pelajaran: $('kegiatanTahun').value.trim(), semester: $('kegiatanSemester').value,
                    exam_browser_required: $('kegiatanBrowser').checked,
                    keterangan: $('kegiatanKeterangan').value.trim(),
                });
            modal.hide(); state.page = 1; await load();
            feedback('kegiatanFeedback', 'Kegiatan berhasil disimpan.');
        } catch (error) { feedback('kegiatanFormFeedback', error.message, true); }
        finally { submit.disabled = false; }
    });
    $('kegiatanSearch').addEventListener('input', () => {
        clearTimeout(debounce);
        debounce = setTimeout(() => {state.page = 1; load();}, 300);
    });
    for (const id of ['kegiatanJenisFilter', 'kegiatanPageSize']) {
        $(id).addEventListener('change', () => {state.page = 1; load();});
    }
    $('kegiatanReset').addEventListener('click', () => {
        $('kegiatanSearch').value = ''; $('kegiatanJenisFilter').value = '';
        $('kegiatanPageSize').value = '25';
        state.page = 1; load();
    });
    $('kegiatanPrevious').addEventListener('click', () => {if (state.page > 1) {state.page--; load();}});
    $('kegiatanNext').addEventListener('click', () => {if (state.page < state.pages) {state.page++; load();}});
    load();
})();
