(() => {
    'use strict';
    const app = document.getElementById('bankApp');
    if (!app) return;
    const $ = (id) => document.getElementById(id);
    const base = app.dataset.api;
    const modal = bootstrap.Modal.getOrCreateInstance($('bankModal'));
    const statusModal = bootstrap.Modal.getOrCreateInstance($('bankStatusModal'));
    const state = {page: 1, pages: 1, editing: null, seq: 0, kegiatan: [], mapel: [], statusTransition: null};
    let timer;
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
        if (!response.ok || result?.ok !== true) throw new Error(Object.values(result?.error?.fields ?? {})[0] ?? result?.error?.message ?? 'Permintaan gagal.');
        return result.data;
    };
    const td = (value) => {
        const node = document.createElement('td'); node.textContent = value == null || value === '' ? '—' : String(value);
        return node;
    };
    const selectOptions = (select, items, label, current = '') => {
        select.replaceChildren(new Option(label, ''));
        for (const item of items) select.add(new Option(item.label, item.id));
        select.value = current;
    };
    const typeLabels = {
        PG: 'Pilihan Ganda', PG_KOMPLEKS: 'PG Kompleks', PG_BERTINGKAT: 'PG Bertingkat',
        MATCHING: 'Menjodohkan', ISIAN_SINGKAT: 'Isian Singkat', URAIAN: 'Uraian',
    };
    const openStatus = async (item, intent = 'INFO') => {
        feedback('bankFeedback', 'Memeriksa kesiapan Bank...');
        try {
            const check = await api(base + '/' + item.id + '/preflight');
            $('bankStatusModalTitle').textContent = item.status === 'READY' ? 'Status Bank READY' : 'Validasi Bank Soal';
            const hero = $('bankStatusHero');
            hero.className = 'bank-status-hero ' + (check.pass ? 'is-pass' : 'is-fail');
            $('bankStatusIcon').textContent = check.pass ? '✓' : '!';
            $('bankStatusHeadline').textContent = item.status === 'READY'
                ? 'Bank sudah berstatus READY'
                : (check.pass ? 'Validasi lulus' : 'Bank belum siap menjadi READY');
            $('bankStatusLead').textContent = check.pass
                ? 'Komposisi, jumlah soal, pilihan/pasangan, bobot, dan kelengkapan soal sudah sesuai.'
                : 'Perbaiki catatan di bawah sebelum Bank dapat dijadikan READY.';

            const counts = $('bankStatusCounts'); counts.replaceChildren();
            for (const [type, count] of Object.entries(check.counts || {})) {
                const card = document.createElement('div'); card.className = 'bank-status-count-item';
                const name = document.createElement('span'); name.textContent = typeLabels[type] || type;
                const value = document.createElement('strong');
                const planned = Number(count.planned ?? count.required ?? 0);
                value.textContent = Number(count.available || 0) + ' / ' + planned;
                card.append(name, value); counts.append(card);
            }

            const errors = $('bankStatusErrors'); errors.replaceChildren();
            const alert = document.createElement('div');
            if ((check.errors || []).length) {
                alert.className = 'alert alert-warning mb-0';
                const strong = document.createElement('strong'); strong.textContent = 'Catatan validasi';
                const list = document.createElement('ul'); list.className = 'mb-0 mt-2 ps-3';
                for (const message of check.errors) {const li = document.createElement('li'); li.textContent = message; list.append(li);}
                alert.append(strong, list);
            } else {
                alert.className = 'alert alert-success mb-0';
                alert.textContent = 'Tidak ditemukan masalah pada Bank Soal.';
            }
            errors.append(alert);

            const action = $('bankStatusAction');
            action.hidden = true; action.disabled = false; action.className = 'btn btn-cbt-primary';
            state.statusTransition = null;
            let note = 'Cetak PDF tetap dapat digunakan saat Bank berstatus READY.';
            if (item.kegiatan_status !== 'DRAFT') {
                note = 'Kegiatan sudah terkunci. Status Bank tidak dapat diubah, tetapi Cetak PDF tetap tersedia.';
            } else if (intent === 'READY') {
                action.hidden = false; action.textContent = 'Jadikan READY'; action.disabled = !check.pass;
                state.statusTransition = check.pass ? {id: Number(item.id), target: 'READY', version: Number(check.bank.version_no)} : null;
                note = check.pass
                    ? 'Menjadikan READY akan mengunci perubahan Bank. Selama Kegiatan masih DRAFT dan Bank belum dipakai Jadwal, status dapat dikembalikan ke DRAFT.'
                    : 'Tombol READY aktif setelah seluruh validasi lulus.';
            } else if (intent === 'DRAFT') {
                action.hidden = false; action.textContent = 'Kembali ke DRAFT & Buka Edit';
                action.className = 'btn btn-warning';
                state.statusTransition = {id: Number(item.id), target: 'DRAFT', version: Number(check.bank.version_no)};
                note = 'Kembali ke DRAFT membuka kembali Komposisi/editor/import. Server tetap menolak bila Bank sudah dipakai Jadwal atau Kegiatan tidak lagi DRAFT.';
            }
            $('bankStatusNote').textContent = note;
            statusModal.show(); feedback('bankFeedback', '');
        } catch (error) {feedback('bankFeedback', error.message, true);}
    };
    const loadOptions = async () => {
        const data = await api(base + '/options');
        state.kegiatan = data.kegiatan; state.mapel = data.mapel;
        const filter = $('bankKegiatanFilter');
        const current = filter.value || new URLSearchParams(location.search).get('kegiatan_id') || '';
        selectOptions(filter, state.kegiatan.map((item) => ({id: item.id, label: item.nama + ' · ' + item.tahun_pelajaran})), 'Semua Kegiatan', current);
        selectOptions($('bankKegiatan'), state.kegiatan.filter((item) => item.status === 'DRAFT')
            .map((item) => ({id: item.id, label: item.nama + ' · ' + item.tahun_pelajaran})), 'Pilih Kegiatan');
        selectOptions($('bankMapel'), state.mapel.filter((item) => item.status === 'ACTIVE')
            .map((item) => ({id: item.id, label: item.nama_mapel})), 'Pilih Mata Pelajaran');
    };
    const load = async () => {
        const seq = ++state.seq;
        feedback('bankFeedback', 'Memuat Bank Soal...');
        const params = new URLSearchParams({page: state.page, per_page: $('bankPageSize').value,
            kegiatan_id: $('bankKegiatanFilter').value, q: $('bankSearch').value.trim()});
        try {
            const data = await api(base + '?' + params);
            if (seq !== state.seq) return;
            const tbody = $('bankRows'); tbody.replaceChildren();
            for (const item of data.items) {
                const row = document.createElement('tr');
                row.append(td(item.nama_bank), td(item.kegiatan_nama), td(item.mapel_nama), td(item.tingkat), td(item.status));
                const actions = document.createElement('td'); actions.className = 'text-nowrap';
                const editable = item.status === 'DRAFT' && item.kegiatan_status === 'DRAFT';
                if (editable) {
                    const composition = document.createElement('a');
                    composition.href = app.dataset.uiBase + '/' + item.id + '/komposisi';
                    composition.className = 'btn btn-outline-primary btn-sm me-1';
                    composition.textContent = 'Komposisi'; actions.append(composition);
                    const questions = document.createElement('a');
                    questions.href = app.dataset.uiBase + '/' + item.id + '/soal';
                    questions.className = 'btn btn-outline-primary btn-sm me-1';
                    questions.textContent = 'Soal PG'; actions.append(questions);
                    const advanced = document.createElement('a');
                    advanced.href = app.dataset.uiBase + '/' + item.id + '/soal-lanjutan';
                    advanced.className = 'btn btn-outline-primary btn-sm me-1';
                    advanced.textContent = 'Tipe Lain'; actions.append(advanced);
                    const importer = document.createElement('a');
                    importer.href = app.dataset.uiBase + '/' + item.id + '/impor';
                    importer.className = 'btn btn-outline-primary btn-sm me-1';
                    importer.textContent = 'Impor'; actions.append(importer);
                }
                const print = document.createElement('a');
                print.href = app.dataset.uiBase + '/' + item.id + '/cetak';
                print.target = '_blank'; print.rel = 'noopener';
                print.className = 'btn btn-outline-secondary btn-sm me-1';
                print.textContent = 'Cetak PDF'; actions.append(print);

                const preflight = document.createElement('button'); preflight.type = 'button';
                preflight.className = 'btn btn-outline-info btn-sm me-1';
                preflight.textContent = item.status === 'READY' ? 'Detail READY' : 'Validasi / READY';
                preflight.addEventListener('click', () => openStatus(item,
                    item.status === 'DRAFT' && item.kegiatan_status === 'DRAFT' ? 'READY' : 'INFO'));
                actions.append(preflight);

                if (item.status === 'READY' && item.kegiatan_status === 'DRAFT') {
                    const reopen = document.createElement('button'); reopen.type = 'button';
                    reopen.className = 'btn btn-outline-warning btn-sm me-1'; reopen.textContent = 'Kembali ke DRAFT';
                    reopen.addEventListener('click', () => openStatus(item, 'DRAFT'));
                    actions.append(reopen);
                }

                if (editable) {
                    const edit = document.createElement('button'); edit.type = 'button';
                    edit.className = 'btn btn-outline-primary btn-sm me-1'; edit.textContent = 'Edit';
                    edit.addEventListener('click', () => {
                        state.editing = Number(item.id);
                        // Opsi lama bisa sudah nonaktif; tetap boleh dipertahankan tanpa mengubah konteks Bank.
                        if (![...$('bankMapel').options].some((opt) => opt.value === String(item.mapel_id))) {
                            const option = new Option(item.mapel_nama + ' (nonaktif)', item.mapel_id);
                            option.dataset.temporary = '1'; $('bankMapel').add(option);
                        }
                        if (![...$('bankKegiatan').options].some((opt) => opt.value === String(item.kegiatan_id))) {
                            const option = new Option(item.kegiatan_nama, item.kegiatan_id);
                            option.dataset.temporary = '1'; $('bankKegiatan').add(option);
                        }
                        $('bankKegiatan').value = item.kegiatan_id;
                        $('bankKegiatan').disabled = true;
                        $('bankMapel').value = item.mapel_id;
                        $('bankTingkat').value = item.tingkat;
                        $('bankNama').value = item.nama_bank;
                        $('bankModalTitle').textContent = 'Edit Bank Soal';
                        feedback('bankFormFeedback', ''); modal.show();
                    });
                    const remove = document.createElement('button'); remove.type = 'button';
                    remove.className = 'btn btn-outline-danger btn-sm'; remove.textContent = 'Hapus';
                    remove.addEventListener('click', async () => {
                        if (!window.confirm('Hapus Bank Soal ' + item.nama_bank + '? Bank yang berisi soal/jadwal tidak dapat dihapus.')) return;
                        remove.disabled = true;
                        try { await api(base + '/' + item.id, 'DELETE'); await load(); feedback('bankFeedback', 'Bank Soal dihapus.'); }
                        catch (error) {remove.disabled = false; feedback('bankFeedback', error.message, true);}
                    });
                    actions.append(edit, remove);
                } else if (item.kegiatan_status !== 'DRAFT') {
                    const locked = document.createElement('span'); locked.className = 'badge text-bg-secondary align-middle';
                    locked.textContent = 'Kegiatan terkunci'; actions.append(locked);
                }
                row.append(actions); tbody.append(row);
            }
            if (!data.items.length) {const row = document.createElement('tr'), cell = td('Belum ada Bank Soal.'); cell.colSpan = 6; cell.className = 'text-center text-secondary py-4'; row.append(cell); tbody.append(row);}
            state.page = Number(data.pagination.page); state.pages = Number(data.pagination.pages);
            $('bankCount').textContent = data.pagination.filtered + ' dari ' + data.pagination.total + ' Bank';
            $('bankPageInfo').textContent = state.page + ' / ' + state.pages;
            $('bankPrevious').disabled = state.page <= 1; $('bankNext').disabled = state.page >= state.pages;
            feedback('bankFeedback', '');
        } catch (error) {if (seq === state.seq) feedback('bankFeedback', error.message, true);}
    };
    $('bankStatusAction').addEventListener('click', async () => {
        const transition = state.statusTransition;
        if (!transition) return;
        const button = $('bankStatusAction'); button.disabled = true;
        try {
            await api(base + '/' + transition.id + '/' + transition.target.toLowerCase(), 'POST',
                {expected_version: transition.version});
            statusModal.hide(); state.statusTransition = null;
            await load(); feedback('bankFeedback', 'Status Bank menjadi ' + transition.target + '.');
        } catch (error) {
            button.disabled = false; $('bankStatusNote').textContent = error.message;
        }
    });
    $('bankStatusModal').addEventListener('hidden.bs.modal', () => {state.statusTransition = null;});
    $('bankAdd').addEventListener('click', () => {
        state.editing = null; $('bankForm').reset(); $('bankKegiatan').disabled = false;
        $('bankKegiatan').value = $('bankKegiatanFilter').value;
        $('bankModalTitle').textContent = 'Tambah Bank Soal'; feedback('bankFormFeedback', ''); modal.show();
    });
    $('bankModal').addEventListener('hidden.bs.modal', () => {
        $('bankKegiatan').disabled = false; state.editing = null;
        for (const select of [$('bankKegiatan'), $('bankMapel')]) {
            for (const option of [...select.options]) if (option.dataset.temporary === '1') option.remove();
        }
    });
    $('bankForm').addEventListener('submit', async (event) => {
        event.preventDefault(); const button = $('bankSubmit'); button.disabled = true;
        try {
            await api(state.editing === null ? base : base + '/' + state.editing,
                state.editing === null ? 'POST' : 'PUT', {
                    kegiatan_id: Number($('bankKegiatan').value), mapel_id: Number($('bankMapel').value),
                    tingkat: Number($('bankTingkat').value), nama_bank: $('bankNama').value.trim(),
                });
            modal.hide(); state.page = 1; await load(); feedback('bankFeedback', 'Bank Soal disimpan.');
        } catch (error) {feedback('bankFormFeedback', error.message, true);}
        finally {button.disabled = false;}
    });
    $('bankKegiatanFilter').addEventListener('change', () => {state.page = 1; load();});
    $('bankSearch').addEventListener('input', () => {clearTimeout(timer); timer = setTimeout(() => {state.page = 1; load();}, 300);});
    $('bankPageSize').addEventListener('change', () => {state.page = 1; load();});
    $('bankReset').addEventListener('click', () => { $('bankKegiatanFilter').value = ''; $('bankSearch').value = ''; $('bankPageSize').value = '25'; state.page = 1; load(); });
    $('bankPrevious').addEventListener('click', () => {if (state.page > 1) {state.page--; load();}});
    $('bankNext').addEventListener('click', () => {if (state.page < state.pages) {state.page++; load();}});
    $('bankAdd').disabled = true;
    loadOptions().then(() => { $('bankAdd').disabled = false; load(); })
        .catch((error) => feedback('bankFeedback', error.message, true));
})();
