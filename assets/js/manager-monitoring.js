(() => {
    'use strict';

    const app = document.getElementById('monitoringApp');
    if (!app) return;

    const $ = id => document.getElementById(id);
    const base = app.dataset.api;
    const detailModal = bootstrap.Modal.getOrCreateInstance($('monitorDetailModal'));
    const actionModal = bootstrap.Modal.getOrCreateInstance($('monitorActionModal'));

    const state = {
        options: {kegiatan: [], jadwal: [], ruang: []},
        page: 1,
        pages: 1,
        selected: new Set(),
        visibleActive: new Set(),
        currentRows: new Map(),
        loading: false,
        timer: null,
        sequence: 0,
        actionIds: [],
    };
    let searchTimer = null;

    const feedback = (id, text, error = false) => {
        const node = $(id);
        node.textContent = text;
        node.className = 'cbt-inline-feedback' + (text ? (error ? ' is-error' : ' is-info') : '');
    };

    const api = async (url, method = 'GET', payload = null, extraHeaders = {}) => {
        const headers = {Accept: 'application/json', ...extraHeaders};
        if (method !== 'GET') headers['X-CSRF-TOKEN'] = document.querySelector('meta[name="csrf-token"]').content;
        if (payload !== null) headers['Content-Type'] = 'application/json';

        const response = await fetch(url, {
            method, credentials: 'same-origin', headers,
            ...(payload === null ? {} : {body: JSON.stringify(payload)}),
        });
        const result = await response.json().catch(() => null);
        if (!response.ok || result?.ok !== true) {
            const error = new Error(result?.error?.message ?? 'Permintaan gagal.');
            error.code = result?.error?.code ?? '';
            error.fields = result?.error?.fields ?? {};
            throw error;
        }
        return result.data;
    };

    const uniqueKey = prefix =>
        prefix + ':' + (window.crypto?.randomUUID?.()
            ?? (Date.now() + '-' + Math.random().toString(36).slice(2)));

    const formatDuration = seconds => {
        if (seconds == null) return '-';
        const value = Math.max(0, Number(seconds) || 0);
        const h = Math.floor(value / 3600);
        const m = Math.floor((value % 3600) / 60);
        const s = value % 60;
        return (h > 0 ? String(h).padStart(2, '0') + ':' : '')
            + String(m).padStart(2, '0') + ':' + String(s).padStart(2, '0');
    };

    const formatDate = value => {
        if (!value) return '-';
        const text = String(value).replace('T', ' ');
        const [date, time] = text.split(' ');
        if (!date || !time) return text;
        const [y, m, d] = date.split('-');
        return d + '/' + m + '/' + y + ' ' + time.slice(0, 8);
    };

    const cell = (text, className = '') => {
        const td = document.createElement('td');
        td.className = className;
        td.textContent = text == null || text === '' ? '-' : String(text);
        return td;
    };

    const button = (label, className, handler) => {
        const node = document.createElement('button');
        node.type = 'button';
        node.className = className;
        node.textContent = label;
        node.addEventListener('click', handler);
        return node;
    };

    const statusBadge = row => {
        const wrapper = document.createElement('td');
        const main = document.createElement('span');
        main.className = 'badge '
            + (row.status === 'SEDANG' ? 'text-bg-primary'
                : row.status === 'SELESAI' ? 'text-bg-success' : 'text-bg-secondary');
        main.textContent = row.status === 'SEDANG' ? 'SEDANG'
            : row.status === 'SELESAI' ? 'SELESAI' : 'BELUM';
        wrapper.append(main);

        if (row.status === 'SEDANG') {
            const connection = document.createElement('div');
            connection.className = 'small mt-1 '
                + (row.connection_state === 'ONLINE' ? 'text-success'
                    : row.connection_state === 'RESET_AKSES' ? 'text-warning' : 'text-danger');
            connection.textContent = row.connection_state === 'ONLINE' ? 'Online'
                : row.connection_state === 'RESET_AKSES' ? 'Reset Akses'
                : 'Tidak terdeteksi';
            wrapper.append(connection);
        }
        return wrapper;
    };

    const scheduleLabel = item => {
        const suffix = item.jenis_jadwal === 'SUSULAN' ? ' · Susulan' : '';
        return item.kegiatan_nama + ' · ' + item.nama_mapel + ' · Tingkat ' + item.tingkat + suffix;
    };

    const populateOptions = data => {
        state.options = data;
        $('monitorKegiatan').replaceChildren(
            new Option('Pilih Kegiatan', ''),
            ...data.kegiatan.map(item => new Option(item.nama + ' · ' + item.status, item.id))
        );
        $('monitorRuang').replaceChildren(
            new Option('Semua Ruang', ''),
            ...data.ruang.map(item => new Option(item.nama, item.id))
        );

        const firstAvailable = data.kegiatan[0] ?? null;
        if (firstAvailable) $('monitorKegiatan').value = String(firstAvailable.id);
        populateSchedules();
    };

    const populateSchedules = () => {
        const kegiatanId = Number($('monitorKegiatan').value || 0);
        const current = $('monitorJadwal').value;
        const rows = state.options.jadwal.filter(item => !kegiatanId || Number(item.kegiatan_id) === kegiatanId);
        $('monitorJadwal').replaceChildren(
            new Option('Pilih Jadwal', ''),
            ...rows.map(item => new Option(scheduleLabel(item), item.id))
        );
        if (rows.some(item => String(item.id) === current)) $('monitorJadwal').value = current;
        else if (rows.length) $('monitorJadwal').value = String(rows[0].id);
    };

    const query = () => {
        const params = new URLSearchParams({
            jadwal_id: $('monitorJadwal').value,
            q: $('monitorSearch').value.trim(),
            status: $('monitorStatus').value,
            ruang_id: $('monitorRuang').value,
        });
        return params;
    };

    const listQuery = () => {
        const params = query();
        params.set('page', String(state.page));
        params.set('per_page', $('monitorPageSize').value);
        return params;
    };

    const renderSummary = data => {
        const summary = data.summary ?? {};
        $('monitorTotal').textContent = String(summary.total ?? 0);
        $('monitorBelum').textContent = String(summary.belum ?? 0);
        $('monitorSedang').textContent = String(summary.sedang ?? 0);
        $('monitorSelesai').textContent = String(summary.selesai ?? 0);
        $('monitorUndetected').textContent = String(summary.tidak_terdeteksi ?? 0);
    };

    const clearSelection = () => {
        state.selected.clear();
        updateSelection();
    };

    const updateSelection = () => {
        $('monitorSelected').textContent = state.selected.size + ' dipilih';
        const enabled = state.selected.size > 0;
        $('monitorBulkReset').disabled = !enabled;
        $('monitorBulkTime').disabled = !enabled;
        $('monitorBulkFinish').disabled = !enabled;

        const visible = [...state.visibleActive];
        $('monitorCheckPage').disabled = visible.length === 0;
        $('monitorCheckPage').checked = visible.length > 0 && visible.every(id => state.selected.has(id));
        $('monitorCheckPage').indeterminate = visible.some(id => state.selected.has(id))
            && !visible.every(id => state.selected.has(id));
    };

    const renderRows = data => {
        const body = $('monitorRows');
        body.replaceChildren();
        state.visibleActive.clear();
        state.currentRows.clear();

        for (const row of data.items || []) {
            if (row.attempt_id != null) state.currentRows.set(Number(row.attempt_id), row);

            const tr = document.createElement('tr');
            const select = document.createElement('td');
            select.className = 'text-center';
            const checkbox = document.createElement('input');
            checkbox.type = 'checkbox';
            checkbox.className = 'form-check-input';
            checkbox.setAttribute('aria-label', 'Pilih Attempt');
            const active = row.attempt_id != null && row.attempt_status === 'ACTIVE';
            checkbox.disabled = !active;
            if (active) {
                const id = Number(row.attempt_id);
                state.visibleActive.add(id);
                checkbox.checked = state.selected.has(id);
                checkbox.addEventListener('change', () => {
                    if (checkbox.checked) state.selected.add(id);
                    else state.selected.delete(id);
                    updateSelection();
                });
            }
            select.append(checkbox);

            const sync = document.createElement('td');
            sync.className = 'text-nowrap';
            sync.textContent = formatDate(row.last_sync_at);
            if (row.pending_sync_risk && row.status === 'SEDANG') {
                const risk = document.createElement('div');
                risk.className = 'small text-warning';
                risk.textContent = 'Perlu perhatian';
                sync.append(risk);
            }

            const actions = document.createElement('td');
            actions.className = 'text-nowrap';
            if (row.attempt_id != null) {
                actions.append(button('Detail', 'btn btn-outline-primary btn-sm me-1', () => openDetail(row.attempt_id)));
            }
            if (active) {
                actions.append(
                    button('Reset', 'btn btn-outline-secondary btn-sm me-1',
                        () => openAction('RESET', [Number(row.attempt_id)])),
                    button('+ Waktu', 'btn btn-outline-secondary btn-sm me-1',
                        () => openAction('TIME', [Number(row.attempt_id)])),
                    button('Selesai', 'btn btn-outline-danger btn-sm',
                        () => openAction('FINISH', [Number(row.attempt_id)]))
                );
            }

            tr.append(
                select,
                cell(row.nomor_peserta, 'text-nowrap'),
                cell(row.nama),
                cell(row.rombel, 'text-nowrap'),
                cell(row.ruang, 'text-nowrap'),
                statusBadge(row),
                cell(formatDuration(row.used_seconds), 'text-nowrap'),
                cell(formatDuration(row.remaining_seconds), 'text-nowrap'),
                sync,
                actions
            );
            body.append(tr);
        }

        if (!data.items?.length) {
            const tr = document.createElement('tr');
            const td = cell('Belum ada peserta untuk filter ini.');
            td.colSpan = 10;
            td.className = 'text-center text-secondary py-4';
            tr.append(td);
            body.append(tr);
        }

        state.page = Number(data.pagination.page || 1);
        state.pages = Number(data.pagination.pages || 1);
        $('monitorCount').textContent = Number(data.pagination.filtered || 0)
            + ' dari ' + Number(data.pagination.total || 0) + ' peserta';
        $('monitorPageInfo').textContent = state.page + ' / ' + state.pages;
        $('monitorPrev').disabled = state.page <= 1;
        $('monitorNext').disabled = state.page >= state.pages;
        updateSelection();
    };

    const load = async (silent = false) => {
        if (state.loading || !$('monitorJadwal').value) return;
        state.loading = true;
        const seq = ++state.sequence;
        if (!silent) feedback('monitorFeedback', 'Memuat Monitoring...');

        try {
            const [summary, list] = await Promise.all([
                api(base + '/summary?' + query()),
                api(base + '/attempts?' + listQuery()),
            ]);
            if (seq !== state.sequence) return;
            renderSummary(summary);
            renderRows(list);
            $('monitorLastRefresh').textContent = 'Terakhir diperbarui ' + new Date().toLocaleTimeString('id-ID');
            if (!silent) feedback('monitorFeedback', '');
        } catch (error) {
            if (seq === state.sequence) feedback('monitorFeedback', error.message, true);
        } finally {
            state.loading = false;
        }
    };

    const openDetail = async attemptId => {
        $('monitorDetailTitle').textContent = 'Detail Attempt #' + attemptId;
        $('monitorDetailBody').textContent = 'Memuat detail...';
        detailModal.show();

        try {
            const data = await api(base + '/attempts/' + attemptId);
            const attempt = data.attempt;
            const summary = data.response_summary;

            const grid = document.createElement('div');
            grid.className = 'row g-3';
            const fields = [
                ['Peserta', attempt.nomor_peserta + ' · ' + attempt.nama],
                ['Rombel / Ruang', attempt.rombel + ' · ' + (attempt.ruang || '-')],
                ['Ujian', attempt.ujian + ' · ' + attempt.jenis_jadwal],
                ['Status', attempt.status + (attempt.finish_reason ? ' · ' + attempt.finish_reason : '')],
                ['Mulai', formatDate(attempt.start_at)],
                ['Deadline', formatDate(attempt.deadline_at)],
                ['Sisa Waktu', formatDuration(attempt.remaining_seconds)],
                ['Last Sync', formatDate(attempt.last_sync_at)],
                ['Jawaban tersimpan', summary.saved_count + ' item'],
                ['Jawaban terisi', summary.answered_count + ' item'],
                ['Ragu-ragu', summary.flagged_count + ' item'],
                ['Client Generation', attempt.client_generation],
            ];
            for (const [label, value] of fields) {
                const col = document.createElement('div');
                col.className = 'col-md-6';
                const box = document.createElement('div');
                box.className = 'border rounded p-3 h-100';
                const small = document.createElement('div');
                small.className = 'small text-secondary';
                small.textContent = label;
                const strong = document.createElement('div');
                strong.className = 'fw-semibold';
                strong.textContent = String(value ?? '-');
                box.append(small, strong);
                col.append(box);
                grid.append(col);
            }

            const events = document.createElement('div');
            events.className = 'mt-4';
            const title = document.createElement('h3');
            title.className = 'fs-6';
            title.textContent = 'Aktivitas Client Terakhir';
            events.append(title);
            const list = document.createElement('ul');
            list.className = 'small mb-0';
            for (const event of data.client_events || []) {
                const li = document.createElement('li');
                li.textContent = formatDate(event.created_at) + ' · ' + event.event_type;
                list.append(li);
            }
            if (!(data.client_events || []).length) {
                const li = document.createElement('li');
                li.textContent = 'Belum ada event client.';
                list.append(li);
            }
            events.append(list);

            $('monitorDetailBody').replaceChildren(grid, events);
        } catch (error) {
            $('monitorDetailBody').textContent = error.message;
        }
    };

    const openAction = (type, ids) => {
        state.actionIds = [...new Set(ids.map(Number).filter(id => id > 0))];
        $('monitorActionType').value = type;
        $('monitorActionFields').replaceChildren();
        feedback('monitorActionFeedback', '');

        const reasonLabel = document.createElement('label');
        reasonLabel.className = 'cbt-form-label';
        reasonLabel.htmlFor = 'monitorActionReason';
        reasonLabel.textContent = 'Alasan';
        const reason = document.createElement('input');
        reason.className = 'form-control';
        reason.id = 'monitorActionReason';
        reason.maxLength = 255;
        reason.placeholder = 'Alasan operasional';
        reason.required = true;

        if (type === 'RESET') {
            $('monitorActionTitle').textContent = 'Reset Akses · ' + state.actionIds.length + ' Attempt';
            $('monitorActionFields').append(reasonLabel, reason);
        } else if (type === 'TIME') {
            $('monitorActionTitle').textContent = 'Tambah Waktu · ' + state.actionIds.length + ' Attempt';
            const timeLabel = document.createElement('label');
            timeLabel.className = 'cbt-form-label mt-3';
            timeLabel.htmlFor = 'monitorActionMinutes';
            timeLabel.textContent = 'Tambahan waktu (menit)';
            const minutes = document.createElement('input');
            minutes.className = 'form-control';
            minutes.id = 'monitorActionMinutes';
            minutes.type = 'number';
            minutes.min = '1';
            minutes.max = '720';
            minutes.step = '1';
            minutes.value = '10';
            minutes.required = true;
            $('monitorActionFields').append(reasonLabel, reason, timeLabel, minutes);
        } else {
            $('monitorActionTitle').textContent = 'Paksa Selesai · ' + state.actionIds.length + ' Attempt';
            const warning = document.createElement('div');
            warning.className = 'alert alert-warning small';
            const hasRisk = state.actionIds.some(id => state.currentRows.get(id)?.pending_sync_risk);
            warning.textContent = hasRisk
                ? 'Ada peserta dengan sinkronisasi yang belum meyakinkan. Jawaban yang masih hanya tersimpan di perangkat tidak dapat diambil server setelah Paksa Selesai.'
                : 'Paksa Selesai mengakhiri Attempt secara authoritative dan membuat snapshot hasil dari jawaban yang sudah diterima server.';
            const checkWrap = document.createElement('div');
            checkWrap.className = 'form-check mt-3';
            const check = document.createElement('input');
            check.type = 'checkbox';
            check.className = 'form-check-input';
            check.id = 'monitorActionRisk';
            const checkLabel = document.createElement('label');
            checkLabel.className = 'form-check-label';
            checkLabel.htmlFor = check.id;
            checkLabel.textContent = 'Saya memahami risiko jawaban yang belum tersinkron.';
            checkWrap.append(check, checkLabel);
            $('monitorActionFields').append(warning, reasonLabel, reason, checkWrap);
        }

        actionModal.show();
    };

    const submitAction = async () => {
        const type = $('monitorActionType').value;
        const reason = $('monitorActionReason')?.value.trim() || '';
        if (!reason) throw new Error('Isi alasan operasional.');

        let endpoint = '';
        let payload = {attempt_ids: state.actionIds, reason};
        if (type === 'RESET') {
            endpoint = base + '/reset-access';
        } else if (type === 'TIME') {
            const minutes = Number($('monitorActionMinutes')?.value || 0);
            if (!Number.isFinite(minutes) || minutes < 1) throw new Error('Tambahan waktu harus lebih dari 0 menit.');
            endpoint = base + '/add-time';
            payload.seconds_added = Math.round(minutes * 60);
        } else {
            endpoint = base + '/force-finish';
            payload.confirm_pending_sync_risk = Boolean($('monitorActionRisk')?.checked);
            if (!payload.confirm_pending_sync_risk)
                throw new Error('Centang konfirmasi risiko sebelum Paksa Selesai.');
        }

        return api(endpoint, 'POST', payload, {
            'Idempotency-Key': uniqueKey('monitor-' + type.toLowerCase()),
        });
    };

    const scheduleTimer = () => {
        clearTimeout(state.timer);
        const baseSeconds = Number($('monitorRefresh').value || 10);
        const seconds = document.hidden ? Math.max(30, baseSeconds) : baseSeconds;
        state.timer = setTimeout(async () => {
            if (!document.querySelector('.modal.show')) await load(true);
            scheduleTimer();
        }, seconds * 1000);
    };

    $('monitorKegiatan').addEventListener('change', () => {
        populateSchedules();
        state.page = 1;
        clearSelection();
        load();
    });
    $('monitorJadwal').addEventListener('change', () => {
        state.page = 1;
        clearSelection();
        load();
    });
    for (const id of ['monitorStatus', 'monitorRuang', 'monitorPageSize']) {
        $(id).addEventListener('change', () => {
            state.page = 1;
            clearSelection();
            load();
        });
    }
    $('monitorSearch').addEventListener('input', () => {
        clearTimeout(searchTimer);
        searchTimer = setTimeout(() => {
            state.page = 1;
            clearSelection();
            load();
        }, 300);
    });
    $('monitorReload').addEventListener('click', () => load());
    $('monitorRefresh').addEventListener('change', scheduleTimer);
    document.addEventListener('visibilitychange', scheduleTimer);

    $('monitorPrev').addEventListener('click', () => {
        if (state.page > 1) {
            state.page--;
            clearSelection();
            load();
        }
    });
    $('monitorNext').addEventListener('click', () => {
        if (state.page < state.pages) {
            state.page++;
            clearSelection();
            load();
        }
    });

    $('monitorCheckPage').addEventListener('change', event => {
        for (const id of state.visibleActive) {
            if (event.target.checked) state.selected.add(id);
            else state.selected.delete(id);
        }
        for (const checkbox of document.querySelectorAll('#monitorRows input[type="checkbox"]:not(:disabled)'))
            checkbox.checked = event.target.checked;
        updateSelection();
    });

    $('monitorBulkReset').addEventListener('click', () => openAction('RESET', [...state.selected]));
    $('monitorBulkTime').addEventListener('click', () => openAction('TIME', [...state.selected]));
    $('monitorBulkFinish').addEventListener('click', () => openAction('FINISH', [...state.selected]));

    $('monitorActionForm').addEventListener('submit', async event => {
        event.preventDefault();
        try {
            const data = await submitAction();
            actionModal.hide();
            clearSelection();
            await load();
            feedback('monitorFeedback', 'Command berhasil diterapkan pada ' + Number(data.affected_count ?? 0) + ' Attempt.');
        } catch (error) {
            feedback('monitorActionFeedback', error.message, true);
        }
    });

    const init = async () => {
        feedback('monitorFeedback', 'Memuat pilihan Monitoring...');
        try {
            populateOptions(await api(base + '/options'));
            feedback('monitorFeedback', '');
            await load();
            scheduleTimer();
        } catch (error) {
            feedback('monitorFeedback', error.message, true);
        }
    };

    init();
})();
