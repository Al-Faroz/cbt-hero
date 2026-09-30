(() => {
    'use strict';
    const app = document.getElementById('jadwalApp');
    if (!app) return;

    const $ = id => document.getElementById(id);
    const base = app.dataset.api;
    const uiBase = app.dataset.uiBase;
    const modal = bootstrap.Modal.getOrCreateInstance($('jadwalModal'));
    const operationModal = bootstrap.Modal.getOrCreateInstance($('jadwalOperationModal'));
    const state = {page: 1, pages: 1, editing: null, options: {kegiatan: [], banks: []}, sequence: 0};
    let debounce;

    const feedback = (id, text, error = false) => {
        const node = $(id);
        node.textContent = text;
        node.className = 'cbt-inline-feedback' + (text ? (error ? ' is-error' : ' is-info') : '');
    };

    const api = async (url, method = 'GET', payload = null) => {
        const headers = {Accept: 'application/json'};
        if (method !== 'GET') headers['X-CSRF-TOKEN'] = document.querySelector('meta[name="csrf-token"]').content;
        if (payload !== null) headers['Content-Type'] = 'application/json';
        const response = await fetch(url, {
            method, credentials: 'same-origin', headers,
            ...(payload === null ? {} : {body: JSON.stringify(payload)}),
        });
        const result = await response.json().catch(() => null);
        if (!response.ok || result?.ok !== true) {
            const fields = result?.error?.fields ?? {};
            throw new Error(Object.values(fields)[0] ?? result?.error?.message ?? 'Permintaan gagal.');
        }
        return result.data;
    };

    const cell = (value, className = '') => {
        const td = document.createElement('td');
        td.className = className;
        td.textContent = value == null || value === '' ? '-' : String(value);
        return td;
    };

    const actionButton = (label, className, handler, disabled = false) => {
        const button = document.createElement('button');
        button.type = 'button'; button.className = className; button.textContent = label;
        button.disabled = disabled;
        button.addEventListener('click', handler); return button;
    };

    const displayDate = value => {
        const text = String(value ?? '');
        if (text.length < 16) return text || '-';
        const [date, time] = text.replace('T', ' ').split(' ');
        const [y, m, d] = date.split('-');
        return d + '/' + m + '/' + y + ' ' + time.slice(0, 5);
    };

    const inputDate = value => String(value ?? '').replace(' ', 'T').slice(0, 16);

    const durationLabel = seconds => {
        const value = Number(seconds) || 0;
        return value % 60 === 0 ? (value / 60) + ' menit' : value + ' detik';
    };

    const selectionLabel = items => (items || []).map(item => item.selection_count + ' ' + item.label).join(' · ') || '-';

    const windowLabel = value => ({
        AKAN_DATANG: 'Akan datang',
        JENDELA_MULAI: 'Jendela mulai aktif',
        BATAS_MULAI_LEWAT: 'Batas mulai lewat'
    }[value] || value);

    const populateFilter = () => {
        const current = $('jadwalKegiatanFilter').value;
        $('jadwalKegiatanFilter').replaceChildren(new Option('Semua Kegiatan', ''),
            ...state.options.kegiatan.map(item => new Option(item.nama, item.id)));
        if ([...$('jadwalKegiatanFilter').options].some(option => option.value === current))
            $('jadwalKegiatanFilter').value = current;
    };

    const populateKegiatanForm = selected => {
        const activities = state.options.kegiatan;
        $('jadwalKegiatan').replaceChildren(new Option('Pilih Kegiatan', ''),
            ...activities.map(item => new Option(item.nama + ' · ' + item.tahun_pelajaran + ' · ' + item.semester, item.id)));
        if (selected != null) $('jadwalKegiatan').value = String(selected);
    };

    const currentBank = () =>
        state.options.banks.find(item => Number(item.id) === Number($('jadwalBank').value)) ?? null;

    const populateBankForm = selected => {
        const kegiatanId = Number($('jadwalKegiatan').value || 0);
        const banks = state.options.banks.filter(item => Number(item.kegiatan_id) === kegiatanId);
        $('jadwalBank').replaceChildren(
            new Option(kegiatanId ? 'Pilih Bank Soal READY' : 'Pilih Kegiatan dahulu', ''),
            ...banks.map(item => new Option(
                item.nama_bank + ' · ' + item.mapel_nama + ' · Tingkat ' + item.tingkat,
                item.id
            ))
        );
        if (selected != null && banks.some(item => Number(item.id) === Number(selected)))
            $('jadwalBank').value = String(selected);
        $('jadwalBank').disabled = kegiatanId < 1 || banks.length === 0;
        $('jadwalBankHint').textContent = kegiatanId < 1
            ? 'Pilih Kegiatan terlebih dahulu.'
            : (banks.length ? banks.length + ' Bank READY tersedia.' : 'Belum ada Bank READY pada Kegiatan ini.');
    };

    const renderSelection = (selected = []) => {
        const container = $('jadwalSelection'); container.replaceChildren();
        const bank = currentBank();
        if (!bank) {
            $('jadwalSelectionHint').textContent = 'Pilih Bank Soal untuk menampilkan komposisi.';
            return;
        }
        const selectedMap = new Map((selected || []).map(item => [item.question_type, Number(item.selection_count)]));
        for (const type of bank.types || []) {
            const col = document.createElement('div'); col.className = 'col-md-6 col-xl-4';
            const card = document.createElement('div'); card.className = 'border rounded p-3 h-100 bg-body-tertiary';
            const title = document.createElement('div'); title.className = 'fw-semibold'; title.textContent = type.label;
            const detail = document.createElement('div'); detail.className = 'small text-secondary mb-2';
            detail.textContent = 'Tersedia ' + type.available + ' soal · Bobot Bank '
                + (window.CbtNumber?.format(type.weight_percent, 3) ?? new Intl.NumberFormat('id-ID',{maximumFractionDigits:3}).format(Number(type.weight_percent))) + '%';
            const label = document.createElement('label'); label.className = 'cbt-form-label';
            label.htmlFor = 'jadwalSelect_' + type.question_type; label.textContent = 'Diambil untuk peserta';
            const input = document.createElement('input'); input.type = 'number'; input.className = 'form-control';
            input.id = 'jadwalSelect_' + type.question_type; input.min = '1'; input.max = String(type.available);
            input.step = '1'; input.required = true; input.dataset.questionType = type.question_type;
            input.value = String(selectedMap.get(type.question_type) ?? type.available);
            card.append(title, detail, label, input); col.append(card); container.append(col);
        }
        $('jadwalSelectionHint').textContent = (bank.types || []).length
            ? 'Jumlah yang dipilih harus minimal 1 dan tidak boleh melebihi jumlah soal tersedia pada Bank.'
            : 'Komposisi Bank belum tersedia.';
    };

    const collectSelection = () =>
        [...document.querySelectorAll('#jadwalSelection [data-question-type]')].map(input => ({
            question_type: input.dataset.questionType,
            selection_count: Number(input.value),
        }));

    const resetForm = () => {
        state.editing = null;
        $('jadwalForm').reset();
        $('jadwalUrutan').value = '1';
        $('jadwalDurasi').value = '90';
        $('jadwalAccess').value = 'BUKA';
        $('jadwalModalTitle').textContent = 'Tambah Jadwal';
        const preferredKegiatan = Number($('jadwalKegiatanFilter').value || 0);
        populateKegiatanForm(preferredKegiatan || null);
        populateBankForm(null);
        renderSelection();
        $('jadwalKegiatan').disabled = false;
        feedback('jadwalFormFeedback', '');
    };

    const openEdit = async id => {
        feedback('jadwalFeedback', 'Memuat Jadwal...');
        try {
            const data = await api(base + '/' + id);
            const item = data.item;
            state.editing = Number(item.id);
            populateKegiatanForm(item.kegiatan_id);
            populateBankForm(item.bank_soal_id);
            $('jadwalBank').value = String(item.bank_soal_id);
            $('jadwalMulai').value = inputDate(item.mulai_at);
            $('jadwalBatasMulai').value = inputDate(item.batas_mulai_at);
            $('jadwalUrutan').value = String(Math.max(1, Number(item.urutan_ujian) || 1));
            $('jadwalDurasi').value = String(Math.max(1, Math.round(Number(item.durasi_seconds) / 60)));
            $('jadwalAccess').value = item.access_state;
            $('jadwalShowScore').checked = Number(item.tampilkan_nilai_saat_selesai) === 1;
            renderSelection(item.type_selection || []);
            $('jadwalModalTitle').textContent = 'Edit Jadwal · '
                + (item.nama_mapel || item.nama_bank || ('#' + item.id));
            feedback('jadwalFormFeedback', '');
            feedback('jadwalFeedback', '');
            modal.show();
        } catch (error) {
            feedback('jadwalFeedback', error.message, true);
        }
    };

    const openOperation = (id, type, currentValue = '') => {
        $('jadwalOperationId').value = String(id);
        $('jadwalOperationType').value = type;
        $('jadwalOperationFields').replaceChildren();
        feedback('jadwalOperationFeedback', '');

        const label = document.createElement('label');
        label.className = 'cbt-form-label';
        label.htmlFor = 'jadwalOperationValue';
        const input = document.createElement('input');
        input.className = 'form-control';
        input.id = 'jadwalOperationValue';
        input.required = true;
        const help = document.createElement('div');
        help.className = 'form-text';

        if (type === 'EXTEND') {
            $('jadwalOperationTitle').textContent = 'Perpanjang Batas Mulai';
            label.textContent = 'Batas Mulai baru';
            input.type = 'datetime-local';
            input.value = inputDate(currentValue);
            help.textContent = 'Waktu baru harus lebih akhir dari Batas Mulai sekarang.';
        } else {
            $('jadwalOperationTitle').textContent = 'Tambah Waktu';
            label.textContent = 'Tambahan waktu untuk semua peserta aktif (menit)';
            input.type = 'number';
            input.min = '1';
            input.step = '1';
            input.value = '10';
            help.textContent = 'Hanya Attempt yang masih aktif pada Jadwal ini yang mendapat tambahan waktu.';
        }
        $('jadwalOperationFields').append(label, input, help);
        operationModal.show();
    };

    const loadOptions = async () => {
        state.options = await api(base + '/options');
        populateFilter();
    };

    const load = async () => {
        const sequence = ++state.sequence;
        feedback('jadwalFeedback', 'Memuat Jadwal...');
        const params = new URLSearchParams({
            page: String(state.page),
            per_page: $('jadwalPageSize').value,
            q: $('jadwalSearch').value.trim(),
            kegiatan_id: $('jadwalKegiatanFilter').value,
            access_state: $('jadwalAccessFilter').value,
        });
        try {
            const data = await api(base + '?' + params);
            if (sequence !== state.sequence) return;
            const body = $('jadwalRows'); body.replaceChildren();
            for (const item of data.items) {
                const row = document.createElement('tr');

                const bankCell = document.createElement('td');
                const bankName = document.createElement('div');
                bankName.className = 'fw-semibold';
                bankName.textContent = item.nama_bank || '-';
                const bankDetail = document.createElement('div');
                bankDetail.className = 'small text-secondary';
                bankDetail.textContent = (item.mapel_nama || '-') + ' · Tingkat ' + (item.tingkat || '-');
                bankCell.append(bankName, bankDetail);

                const startCell = document.createElement('td');
                startCell.append(document.createTextNode(displayDate(item.mulai_at)));
                const windowInfo = document.createElement('div');
                windowInfo.className = 'small text-secondary';
                windowInfo.textContent = windowLabel(item.window_state);
                startCell.append(windowInfo);

                const preparationCell = document.createElement('td');
                const preparationBadge = document.createElement('span');
                preparationBadge.className = 'badge ' + (item.preparation_state === 'READY'
                    ? 'text-bg-success'
                    : 'text-bg-secondary');
                preparationBadge.textContent = item.preparation_state === 'READY' ? 'READY' : 'DRAFT';
                preparationCell.append(preparationBadge);
                const preparationInfo = document.createElement('div');
                preparationInfo.className = 'small text-secondary mt-1';
                preparationInfo.textContent = item.preparation_state === 'READY'
                    ? (String(item.preparation_ready || 0) + '/' + String(item.preparation_total || 0) + ' assignment siap')
                    : (String(item.preparation_progress || 0) + '% siap');
                preparationCell.append(preparationInfo);

                const accessCell = document.createElement('td');
                const badge = document.createElement('span');
                badge.className = 'badge ' + (item.access_state === 'BUKA' ? 'text-bg-success' : 'text-bg-secondary');
                badge.textContent = item.access_state === 'BUKA' ? 'BUKA' : 'TAHAN';
                accessCell.append(badge);

                const actions = document.createElement('td');
                actions.className = 'text-nowrap';

                const followUp = document.createElement('a');
                followUp.className = 'btn btn-outline-primary btn-sm me-1';
                followUp.href = uiBase + '/' + item.id + '/susulan';
                followUp.textContent = 'Susulan';
                actions.append(followUp);

                const preparation = document.createElement('button');
                preparation.type = 'button';
                preparation.className = 'btn btn-outline-primary btn-sm me-1 js-preparation';
                preparation.dataset.jadwalId = String(item.id);
                preparation.dataset.title = (item.mapel_nama || item.nama_bank || ('Jadwal #' + item.id));
                preparation.textContent = 'Preparation';
                actions.append(preparation);

                actions.append(actionButton(
                    'Perpanjang',
                    'btn btn-outline-secondary btn-sm me-1',
                    () => openOperation(item.id, 'EXTEND', item.batas_mulai_at)
                ));

                actions.append(actionButton(
                    'Tambah Waktu',
                    'btn btn-outline-secondary btn-sm me-1',
                    () => openOperation(item.id, 'ADD_TIME'),
                    Number(item.active_attempt_count) < 1
                ));

                if (item.first_attempt_started_at === null) {
                    const visible = Number(item.tampilkan_nilai_saat_selesai) === 1;
                    actions.append(actionButton(
                        visible ? 'Nilai: ON' : 'Nilai: OFF',
                        'btn btn-outline-secondary btn-sm me-1',
                        async () => {
                            try {
                                await api(base + '/' + item.id + '/result-visibility', 'PATCH', {
                                    tampilkan_nilai_saat_selesai: !visible,
                                });
                                await load();
                            } catch (error) {
                                feedback('jadwalFeedback', error.message, true);
                            }
                        }
                    ));
                }

                if (item.access_editable) {
                    const target = item.access_state === 'BUKA' ? 'TAHAN' : 'BUKA';
                    actions.append(actionButton(
                        target === 'TAHAN' ? 'Tahan' : 'Buka',
                        'btn btn-outline-secondary btn-sm me-1',
                        async () => {
                            if (target === 'TAHAN'
                                && !window.confirm('Tahan akses Jadwal ini? Peserta yang belum masuk tidak dapat START/RESUME sampai akses dibuka kembali.'))
                                return;
                            try {
                                await api(base + '/' + item.id + '/access', 'PATCH', {access_state: target});
                                await load();
                                feedback('jadwalFeedback', 'Akses Jadwal menjadi ' + target + '.');
                            } catch (error) {
                                feedback('jadwalFeedback', error.message, true);
                            }
                        }
                    ));
                }
                if (item.structural_editable) {
                    actions.append(actionButton(
                        'Edit',
                        'btn btn-outline-primary btn-sm me-1',
                        () => openEdit(item.id)
                    ));
                    actions.append(actionButton(
                        'Hapus',
                        'btn btn-outline-danger btn-sm',
                        async () => {
                            if (!window.confirm('Hapus Jadwal untuk ' + (item.nama_mapel || item.nama_bank) + '?'))
                                return;
                            try {
                                await api(base + '/' + item.id, 'DELETE');
                                await load();
                                await loadOptions();
                                feedback('jadwalFeedback', 'Jadwal berhasil dihapus.');
                            } catch (error) {
                                feedback('jadwalFeedback', error.message, true);
                            }
                        }
                    ));
                } else {
                    const locked = document.createElement('span');
                    locked.className = 'small text-secondary ms-1';
                    locked.textContent = 'Struktur terkunci';
                    actions.append(locked);
                }

                row.append(
                    cell(item.kegiatan_nama),
                    bankCell,
                    startCell,
                    cell(item.urutan_ujian || 1, 'text-center fw-semibold'),
                    cell(displayDate(item.batas_mulai_at), 'text-nowrap'),
                    cell(durationLabel(item.durasi_seconds), 'text-nowrap'),
                    cell(selectionLabel(item.type_selection)),
                    cell(Number(item.tampilkan_nilai_saat_selesai) === 1 ? 'Ditampilkan' : 'Disembunyikan'),
                    preparationCell,
                    accessCell,
                    actions
                );
                body.append(row);
            }

            if (!data.items.length) {
                const row = document.createElement('tr');
                const td = cell('Belum ada Jadwal sesuai filter.');
                td.colSpan = 11;
                td.className = 'text-center text-secondary py-4';
                row.append(td);
                body.append(row);
            }

            state.page = Number(data.pagination.page);
            state.pages = Number(data.pagination.pages);
            $('jadwalCount').textContent = data.pagination.filtered + ' dari '
                + data.pagination.total + ' Jadwal';
            $('jadwalPageInfo').textContent = state.page + ' / ' + state.pages;
            $('jadwalPrevious').disabled = state.page <= 1;
            $('jadwalNext').disabled = state.page >= state.pages;
            feedback('jadwalFeedback', '');
        } catch (error) {
            if (sequence === state.sequence)
                feedback('jadwalFeedback', error.message, true);
        }
    };

    $('jadwalAdd').addEventListener('click', () => {
        resetForm();
        modal.show();
    });
    $('jadwalModal').addEventListener('hidden.bs.modal', resetForm);
    $('jadwalKegiatan').addEventListener('change', () => {
        populateBankForm(null);
        renderSelection();
    });
    $('jadwalBank').addEventListener('change', () => renderSelection());

    $('jadwalForm').addEventListener('submit', async event => {
        event.preventDefault();
        const submit = $('jadwalSubmit');
        submit.disabled = true;
        try {
            const durationMinutes = Number($('jadwalDurasi').value);
            await api(
                state.editing === null ? base : base + '/' + state.editing,
                state.editing === null ? 'POST' : 'PUT',
                {
                    kegiatan_id: Number($('jadwalKegiatan').value),
                    bank_soal_id: Number($('jadwalBank').value),
                    mulai_at: $('jadwalMulai').value,
                    batas_mulai_at: $('jadwalBatasMulai').value,
                    urutan_ujian: Number($('jadwalUrutan').value || 1),
                    durasi_seconds: Math.round(durationMinutes * 60),
                    access_state: $('jadwalAccess').value,
                    tampilkan_nilai_saat_selesai: $('jadwalShowScore').checked,
                    type_selection: collectSelection(),
                }
            );
            modal.hide();
            state.page = 1;
            await load();
            await loadOptions();
            feedback('jadwalFeedback', 'Jadwal berhasil disimpan.');
        } catch (error) {
            feedback('jadwalFormFeedback', error.message, true);
        } finally {
            submit.disabled = false;
        }
    });

    $('jadwalOperationForm').addEventListener('submit', async event => {
        event.preventDefault();
        const id = Number($('jadwalOperationId').value);
        const type = $('jadwalOperationType').value;
        const value = $('jadwalOperationValue').value;
        try {
            if (type === 'EXTEND') {
                await api(base + '/' + id + '/extend-start-window', 'POST', {
                    batas_mulai_at: value,
                });
            } else {
                await api(base + '/' + id + '/add-time', 'POST', {
                    seconds: Math.round(Number(value) * 60),
                    scope: 'ALL_ACTIVE',
                });
            }
            operationModal.hide();
            await load();
            feedback('jadwalFeedback', type === 'EXTEND'
                ? 'Batas Mulai berhasil diperpanjang.'
                : 'Tambahan waktu berhasil diterapkan.');
        } catch (error) {
            feedback('jadwalOperationFeedback', error.message, true);
        }
    });

    $('jadwalSearch').addEventListener('input', () => {
        clearTimeout(debounce);
        debounce = setTimeout(() => {
            state.page = 1;
            load();
        }, 300);
    });
    for (const id of ['jadwalKegiatanFilter', 'jadwalAccessFilter', 'jadwalPageSize']) {
        $(id).addEventListener('change', () => {
            state.page = 1;
            load();
        });
    }
    $('jadwalReset').addEventListener('click', () => {
        $('jadwalSearch').value = '';
        $('jadwalKegiatanFilter').value = '';
        $('jadwalAccessFilter').value = '';
        $('jadwalPageSize').value = '25';
        state.page = 1;
        load();
    });
    $('jadwalPrevious').addEventListener('click', () => {
        if (state.page > 1) {
            state.page--;
            load();
        }
    });
    $('jadwalNext').addEventListener('click', () => {
        if (state.page < state.pages) {
            state.page++;
            load();
        }
    });

    const init = async () => {
        await loadOptions();
        const requestedKegiatan = new URLSearchParams(window.location.search).get('kegiatan_id');
        if (requestedKegiatan
            && [...$('jadwalKegiatanFilter').options].some(option => option.value === requestedKegiatan))
            $('jadwalKegiatanFilter').value = requestedKegiatan;
        await load();
    };
    init().catch(error => feedback('jadwalFeedback', error.message, true));
})();
