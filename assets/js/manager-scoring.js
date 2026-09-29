(() => {
    'use strict';

    const app = document.getElementById('scoringApp');
    if (!app) return;

    const $ = id => document.getElementById(id);
    const optionsApi = app.dataset.optionsApi;
    const scoringApi = app.dataset.scoringApi;
    const resultsApi = app.dataset.resultsApi;
    const labels = {
        PG: 'Pilihan Ganda',
        PG_KOMPLEKS: 'PG Kompleks',
        PG_BERTINGKAT: 'PG Bertingkat',
        MATCHING: 'Menjodohkan',
        ISIAN_SINGKAT: 'Isian Singkat',
        URAIAN: 'Uraian'
    };

    const state = {
        kegiatan: [],
        jadwal: [],
        questions: [],
        selectedQuestion: null,
        selectedJadwal: 0,
        responses: [],
        operation: null
    };

    const feedback = (id, message, error = false) => {
        const node = $(id);
        if (!node) return;
        node.textContent = message || '';
        node.className = 'cbt-inline-feedback' + (message ? (error ? ' is-error' : ' is-info') : '');
    };

    const api = async (url, method = 'GET', payload = null, idempotencyKey = null) => {
        const headers = {Accept: 'application/json'};
        if (method !== 'GET') headers['X-CSRF-TOKEN'] = document.querySelector('meta[name="csrf-token"]')?.content || '';
        if (payload !== null) headers['Content-Type'] = 'application/json';
        if (idempotencyKey) headers['Idempotency-Key'] = idempotencyKey;

        const response = await fetch(url, {
            method,
            credentials: 'same-origin',
            headers,
            ...(payload === null ? {} : {body: JSON.stringify(payload)})
        });
        const result = await response.json().catch(() => null);
        if (!response.ok || result?.ok !== true) {
            const error = new Error(result?.error?.message || 'Permintaan gagal.');
            error.code = result?.error?.code || '';
            error.status = response.status;
            throw error;
        }
        return result.data;
    };

    const operationKey = prefix => {
        const random = window.crypto?.randomUUID?.() || (Date.now() + '-' + Math.random().toString(16).slice(2));
        return 'phase8:' + prefix + ':' + random;
    };

    const numberText = value => {
        if (value === null || value === undefined || value === '') return '-';
        const n = Number(value);
        return Number.isFinite(n) ? n.toFixed(4).replace(/\.?0+$/, '') : String(value);
    };

    const plain = value => {
        const temp = document.createElement('div');
        temp.innerHTML = String(value || '');
        return (temp.textContent || '').replace(/\s+/g, ' ').trim();
    };

    const answerText = raw => {
        if (!raw) return '-';
        let payload = raw;
        if (typeof raw === 'string') {
            try { payload = JSON.parse(raw); } catch (_) { return raw; }
        }
        if (!payload || typeof payload !== 'object') return '-';
        if (Array.isArray(payload.selected)) return payload.selected.join(', ') || '-';
        if (payload.selected !== undefined) return String(payload.selected || '-');
        if (payload.value !== undefined) return String(payload.value || '-');
        if (payload.text !== undefined) return String(payload.text || '-');
        if (payload.pairs && typeof payload.pairs === 'object') {
            return Object.entries(payload.pairs)
                .filter(([, right]) => right)
                .map(([left, right]) => left + ' → ' + right)
                .join('; ') || '-';
        }
        return JSON.stringify(payload);
    };

    const cell = (text, className = '') => {
        const td = document.createElement('td');
        if (className) td.className = className;
        td.textContent = text;
        return td;
    };

    const badge = (text, tone = 'secondary') => {
        const span = document.createElement('span');
        span.className = 'badge text-bg-' + tone;
        span.textContent = text;
        return span;
    };

    const populateKegiatan = () => {
        const select = $('scoringKegiatan');
        const current = select.value;
        select.replaceChildren(new Option('Pilih Kegiatan', ''));
        for (const item of state.kegiatan) {
            const suffix = item.status ? ' · ' + item.status : '';
            select.add(new Option(item.nama + suffix, item.id));
        }
        if ([...select.options].some(option => option.value === current)) select.value = current;
    };

    const populateJadwal = () => {
        const select = $('scoringJadwal');
        const kegiatanId = Number($('scoringKegiatan').value || 0);
        select.replaceChildren(new Option('Pilih Jadwal', ''));
        const list = state.jadwal.filter(item => !kegiatanId || Number(item.kegiatan_id) === kegiatanId);
        for (const item of list) {
            const mapel = item.nama_mapel || item.nama_bank || 'Jadwal #' + item.id;
            const kind = item.jenis_jadwal === 'SUSULAN' ? ' · Susulan' : '';
            const date = item.mulai_at ? ' · ' + item.mulai_at : '';
            select.add(new Option(mapel + kind + date, item.id));
        }
        if (state.selectedJadwal && [...select.options].some(option => Number(option.value) === state.selectedJadwal)) {
            select.value = String(state.selectedJadwal);
        } else {
            state.selectedJadwal = 0;
        }
        updateActions();
    };

    const updateActions = () => {
        const ready = state.selectedJadwal > 0;
        $('scoringReload').disabled = !ready;
        $('scoringRescore').disabled = !ready;
        $('scoringFinalize').disabled = !ready;
    };

    const renderQuestions = () => {
        const body = $('scoringQuestionRows');
        body.replaceChildren();
        $('scoringQuestionCount').textContent = state.questions.length + ' soal';

        if (!state.selectedJadwal) {
            const tr = document.createElement('tr');
            const td = cell('Pilih Jadwal terlebih dahulu.', 'text-center text-secondary py-4');
            td.colSpan = 6; tr.append(td); body.append(tr); return;
        }
        if (!state.questions.length) {
            const tr = document.createElement('tr');
            const td = cell('Belum ada soal/respons yang dapat ditampilkan untuk filter ini.', 'text-center text-secondary py-4');
            td.colSpan = 6; tr.append(td); body.append(tr); return;
        }

        for (const item of state.questions) {
            const tr = document.createElement('tr');
            const summary = plain(item.question_html).slice(0, 150) || 'Soal #' + item.question_id;
            tr.append(
                cell(summary),
                cell(labels[item.question_type] || item.question_type || '-'),
                cell(String(Number(item.response_count || 0)), 'text-nowrap'),
                cell(String(Number(item.pending_count || 0)), 'text-nowrap')
            );

            const statusTd = document.createElement('td');
            if (String(item.change_kind) === 'VOID' || String(item.question_status) === 'VOID') statusTd.append(badge('VOID', 'warning'));
            else if (Number(item.pending_count || 0) > 0) statusTd.append(badge('Perlu diperiksa', 'warning'));
            else statusTd.append(badge('Terskor', 'success'));
            tr.append(statusTd);

            const action = document.createElement('td');
            const button = document.createElement('button');
            button.type = 'button';
            button.className = 'btn btn-outline-primary btn-sm';
            button.dataset.questionId = String(item.question_id);
            button.textContent = 'Buka Jawaban';
            action.append(button);
            tr.append(action);
            body.append(tr);
        }
    };

    const renderResponses = () => {
        const body = $('scoringResponseRows');
        body.replaceChildren();
        $('scoringResponseCount').textContent = state.responses.length + ' respons';

        if (!state.responses.length) {
            const tr = document.createElement('tr');
            const td = cell('Belum ada respons tersimpan untuk soal ini.', 'text-center text-secondary py-4');
            td.colSpan = 9; tr.append(td); body.append(tr); return;
        }

        for (const item of state.responses) {
            const tr = document.createElement('tr');
            tr.append(
                cell(item.nomor_peserta_snapshot || '-'),
                cell(item.nama_snapshot || '-'),
                cell(item.rombel_snapshot || '-'),
                cell(answerText(item.answer_payload)),
                cell(numberText(item.auto_score), 'text-nowrap'),
                cell(numberText(item.manual_score), 'text-nowrap'),
                cell(numberText(item.effective_score), 'text-nowrap')
            );

            const statusTd = document.createElement('td');
            const scoringState = String(item.scoring_state || '-');
            const tone = ['PENDING_MANUAL', 'NEEDS_REVIEW'].includes(scoringState)
                ? 'warning' : (scoringState === 'MANUAL' ? 'info' : 'secondary');
            statusTd.append(badge(scoringState, tone));
            tr.append(statusTd);

            const action = document.createElement('td');
            const button = document.createElement('button');
            button.type = 'button';
            button.className = 'btn btn-outline-primary btn-sm';
            button.dataset.responseId = String(item.response_id);
            button.textContent = item.manual_score === null ? 'Beri Nilai' : 'Koreksi';
            button.disabled = String(item.attempt_status) !== 'FINISHED' || Boolean(item.results_finalized_at);
            button.dataset.maxPoint = String(item.max_point ?? '');
            button.dataset.currentScore = String(item.manual_score ?? item.effective_score ?? '');
            button.dataset.participant = item.nama_snapshot || item.nomor_peserta_snapshot || '';
            action.append(button);
            tr.append(action);
            body.append(tr);
        }
    };

    const loadQuestions = async () => {
        if (!state.selectedJadwal) {
            state.questions = []; renderQuestions(); return;
        }
        feedback('scoringFeedback', 'Memuat data penilaian...');
        const params = new URLSearchParams({jadwal_id: String(state.selectedJadwal)});
        if ($('scoringType').value) params.set('question_type', $('scoringType').value);
        try {
            const data = await api(scoringApi + '/questions?' + params.toString());
            state.questions = Array.isArray(data) ? data : [];
            renderQuestions();
            const schedule = state.jadwal.find(item => Number(item.id) === state.selectedJadwal);
            $('scoringContext').textContent = schedule
                ? (schedule.kegiatan_nama + ' · ' + (schedule.nama_mapel || schedule.nama_bank || ('Jadwal #' + schedule.id)))
                : ('Jadwal #' + state.selectedJadwal);
            feedback('scoringFeedback', '');
        } catch (error) {
            state.questions = []; renderQuestions();
            feedback('scoringFeedback', error.message, true);
        }
    };

    const loadResponses = async questionId => {
        const item = state.questions.find(row => Number(row.question_id) === Number(questionId));
        if (!item) return;
        state.selectedQuestion = item;
        $('scoringQuestionTitle').textContent = (labels[item.question_type] || item.question_type || 'Soal') + ' · #' + item.question_id;
        $('scoringQuestionText').textContent = plain(item.question_html).slice(0, 300);
        $('scoringResponsesPanel').hidden = false;
        $('scoringResponseRows').replaceChildren();
        feedback('scoringFeedback', 'Memuat jawaban peserta...');

        try {
            const params = new URLSearchParams({jadwal_id: String(state.selectedJadwal)});
            state.responses = await api(scoringApi + '/questions/' + item.question_id + '/responses?' + params.toString());
            if (!Array.isArray(state.responses)) state.responses = [];
            renderResponses();
            feedback('scoringFeedback', '');
            $('scoringResponsesPanel').scrollIntoView({behavior: 'smooth', block: 'start'});
        } catch (error) {
            state.responses = []; renderResponses();
            feedback('scoringFeedback', error.message, true);
        }
    };

    const openManual = responseId => {
        const row = state.responses.find(item => Number(item.response_id) === Number(responseId));
        if (!row) return;
        $('manualResponseId').value = String(responseId);
        $('manualScoreValue').max = String(row.max_point ?? '');
        $('manualScoreValue').value = row.manual_score ?? row.effective_score ?? '';
        $('manualScoreReason').value = '';
        $('manualScoreLimit').textContent = 'Rentang nilai: 0 sampai ' + numberText(row.max_point) + '.';
        $('manualScoreTitle').textContent = 'Nilai Manual · ' + (row.nama_snapshot || row.nomor_peserta_snapshot || '');
        feedback('manualScoreFeedback', '');
        bootstrap.Modal.getOrCreateInstance($('manualScoreModal')).show();
    };

    const openOperation = type => {
        state.operation = type;
        const finalize = type === 'finalize';
        $('scoringOperationTitle').textContent = finalize ? 'Finalisasi Hasil' : 'Hitung Ulang Nilai';
        $('scoringOperationMessage').textContent = finalize
            ? 'Finalisasi akan membuat snapshot hasil FINAL dan mengunci koreksi normal pada Jadwal ini. Pastikan seluruh penilaian manual sudah selesai.'
            : 'Hitung ulang akan membuat snapshot nilai baru untuk seluruh Attempt yang sudah selesai berdasarkan kunci, bobot, VOID, dan koreksi saat ini.';
        $('scoringOperationConfirm').className = finalize ? 'btn btn-cbt-primary' : 'btn btn-outline-primary';
        $('scoringOperationConfirm').textContent = finalize ? 'Finalisasi' : 'Hitung Ulang';
        feedback('scoringOperationFeedback', '');
        bootstrap.Modal.getOrCreateInstance($('scoringOperationModal')).show();
    };

    const runOperation = async () => {
        if (!state.selectedJadwal || !state.operation) return;
        const button = $('scoringOperationConfirm');
        button.disabled = true;
        const type = state.operation;
        try {
            const url = type === 'finalize'
                ? resultsApi + '/jadwal/' + state.selectedJadwal + '/finalize'
                : scoringApi + '/jadwal/' + state.selectedJadwal + '/rescore';
            const data = await api(url, 'POST', {}, operationKey(type));
            bootstrap.Modal.getOrCreateInstance($('scoringOperationModal')).hide();
            feedback('scoringFeedback', type === 'finalize'
                ? 'Hasil Jadwal berhasil difinalkan.'
                : 'Hitung ulang selesai untuk ' + Number(data.rescored_count || 0) + ' Attempt.');
            if (state.selectedQuestion) await loadResponses(state.selectedQuestion.question_id);
            await loadQuestions();
        } catch (error) {
            feedback('scoringOperationFeedback', error.message, true);
        } finally {
            button.disabled = false;
        }
    };

    $('scoringKegiatan').addEventListener('change', () => {
        state.selectedJadwal = 0;
        populateJadwal();
        state.questions = [];
        state.responses = [];
        state.selectedQuestion = null;
        $('scoringResponsesPanel').hidden = true;
        renderQuestions();
    });

    $('scoringJadwal').addEventListener('change', () => {
        state.selectedJadwal = Number($('scoringJadwal').value || 0);
        updateActions();
        $('scoringResponsesPanel').hidden = true;
        state.selectedQuestion = null;
        loadQuestions();
    });

    $('scoringType').addEventListener('change', loadQuestions);
    $('scoringReload').addEventListener('click', loadQuestions);
    $('scoringRescore').addEventListener('click', () => openOperation('rescore'));
    $('scoringFinalize').addEventListener('click', () => openOperation('finalize'));
    $('scoringOperationConfirm').addEventListener('click', runOperation);
    $('scoringCloseResponses').addEventListener('click', () => {
        $('scoringResponsesPanel').hidden = true;
        state.selectedQuestion = null;
    });

    $('scoringQuestionRows').addEventListener('click', event => {
        const button = event.target.closest('[data-question-id]');
        if (button) loadResponses(Number(button.dataset.questionId));
    });

    $('scoringResponseRows').addEventListener('click', event => {
        const button = event.target.closest('[data-response-id]');
        if (button && !button.disabled) openManual(Number(button.dataset.responseId));
    });

    $('manualScoreForm').addEventListener('submit', async event => {
        event.preventDefault();
        const responseId = Number($('manualResponseId').value || 0);
        const score = $('manualScoreValue').value;
        const reason = $('manualScoreReason').value.trim();
        if (!responseId || score === '' || !reason) {
            feedback('manualScoreFeedback', 'Nilai dan alasan koreksi wajib diisi.', true);
            return;
        }

        const submit = event.submitter || $('manualScoreForm').querySelector('[type="submit"]');
        submit.disabled = true;
        try {
            await api(scoringApi + '/responses/' + responseId + '/manual-score', 'POST', {score, reason});
            bootstrap.Modal.getOrCreateInstance($('manualScoreModal')).hide();
            feedback('scoringFeedback', 'Nilai manual berhasil disimpan dan snapshot penilaian diperbarui.');
            if (state.selectedQuestion) await loadResponses(state.selectedQuestion.question_id);
            await loadQuestions();
        } catch (error) {
            feedback('manualScoreFeedback', error.message, true);
        } finally {
            submit.disabled = false;
        }
    });

    api(optionsApi).then(data => {
        state.kegiatan = Array.isArray(data.kegiatan) ? data.kegiatan : [];
        state.jadwal = Array.isArray(data.jadwal) ? data.jadwal : [];
        populateKegiatan();
        populateJadwal();
    }).catch(error => feedback('scoringFeedback', error.message, true));
})();
