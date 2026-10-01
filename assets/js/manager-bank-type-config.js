(() => {
    'use strict';
    const app = document.getElementById('typeApp');
    if (!app) return;
    const $ = (id) => document.getElementById(id);
    const labels = {PG: 'Pilihan Ganda', PG_KOMPLEKS: 'PG Kompleks', MATCHING: 'Menjodohkan',
        ISIAN_SINGKAT: 'Isian Singkat', URAIAN: 'Uraian', PG_BERTINGKAT: 'PG Bertingkat'};
    const objective = new Set(['PG', 'PG_KOMPLEKS', 'MATCHING', 'PG_BERTINGKAT']);
    let version = null, editable = false;
    const feedback = (message, error = false) => {
        const node = $('typeFeedback'); node.textContent = message;
        node.className = 'cbt-inline-feedback' + (message ? (error ? ' is-error' : ' is-info') : '');
    };
    const api = async (method = 'GET', payload = null) => {
        const headers = {'Accept': 'application/json'};
        if (method !== 'GET') {headers['Content-Type'] = 'application/json'; headers['X-CSRF-TOKEN'] = document.querySelector('meta[name="csrf-token"]').content;}
        const response = await fetch(app.dataset.api, {method, credentials: 'same-origin', headers,
            ...(payload === null ? {} : {body: JSON.stringify(payload)})});
        const result = await response.json().catch(() => null);
        if (!response.ok || result?.ok !== true) throw new Error(result?.error?.message ?? 'Permintaan gagal.');
        return result.data;
    };
    const input = (type, value, className) => {
        const node = document.createElement('input'); node.type = type; node.className = className;
        if (type === 'checkbox') node.checked = Boolean(value); else node.value = value;
        return node;
    };
    const cell = (node) => {const td = document.createElement('td'); td.append(node); return td;};
    const displayDecimal = (value) => window.CbtNumber?.format(value, 3) ?? String(value ?? '0');
    const parseDecimal = (value) => window.CbtNumber?.parse(value) ?? Number(String(value ?? '').replace(',', '.'));
    const current = () => [...$('typeRows').querySelectorAll('tr[data-type]')].filter((row) => row.enabled.checked).map((row) => ({
        question_type: row.dataset.type, question_count: Number(row.count.value),
        option_count: row.choice ? Number(row.choice.value) : null,
        weight_percent: parseDecimal(row.weight.value), shuffle_questions: row.questions.checked,
        shuffle_options: row.options.checked,
        scoring_mode: ['PG_KOMPLEKS', 'MATCHING'].includes(row.dataset.type) ? row.mode.value : null,
    }));
    const updateTotals = () => {
        const items = current();
        const thousandths = items.reduce((sum, item) => sum + Math.round((Number(item.weight_percent) || 0) * 1000), 0);
        $('typeTotal').textContent = 'Total bobot: ' + (window.CbtNumber?.format(thousandths / 1000, 3) ?? (thousandths / 1000)) + '%';
        $('typeStatus').textContent = items.length ? (thousandths === 100000 ? 'Bobot lengkap. Validasi soal dilakukan saat Bank READY tersedia.' :
            thousandths > 100000 ? 'Bobot melebihi 100%.' : 'Bobot masih belum mencapai 100%.') : 'Belum ada tipe yang dipilih.';
    };
    const render = (data) => {
        version = Number(data.bank.version_no); editable = data.editable;
        $('typeContext').textContent = data.bank.nama_bank + ' · ' + data.bank.kegiatan_nama + ' · ' +
            data.bank.mapel_nama + ' · Tingkat ' + data.bank.tingkat + ' · ' + data.bank.status;
        const configs = new Map(data.items.map((item) => [item.question_type, item]));
        const body = $('typeRows'); body.replaceChildren();
        const groupHeader = (label) => {
            const row = document.createElement('tr'); row.className = 'table-light';
            const heading = document.createElement('th'); heading.colSpan = 8;
            heading.scope = 'rowgroup'; heading.textContent = label;
            row.append(heading); body.append(row);
        };
        for (const type of data.types) {
            if (type === 'PG') groupHeader('PILIHAN GANDA / KLIK');
            if (type === 'ISIAN_SINGKAT') groupHeader('ISIAN & URAIAN / KETIK');
            const config = configs.get(type); const row = document.createElement('tr'); row.dataset.type = type;
            const enabled = input('checkbox', Boolean(config), 'form-check-input');
            enabled.setAttribute('aria-label', 'Gunakan ' + labels[type]); row.enabled = enabled;
            const name = document.createElement('strong'); name.textContent = labels[type];
            const count = input('number', config?.question_count ?? 1, 'form-control form-control-sm');
            count.min = '1'; count.max = '1000'; count.step = '1'; count.style.minWidth = '85px'; row.count = count;
            const choiceType = ['PG', 'PG_KOMPLEKS', 'PG_BERTINGKAT', 'MATCHING'].includes(type);
            const choice = choiceType ? input('number', config?.option_count ?? 4, 'form-control form-control-sm') : null;
            if (choice) {
                choice.min = '2'; choice.max = type === 'PG' ? '6' : (type === 'MATCHING' ? '12' : '8'); choice.step = '1';
                choice.style.width = '92px'; choice.style.minWidth = '92px';
                choice.setAttribute('aria-label', (type === 'MATCHING' ? 'Jumlah pasangan ' : 'Jumlah pilihan ') + labels[type]);
            }
            row.choice = choice;
            const weight = input('text', displayDecimal(config?.weight_percent ?? 0), 'form-control form-control-sm text-end manager-weight-input');
            weight.inputMode = 'decimal'; weight.setAttribute('pattern', '[0-9.,]+'); row.weight = weight;
            const questions = input('checkbox', Number(config?.shuffle_questions) === 1, 'form-check-input'); row.questions = questions;
            const options = input('checkbox', Number(config?.shuffle_options) === 1, 'form-check-input'); row.options = options;
            questions.setAttribute('aria-label', 'Acak soal ' + labels[type]);
            options.setAttribute('aria-label', 'Acak opsi atau pasangan ' + labels[type]);
            const mode = document.createElement('select'); mode.className = 'form-select form-select-sm';
            mode.add(new Option(type === 'MATCHING' ? 'Per pasangan (parsial)' : 'Parsial', 'PARTIAL'));
            mode.add(new Option('Semua benar', 'ALL_OR_NOTHING'));
            mode.value = config?.scoring_mode || 'PARTIAL'; row.mode = mode;
            const choiceCell = document.createElement('td');
            if (choice) choiceCell.append(choice); else choiceCell.textContent = '—';
            row.append(cell(enabled), cell(name), cell(count), choiceCell, cell(weight), cell(questions), cell(options), cell(mode));
            const sync = () => {
                const on = editable && enabled.checked;
                enabled.disabled = !editable;
                count.disabled = !on; weight.disabled = !on;
                if (choice) choice.disabled = !on;
                questions.disabled = !on || !objective.has(type);
                options.disabled = !on || !objective.has(type);
                mode.disabled = !on || !['PG_KOMPLEKS', 'MATCHING'].includes(type);
                if (!objective.has(type)) {questions.checked = false; options.checked = false;}
                updateTotals();
            };
            enabled.addEventListener('change', sync);
            weight.addEventListener('input', updateTotals);
            body.append(row); sync();
        }
        $('typeSave').disabled = !editable;
        if (!editable) feedback('Bank READY; komposisi terkunci.', true);
        else feedback('');
    };
    $('typeForm').addEventListener('submit', async (event) => {
        event.preventDefault();
        const items = current();
        if (!items.length && !window.confirm('Simpan Bank tanpa tipe soal? Konfigurasi yang ada akan dihapus.')) return;
        const button = $('typeSave'); button.disabled = true;
        try {
            const data = await api('PUT', {expected_version: version, items});
            render(data); feedback('Komposisi tersimpan. Versi Bank ' + data.bank.version_no + '.');
        } catch (error) {feedback(error.message, true);}
        finally {button.disabled = !editable;}
    });
    api().then(render).catch((error) => feedback(error.message, true));
})();
