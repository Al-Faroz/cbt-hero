(() => {
    'use strict';

    const app = document.getElementById('susulanApp');
    if (!app) return;

    const $ = id => document.getElementById(id);
    const base = app.dataset.api;
    const jadwalBase = app.dataset.jadwalApi;
    const mainId = Number(app.dataset.mainId);
    const formModal = bootstrap.Modal.getOrCreateInstance($('susulanModal'));
    const targetsModal = bootstrap.Modal.getOrCreateInstance($('susulanTargetsModal'));
    const operationModal = bootstrap.Modal.getOrCreateInstance($('jadwalOperationModal'));

    const state = {
        main: null,
        items: [],
        editing: null,
        selected: new Map(),
        candidatePage: 1,
        candidatePages: 1,
        candidateSeq: 0,
    };
    let searchTimer;

    const feedback = (id, text, error = false) => {
        const node = $(id);
        node.textContent = text;
        node.className = 'cbt-inline-feedback' + (text ? (error ? ' is-error' : ' is-info') : '');
    };

    const api = async (url, method = 'GET', payload = null, extraHeaders = {}) => {
        const headers = {Accept: 'application/json', ...extraHeaders};
        if (method !== 'GET') {
            headers['X-CSRF-TOKEN'] = document.querySelector('meta[name="csrf-token"]').content;
        }
        if (payload !== null) headers['Content-Type'] = 'application/json';
        const response = await fetch(url, {
            method,
            credentials: 'same-origin',
            headers,
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
        button.type = 'button';
        button.className = className;
        button.textContent = label;
        button.disabled = disabled;
        button.addEventListener('click', handler);
        return button;
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

    const selectionLabel = items => (items || [])
        .map(item => item.selection_count + ' ' + (item.label || item.question_type))
        .join(' · ') || '-';

    const targetModeLabel = mode =>
        mode === 'FIRST_ATTEMPT' ? 'Belum Pernah START' : 'Replacement';

    const idempotencyKey = () =>
        (window.crypto?.randomUUID?.() ?? ('susulan-' + Date.now() + '-' + Math.random().toString(36).slice(2)))
            .replaceAll(' ', '');

    const renderMain = () => {
        const main = state.main;
        if (!main) return;
        $('susulanMainBank').textContent = main.nama_bank + ' · Jadwal #' + main.id;
        $('susulanMainMapel').textContent = main.mapel_nama + ' · Tingkat ' + main.tingkat;
        $('susulanMainSelection').textContent = selectionLabel(main.type_selection);
    };

    const load = async () => {
        feedback('susulanFeedback', 'Memuat Susulan...');
        try {
            const data = await api(base);
            state.main = data.main;
            state.items = data.items || [];
            renderMain();

            const body = $('susulanRows');
            body.replaceChildren();
            state.items.forEach(item => {
                const row = document.createElement('tr');
                const access = document.createElement('td');
                const badge = document.createElement('span');
                badge.className = 'badge ' + (item.access_state === 'BUKA' ? 'text-bg-success' : 'text-bg-secondary');
                badge.textContent = item.access_state;
                access.append(badge);

                const actions = document.createElement('td');
                actions.className = 'text-nowrap';

                actions.append(actionButton(
                    'Target',
                    'btn btn-outline-primary btn-sm me-1',
                    () => openTargets(item.id)
                ));

                if (item.editable) {
                    actions.append(actionButton(
                        'Edit',
                        'btn btn-outline-primary btn-sm me-1',
                        () => openEdit(item.id)
                    ));
                }

                const targetAccess = item.access_state === 'BUKA' ? 'TAHAN' : 'BUKA';
                actions.append(actionButton(
                    targetAccess === 'TAHAN' ? 'Tahan' : 'Buka',
                    'btn btn-outline-secondary btn-sm me-1',
                    async () => {
                        try {
                            await api(jadwalBase + '/' + item.id + '/access', 'PATCH', {access_state: targetAccess});
                            await load();
                            feedback('susulanFeedback', 'Akses Susulan menjadi ' + targetAccess + '.');
                        } catch (error) {
                            feedback('susulanFeedback', error.message, true);
                        }
                    }
                ));

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
                        'btn btn-outline-secondary btn-sm',
                        async () => {
                            try {
                                await api(jadwalBase + '/' + item.id + '/result-visibility', 'PATCH', {
                                    tampilkan_nilai_saat_selesai: !visible,
                                });
                                await load();
                            } catch (error) {
                                feedback('susulanFeedback', error.message, true);
                            }
                        }
                    ));
                }

                row.append(
                    cell('Susulan #' + (item.susulan_no || item.id)),
                    cell(displayDate(item.mulai_at), 'text-nowrap'),
                    cell(displayDate(item.batas_mulai_at), 'text-nowrap'),
                    cell(durationLabel(item.durasi_seconds), 'text-nowrap'),
                    cell(item.target_count + ' peserta'),
                    access,
                    actions
                );
                body.append(row);
            });

            if (!state.items.length) {
                const row = document.createElement('tr');
                const td = cell('Belum ada Jadwal Susulan.');
                td.colSpan = 7;
                td.className = 'text-center text-secondary py-4';
                row.append(td);
                body.append(row);
            }
            feedback('susulanFeedback', '');
        } catch (error) {
            feedback('susulanFeedback', error.message, true);
        }
    };

    const updateSelectedCount = () => {
        $('susulanSelectedCount').textContent = state.selected.size + ' peserta dipilih';
        const visible = [...document.querySelectorAll('.susulan-candidate-check:not(:disabled)')];
        $('susulanCheckPage').checked = visible.length > 0 && visible.every(box => box.checked);
        $('susulanCheckPage').indeterminate = visible.some(box => box.checked) && !visible.every(box => box.checked);
    };

    const candidatePayload = item => ({
        peserta_kegiatan_id: Number(item.peserta_kegiatan_id),
        target_mode: item.target_mode,
        supersede_attempt_id: item.supersede_attempt_id == null ? null : Number(item.supersede_attempt_id),
    });

    const loadCandidates = async () => {
        const seq = ++state.candidateSeq;
        const params = new URLSearchParams({
            page: String(state.candidatePage),
            per_page: $('susulanCandidatePageSize').value,
            q: $('susulanCandidateSearch').value.trim(),
        });
        if (state.editing !== null) params.set('susulan_id', String(state.editing));
        feedback('susulanFormFeedback', 'Memuat daftar peserta...');

        try {
            const data = await api(base + '/candidates?' + params);
            if (seq !== state.candidateSeq) return;
            const body = $('susulanCandidateRows');
            body.replaceChildren();

            for (const item of data.items || []) {
                const row = document.createElement('tr');
                const selectCell = document.createElement('td');
                selectCell.className = 'text-center';

                const checkbox = document.createElement('input');
                checkbox.type = 'checkbox';
                checkbox.className = 'form-check-input susulan-candidate-check';
                checkbox.dataset.id = String(item.peserta_kegiatan_id);
                checkbox._candidate = item;
                checkbox.disabled = !item.eligible;
                checkbox.checked = state.selected.has(Number(item.peserta_kegiatan_id));
                checkbox.addEventListener('change', () => {
                    const id = Number(item.peserta_kegiatan_id);
                    if (checkbox.checked) state.selected.set(id, candidatePayload(item));
                    else state.selected.delete(id);
                    updateSelectedCount();
                });
                selectCell.append(checkbox);

                const statusCell = document.createElement('td');
                const mode = document.createElement('div');
                mode.className = item.eligible ? 'fw-semibold' : 'text-secondary';
                mode.textContent = item.eligible ? targetModeLabel(item.target_mode) : 'Tidak dapat dipilih';
                const reason = document.createElement('div');
                reason.className = 'small text-secondary';
                reason.textContent = item.reason || '';
                statusCell.append(mode, reason);

                row.append(
                    selectCell,
                    cell(item.nomor_peserta),
                    cell(item.nama),
                    cell(item.rombel),
                    statusCell
                );
                body.append(row);
            }

            if (!data.items?.length) {
                const row = document.createElement('tr');
                const td = cell('Tidak ada peserta sesuai pencarian.');
                td.colSpan = 5;
                td.className = 'text-center text-secondary py-4';
                row.append(td);
                body.append(row);
            }

            state.candidatePage = Number(data.pagination.page);
            state.candidatePages = Number(data.pagination.pages);
            $('susulanCandidateInfo').textContent = data.pagination.filtered + ' dari '
                + data.pagination.total + ' peserta';
            $('susulanCandidatePageInfo').textContent = state.candidatePage + ' / ' + state.candidatePages;
            $('susulanCandidatePrev').disabled = state.candidatePage <= 1;
            $('susulanCandidateNext').disabled = state.candidatePage >= state.candidatePages;
            updateSelectedCount();
            feedback('susulanFormFeedback', '');
        } catch (error) {
            if (seq === state.candidateSeq) feedback('susulanFormFeedback', error.message, true);
        }
    };

    const resetForm = () => {
        state.editing = null;
        state.selected.clear();
        state.candidatePage = 1;
        $('susulanForm').reset();
        $('susulanDurasi').value = state.main
            ? String(Math.max(1, Math.round(Number(state.main.durasi_seconds) / 60)))
            : '90';
        $('susulanAccess').value = 'TAHAN';
        $('susulanCandidateSearch').value = '';
        $('susulanCandidatePageSize').value = '25';
        $('susulanModalTitle').textContent = 'Tambah Susulan';
        feedback('susulanFormFeedback', '');
        updateSelectedCount();
    };

    const openCreate = async () => {
        resetForm();
        formModal.show();
        await loadCandidates();
    };

    const openEdit = async id => {
        resetForm();
        state.editing = Number(id);
        try {
            const data = await api(jadwalBase + '/' + id);
            const item = data.item;
            $('susulanMulai').value = inputDate(item.mulai_at);
            $('susulanBatasMulai').value = inputDate(item.batas_mulai_at);
            $('susulanDurasi').value = String(Math.max(1, Math.round(Number(item.durasi_seconds) / 60)));
            $('susulanAccess').value = item.access_state;
            for (const target of item.targets || []) {
                if (target.status !== 'TARGETED') continue;
                state.selected.set(Number(target.peserta_kegiatan_id), {
                    peserta_kegiatan_id: Number(target.peserta_kegiatan_id),
                    target_mode: target.target_mode,
                    supersede_attempt_id: target.supersede_attempt_id == null
                        ? null : Number(target.supersede_attempt_id),
                });
            }
            $('susulanModalTitle').textContent = 'Edit Susulan #' + item.id;
            formModal.show();
            await loadCandidates();
        } catch (error) {
            feedback('susulanFeedback', error.message, true);
        }
    };

    const openTargets = async id => {
        feedback('susulanTargetFeedback', '');
        $('susulanTargetsTitle').textContent = 'Target Susulan #' + id;
        try {
            const data = await api(jadwalBase + '/' + id);
            renderTargets(data.item);
            targetsModal.show();
        } catch (error) {
            feedback('susulanFeedback', error.message, true);
        }
    };

    const renderTargets = item => {
        const body = $('susulanTargetRows');
        body.replaceChildren();
        for (const target of item.targets || []) {
            const action = document.createElement('td');
            if (target.status === 'TARGETED') {
                action.append(actionButton(
                    'Batalkan',
                    'btn btn-outline-danger btn-sm',
                    async () => {
                        if (!window.confirm('Batalkan target Susulan untuk ' + target.nama + '?')) return;
                        try {
                            await api(
                                jadwalBase + '/' + item.id + '/targets/' + target.id + '/cancel',
                                'POST',
                                {}
                            );
                            const refreshed = await api(jadwalBase + '/' + item.id);
                            renderTargets(refreshed.item);
                            await load();
                            feedback('susulanTargetFeedback', 'Target dibatalkan.');
                        } catch (error) {
                            feedback('susulanTargetFeedback', error.message, true);
                        }
                    }
                ));
            } else {
                action.textContent = '-';
            }
            const row = document.createElement('tr');
            row.append(
                cell(target.nomor_peserta),
                cell(target.nama),
                cell(target.rombel),
                cell(targetModeLabel(target.target_mode)),
                cell(target.status === 'TARGETED' ? 'Aktif' : 'Dibatalkan'),
                action
            );
            body.append(row);
        }
        if (!item.targets?.length) {
            const row = document.createElement('tr');
            const td = cell('Tidak ada target.');
            td.colSpan = 6; td.className = 'text-center text-secondary py-4';
            row.append(td); body.append(row);
        }
    };

    const openOperation = (id, type, currentValue = '') => {
        $('jadwalOperationId').value = String(id);
        $('jadwalOperationType').value = type;
        $('jadwalOperationFields').replaceChildren();
        feedback('jadwalOperationFeedback', '');

        if (type === 'EXTEND') {
            $('jadwalOperationTitle').textContent = 'Perpanjang Batas Mulai';
            const label = document.createElement('label');
            label.className = 'cbt-form-label';
            label.htmlFor = 'jadwalOperationValue';
            label.textContent = 'Batas Mulai baru';
            const input = document.createElement('input');
            input.className = 'form-control';
            input.type = 'datetime-local';
            input.id = 'jadwalOperationValue';
            input.value = inputDate(currentValue);
            input.required = true;
            const help = document.createElement('div');
            help.className = 'form-text';
            help.textContent = 'Waktu baru harus lebih akhir dari Batas Mulai sekarang.';
            $('jadwalOperationFields').append(label, input, help);
        } else {
            $('jadwalOperationTitle').textContent = 'Tambah Waktu';
            const label = document.createElement('label');
            label.className = 'cbt-form-label';
            label.htmlFor = 'jadwalOperationValue';
            label.textContent = 'Tambahan waktu untuk semua peserta aktif (menit)';
            const input = document.createElement('input');
            input.className = 'form-control';
            input.type = 'number';
            input.id = 'jadwalOperationValue';
            input.min = '1';
            input.step = '1';
            input.value = '10';
            input.required = true;
            const help = document.createElement('div');
            help.className = 'form-text';
            help.textContent = 'Hanya Attempt yang masih aktif pada Susulan ini yang mendapat tambahan waktu.';
            $('jadwalOperationFields').append(label, input, help);
        }
        operationModal.show();
    };

    $('susulanAdd').addEventListener('click', openCreate);
    $('susulanModal').addEventListener('hidden.bs.modal', resetForm);

    $('susulanCheckPage').addEventListener('change', event => {
        for (const checkbox of document.querySelectorAll('.susulan-candidate-check:not(:disabled)')) {
            checkbox.checked = event.target.checked;
            const id = Number(checkbox.dataset.id);
            const row = checkbox.closest('tr');
            if (!row) continue;
            const candidate = checkbox._candidate;
            if (!candidate) continue;
            if (checkbox.checked) state.selected.set(id, candidatePayload(candidate));
            else state.selected.delete(id);
        }
        updateSelectedCount();
    });

    $('susulanCandidateSearch').addEventListener('input', () => {
        clearTimeout(searchTimer);
        searchTimer = setTimeout(() => {
            state.candidatePage = 1;
            loadCandidates();
        }, 250);
    });
    $('susulanCandidatePageSize').addEventListener('change', () => {
        state.candidatePage = 1;
        loadCandidates();
    });
    $('susulanCandidatePrev').addEventListener('click', () => {
        if (state.candidatePage > 1) {
            state.candidatePage--;
            loadCandidates();
        }
    });
    $('susulanCandidateNext').addEventListener('click', () => {
        if (state.candidatePage < state.candidatePages) {
            state.candidatePage++;
            loadCandidates();
        }
    });

    $('susulanForm').addEventListener('submit', async event => {
        event.preventDefault();
        const submit = $('susulanSubmit');
        submit.disabled = true;
        try {
            const payload = {
                mulai_at: $('susulanMulai').value,
                batas_mulai_at: $('susulanBatasMulai').value,
                durasi_seconds: Math.round(Number($('susulanDurasi').value) * 60),
                access_state: $('susulanAccess').value,
                targets: [...state.selected.values()],
            };
            if (state.editing === null) {
                await api(base, 'POST', payload, {'Idempotency-Key': idempotencyKey()});
            } else {
                await api(jadwalBase + '/' + state.editing, 'PUT', payload);
            }
            formModal.hide();
            await load();
            feedback('susulanFeedback', 'Jadwal Susulan berhasil disimpan.');
        } catch (error) {
            feedback('susulanFormFeedback', error.message, true);
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
                await api(jadwalBase + '/' + id + '/extend-start-window', 'POST', {
                    batas_mulai_at: value,
                });
            } else {
                await api(jadwalBase + '/' + id + '/add-time', 'POST', {
                    seconds: Math.round(Number(value) * 60),
                    scope: 'ALL_ACTIVE',
                });
            }
            operationModal.hide();
            await load();
            feedback('susulanFeedback', type === 'EXTEND'
                ? 'Batas Mulai berhasil diperpanjang.'
                : 'Tambahan waktu berhasil diterapkan.');
        } catch (error) {
            feedback('jadwalOperationFeedback', error.message, true);
        }
    });

    const init = async () => {
        await load();
        resetForm();
    };

    init().catch(error => feedback('susulanFeedback', error.message, true));
})();
