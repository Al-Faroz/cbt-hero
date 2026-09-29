(() => {
    'use strict';

    const runtime = window.CbtExamRuntime;
    const db = window.CbtExamDb;
    const store = () => window.CbtExamAnswerStore;
    if (!runtime || !db) return;

    const card = document.getElementById('examQuestionCard');
    const flagged = document.getElementById('examFlagged');
    let currentPayload = null;
    let currentItem = null;
    let textTimer = null;

    const rich = (value, className = '') =>
        window.CbtQuestionRenderer?.renderContent
            ? window.CbtQuestionRenderer.renderContent(value || '', runtime.mediaMap, className)
            : document.createTextNode(value || '');

    const isAnswered = (payload, type) => {
        if (payload == null) return false;
        if (['PG', 'PG_BERTINGKAT'].includes(type)) return Boolean(payload.selected);
        if (type === 'PG_KOMPLEKS') return Array.isArray(payload.selected) && payload.selected.length > 0;
        if (type === 'MATCHING') return payload.pairs && Object.values(payload.pairs).some(value => value);
        if (type === 'ISIAN_SINGKAT') return String(payload.value ?? '').trim() !== '';
        if (type === 'URAIAN') return String(payload.text ?? '').trim() !== '';
        return false;
    };

    const scheduleSave = (payload, immediate = true) => {
        currentPayload = payload;
        clearTimeout(textTimer);
        if (immediate) {
            store()?.save(currentItem.item_id, payload, flagged.checked);
        } else {
            textTimer = setTimeout(() => {
                store()?.save(currentItem.item_id, currentPayload, flagged.checked);
            }, 400);
        }
    };

    const optionContent = option => {
        const box = document.createElement('div');
        box.className = 'exam-option-content';
        box.append(rich(option.content_text || ''));
        return box;
    };

    const renderChoice = (item, answer, multiple = false) => {
        const selected = multiple
            ? new Set(Array.isArray(answer?.selected) ? answer.selected : [])
            : String(answer?.selected || '');
        const wrap = document.createElement('div');
        wrap.className = 'exam-options';

        for (const option of item.options || []) {
            const label = document.createElement('label');
            label.className = 'exam-option';
            const input = document.createElement('input');
            input.className = 'form-check-input';
            input.type = multiple ? 'checkbox' : 'radio';
            input.name = 'answer_' + item.item_id;
            input.value = option.option_key;
            input.checked = multiple ? selected.has(option.option_key) : selected === option.option_key;
            input.addEventListener('change', () => {
                if (multiple) {
                    const values = [...wrap.querySelectorAll('input:checked')].map(node => node.value);
                    scheduleSave({selected: values});
                } else {
                    scheduleSave({selected: input.value});
                }
            });
            const key = document.createElement('strong');
            key.className = 'mt-1';
            key.textContent = option.option_key + '.';
            label.append(input, key, optionContent(option));
            wrap.append(label);
        }
        return wrap;
    };

    const renderMatching = (item, answer) => {
        const wrap = document.createElement('div');
        const current = answer?.pairs && typeof answer.pairs === 'object' ? {...answer.pairs} : {};
        const rightChoices = item.matching_right || [];
        const labels = new Map();

        const bank = document.createElement('div');
        bank.className = 'border rounded p-2 mb-3';
        const bankTitle = document.createElement('div');
        bankTitle.className = 'small fw-semibold mb-2';
        bankTitle.textContent = 'Pilihan pasangan';
        bank.append(bankTitle);

        rightChoices.forEach((right, index) => {
            const label = String.fromCharCode(65 + index);
            labels.set(right.key, label);
            const line = document.createElement('div');
            line.className = 'd-flex gap-2 align-items-start py-1';
            const badge = document.createElement('strong');
            badge.textContent = label + '.';
            const content = document.createElement('div');
            content.className = 'flex-grow-1';
            content.append(rich(right.content_text || ''));
            line.append(badge, content);
            bank.append(line);
        });
        wrap.append(bank);

        for (const left of item.matching_left || []) {
            const row = document.createElement('div');
            row.className = 'exam-matching-row';
            const leftBox = document.createElement('div');
            leftBox.append(rich(left.content_text || ''));

            const select = document.createElement('select');
            select.className = 'form-select';
            select.dataset.leftKey = left.key;
            select.append(new Option('Pilih pasangan', ''));
            for (const right of rightChoices) {
                select.append(new Option('Pilihan ' + (labels.get(right.key) || right.key), right.key));
            }
            select.value = String(current[left.key] || '');
            select.addEventListener('change', () => {
                const chosen = select.value || null;
                if (chosen) {
                    for (const [otherLeft, otherRight] of Object.entries(current)) {
                        if (otherLeft !== left.key && otherRight === chosen) current[otherLeft] = null;
                    }
                    for (const otherSelect of wrap.querySelectorAll('select[data-left-key]')) {
                        if (otherSelect !== select && otherSelect.value === chosen) otherSelect.value = '';
                    }
                }
                current[left.key] = chosen;
                scheduleSave({pairs: {...current}});
            });
            row.append(leftBox, select);
            wrap.append(row);
        }
        return wrap;
    };

    const renderTextAnswer = (item, answer) => {
        if (item.question_type === 'URAIAN') {
            const area = document.createElement('textarea');
            area.className = 'form-control exam-answer-textarea';
            area.placeholder = 'Tulis jawaban Anda di sini...';
            area.value = String(answer?.text ?? '');
            area.addEventListener('input', () => scheduleSave({text: area.value}, false));
            return area;
        }

        const input = document.createElement('input');
        input.className = 'form-control';
        input.type = item.short_answer_mode === 'NUMERIC' ? 'text' : 'text';
        input.inputMode = item.short_answer_mode === 'NUMERIC' ? 'decimal' : 'text';
        input.autocomplete = 'off';
        input.placeholder = item.short_answer_mode === 'NUMERIC' ? 'Masukkan angka' : 'Masukkan jawaban singkat';
        input.value = String(answer?.value ?? '');
        input.addEventListener('input', () => scheduleSave({value: input.value}, false));
        return input;
    };

    const renderIndex = async index => {
        const items = runtime.bootstrap?.package?.items || [];
        if (!items.length || index < 0 || index >= items.length || !card) return;
        clearTimeout(textTimer);
        runtime.currentIndex = index;
        currentItem = items[index];

        const answerRow = await db.answer_store.get([runtime.attemptId, currentItem.item_id]);
        const answer = answerRow?.answer_payload ?? null;
        currentPayload = answer;
        if (flagged) flagged.checked = Boolean(answerRow?.is_flagged);

        card.replaceChildren();
        const number = document.createElement('div');
        number.className = 'exam-question-number';
        number.textContent = 'Soal ' + (index + 1) + ' dari ' + items.length;
        card.append(number);

        if (currentItem.stimulus_text) {
            const stimulus = document.createElement('div');
            stimulus.className = 'exam-stimulus';
            stimulus.append(rich(currentItem.stimulus_text));
            card.append(stimulus);
        }

        const question = document.createElement('div');
        question.className = 'exam-question-text';
        question.append(rich(currentItem.question_text || ''));
        card.append(question);

        if (currentItem.voided) {
            const notice = document.createElement('div');
            notice.className = 'alert alert-warning mt-3 mb-0';
            notice.textContent = 'Soal ini dibatalkan. Jawaban yang pernah tersimpan tetap menjadi riwayat, tetapi tidak dihitung dalam nilai.';
            card.append(notice);
        } else if (['PG', 'PG_BERTINGKAT'].includes(currentItem.question_type)) {
            card.append(renderChoice(currentItem, answer, false));
        } else if (currentItem.question_type === 'PG_KOMPLEKS') {
            card.append(renderChoice(currentItem, answer, true));
        } else if (currentItem.question_type === 'MATCHING') {
            card.append(renderMatching(currentItem, answer));
        } else if (['ISIAN_SINGKAT', 'URAIAN'].includes(currentItem.question_type)) {
            card.append(renderTextAnswer(currentItem, answer));
        }

        if (flagged) flagged.disabled = Boolean(currentItem.voided);
        const local = document.getElementById('examLocalStatus');
        if (local) local.textContent = currentItem.voided
            ? 'Soal dibatalkan · tidak dihitung'
            : (answerRow
                ? (answerRow.server_revision > 0 ? 'Jawaban sudah tersinkron' : 'Tersimpan di perangkat')
                : 'Belum ada jawaban');

        runtime.emit('cbt:question-rendered', {
            index,
            item: currentItem,
            answered: isAnswered(answer, currentItem.question_type)
        });
    };

    flagged?.addEventListener('change', () => {
        if (!currentItem || currentItem.voided) return;
        store()?.save(currentItem.item_id, currentPayload, flagged.checked);
    });

    document.addEventListener('cbt:bootstrap-ready', () => renderIndex(runtime.currentIndex || 0));
    window.CbtExamRenderer = {renderIndex, isAnswered};
})();
