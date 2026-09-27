(() => {
    'use strict';
    const app = document.getElementById('questionApp');
    if (!app) return;
    const $ = (id) => document.getElementById(id);
    const base = app.dataset.api;
    const state = {page: 1, pages: 1, seq: 0, editable: false, configured: false,
        editing: null, revision: null, options: [], correctIndex: -1};
    const feedback = (id, message, error = false) => {
        const node = $(id); node.textContent = message;
        node.className = 'cbt-inline-feedback' + (message ? (error ? ' is-error' : ' is-info') : '');
    };
    const api = async (url, method = 'GET', payload = null) => {
        const headers = {'Accept': 'application/json'};
        if (method !== 'GET') {headers['Content-Type'] = 'application/json'; headers['X-CSRF-TOKEN'] = document.querySelector('meta[name="csrf-token"]').content;}
        const response = await fetch(url, {method, credentials: 'same-origin', headers,
            ...(payload === null ? {} : {body: JSON.stringify(payload)})});
        const result = await response.json().catch(() => null);
        if (!response.ok || result?.ok !== true) throw new Error(result?.error?.message ?? 'Permintaan gagal.');
        return result.data;
    };
    const td = (value) => {const cell = document.createElement('td'); cell.textContent = value == null ? '—' : String(value); return cell;};
    const preview = () => {
        const data = {question_text: $('questionText').value, options: state.options.map((content, index) => ({
            option_key: String.fromCharCode(65 + index), content_text: content,
        })), correct_key: state.correctIndex < 0 ? null : String.fromCharCode(65 + state.correctIndex)};
        $('questionPreview').replaceChildren(window.CbtQuestionRenderer.renderPg(data, {showAnswer: true}));
    };
    const renderOptions = () => {
        const target = $('questionOptions'); target.replaceChildren();
        state.options.forEach((value, index) => {
            const row = document.createElement('div'); row.className = 'd-flex align-items-start gap-2';
            const radio = document.createElement('input'); radio.type = 'radio'; radio.name = 'correctPg';
            radio.className = 'form-check-input mt-2'; radio.checked = state.correctIndex === index;
            radio.setAttribute('aria-label', 'Kunci jawaban ' + String.fromCharCode(65 + index));
            radio.disabled = !state.editable;
            radio.addEventListener('change', () => {state.correctIndex = index; preview();});
            const label = document.createElement('label'); label.className = 'mt-2 fw-semibold';
            label.textContent = String.fromCharCode(65 + index) + '.';
            const input = document.createElement('textarea'); input.className = 'form-control';
            input.rows = 2; input.maxLength = 3000; input.required = true; input.value = value;
            input.disabled = !state.editable;
            input.setAttribute('aria-label', 'Teks opsi ' + String.fromCharCode(65 + index));
            input.addEventListener('input', () => {state.options[index] = input.value; preview();});
            const remove = document.createElement('button'); remove.type = 'button';
            remove.className = 'btn btn-outline-danger btn-sm mt-1'; remove.textContent = 'Hapus';
            remove.disabled = !state.editable || state.options.length <= 2;
            remove.addEventListener('click', () => {
                state.options.splice(index, 1);
                if (state.correctIndex === index) state.correctIndex = -1;
                else if (state.correctIndex > index) state.correctIndex--;
                renderOptions();
            });
            row.append(radio, label, input, remove); target.append(row);
        });
        $('questionAddOption').disabled = !state.editable || state.options.length >= 6;
        preview();
    };
    const openEditor = (item = null) => {
        state.editing = item ? Number(item.id) : null;
        state.revision = item ? Number(item.current_revision_no) : null;
        state.options = item ? item.options.map((option) => option.content_text) : ['', '', '', ''];
        state.correctIndex = item ? item.options.findIndex((option) => Number(option.is_correct) === 1) : -1;
        $('questionText').value = item?.question_text ?? '';
        $('questionPoint').value = item?.max_point ?? '1';
        $('questionText').disabled = !state.editable; $('questionPoint').disabled = !state.editable;
        $('questionSave').hidden = !state.editable;
        $('questionEditorTitle').textContent = item ? 'Soal PG · Revisi ' + state.revision : 'Tambah Soal PG';
        feedback('questionFormFeedback', ''); renderOptions();
        $('questionEditor').hidden = false; $('questionEditor').scrollIntoView({behavior: 'smooth'});
    };
    const load = async () => {
        const seq = ++state.seq;
        feedback('questionFeedback', 'Memuat soal...');
        try {
            const data = await api(base + '?' + new URLSearchParams({page: state.page, per_page: $('questionPageSize').value}));
            if (seq !== state.seq) return;
            state.editable = data.bank.status === 'DRAFT' && data.bank.kegiatan_status === 'DRAFT';
            state.configured = Boolean(data.pg_configured);
            $('questionContext').textContent = data.bank.nama_bank + ' · ' + data.bank.kegiatan_nama + ' · ' +
                data.bank.mapel_nama + ' · Tingkat ' + data.bank.tingkat + ' · ' + data.bank.status;
            $('questionAdd').disabled = !state.editable || !state.configured;
            const tbody = $('questionRows'); tbody.replaceChildren();
            for (const item of data.items) {
                const row = document.createElement('tr');
                const stem = td(item.question_text.length > 160 ? item.question_text.slice(0, 160) + '…' : item.question_text);
                const actions = document.createElement('td'); actions.className = 'text-nowrap';
                const view = document.createElement('button'); view.type = 'button';
                view.className = 'btn btn-outline-primary btn-sm me-1';
                view.textContent = state.editable ? 'Edit / Pratinjau' : 'Lihat';
                view.addEventListener('click', async () => {
                    try { const data = await api(base + '/' + item.id); openEditor(data.item); }
                    catch (error) {feedback('questionFeedback', error.message, true);}
                });
                actions.append(view);
                if (state.editable) {
                    const remove = document.createElement('button'); remove.type = 'button';
                    remove.className = 'btn btn-outline-danger btn-sm'; remove.textContent = 'Hapus';
                    remove.addEventListener('click', async () => {
                        if (!window.confirm('Hapus Soal PG ini beserta semua revisinya?')) return;
                        remove.disabled = true;
                        try {await api(base + '/' + item.id, 'DELETE'); $('questionEditor').hidden = true;
                            await load(); feedback('questionFeedback', 'Soal dihapus.');}
                        catch (error) {remove.disabled = false; feedback('questionFeedback', error.message, true);}
                    }); actions.append(remove);
                }
                row.append(td(item.sort_order), stem, td(item.max_point), td(item.current_revision_no), actions);
                tbody.append(row);
            }
            if (!data.items.length) {const row = document.createElement('tr'), cell = td('Belum ada Soal PG.');
                cell.colSpan = 5; cell.className = 'text-center text-secondary py-4'; row.append(cell); tbody.append(row);}
            state.page = Number(data.pagination.page); state.pages = Number(data.pagination.pages);
            $('questionCount').textContent = data.pagination.total + ' Soal PG';
            $('questionPageInfo').textContent = state.page + ' / ' + state.pages;
            $('questionPrevious').disabled = state.page <= 1; $('questionNext').disabled = state.page >= state.pages;
            feedback('questionFeedback', state.configured ? '' : 'Aktifkan tipe Pilihan Ganda pada Komposisi Bank terlebih dahulu.', !state.configured);
        } catch (error) {if (seq === state.seq) feedback('questionFeedback', error.message, true);}
    };
    $('questionAdd').addEventListener('click', () => openEditor());
    $('questionAddOption').addEventListener('click', () => {if (state.options.length < 6) {state.options.push(''); renderOptions();}});
    $('questionText').addEventListener('input', preview);
    $('questionCancel').addEventListener('click', () => {$('questionEditor').hidden = true;});
    $('questionForm').addEventListener('submit', async (event) => {
        event.preventDefault();
        if (state.correctIndex < 0) {feedback('questionFormFeedback', 'Pilih satu kunci jawaban.', true); return;}
        const payload = {question_text: $('questionText').value, max_point: $('questionPoint').value,
            correct_key: String.fromCharCode(65 + state.correctIndex),
            options: state.options.map((value) => ({text: value}))};
        if (state.editing !== null) payload.expected_revision = state.revision;
        const button = $('questionSave'); button.disabled = true;
        try {
            const data = await api(state.editing === null ? base : base + '/' + state.editing,
                state.editing === null ? 'POST' : 'PUT', payload);
            state.page = 1; $('questionEditor').hidden = true; await load();
            feedback('questionFeedback', 'Soal tersimpan pada revisi ' + data.item.current_revision_no + '.');
        } catch (error) {feedback('questionFormFeedback', error.message, true);}
        finally {button.disabled = false;}
    });
    $('questionPageSize').addEventListener('change', () => {state.page = 1; load();});
    $('questionPrevious').addEventListener('click', () => {if (state.page > 1) {state.page--; load();}});
    $('questionNext').addEventListener('click', () => {if (state.page < state.pages) {state.page++; load();}});
    load();
})();
