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

        if (multiple) {
            const hint = document.createElement('div');
            hint.className = 'exam-question-hint';
            hint.innerHTML = '<i class="bi bi-check2-square" aria-hidden="true"></i><span>Pilih satu atau lebih jawaban yang benar.</span>';
            wrap.append(hint);
        }

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
        wrap.className = 'exam-matching';

        const current = answer?.pairs && typeof answer.pairs === 'object' ? {...answer.pairs} : {};
        const leftItems = item.matching_left || [];
        const rightChoices = item.matching_right || [];
        const labels = new Map();

        rightChoices.forEach((right, index) => {
            labels.set(right.key, String.fromCharCode(65 + index));
        });

        const hint = document.createElement('div');
        hint.className = 'exam-question-hint';
        hint.innerHTML = '<i class="bi bi-diagram-3" aria-hidden="true"></i><span>Pasangkan setiap pernyataan di sebelah kiri dengan satu jawaban di sebelah kanan.</span>';
        wrap.append(hint);

        const grid = document.createElement('div');
        grid.className = 'exam-matching-grid';

        const leftPanel = document.createElement('section');
        leftPanel.className = 'exam-matching-panel';
        const leftTitle = document.createElement('div');
        leftTitle.className = 'exam-matching-panel-title';
        leftTitle.textContent = 'Pernyataan';
        leftPanel.append(leftTitle);

        const rightPanel = document.createElement('section');
        rightPanel.className = 'exam-matching-panel';
        const rightTitle = document.createElement('div');
        rightTitle.className = 'exam-matching-panel-title';
        rightTitle.textContent = 'Pilihan Jawaban';
        rightPanel.append(rightTitle);

        rightChoices.forEach((right) => {
            const answerRow = document.createElement('div');
            answerRow.className = 'exam-matching-answer';
            answerRow.dataset.rightKey = String(right.key);

            const badge = document.createElement('span');
            badge.className = 'exam-matching-answer-key';
            badge.textContent = labels.get(right.key) || String(right.key);

            const content = document.createElement('div');
            content.className = 'exam-matching-answer-text';
            content.append(rich(right.content_text || ''));

            answerRow.append(badge, content);
            rightPanel.append(answerRow);
        });

        leftItems.forEach((left, index) => {
            const row = document.createElement('div');
            row.className = 'exam-matching-question';

            const number = document.createElement('span');
            number.className = 'exam-matching-question-number';
            number.textContent = String(index + 1);

            const content = document.createElement('div');
            content.className = 'exam-matching-question-text';
            content.append(rich(left.content_text || ''));

            const select = document.createElement('select');
            select.className = 'form-select exam-matching-select';
            select.dataset.leftKey = String(left.key);
            select.setAttribute('aria-label', 'Pilih pasangan untuk pernyataan ' + (index + 1));
            select.append(new Option('Pilih jawaban', ''));

            for (const right of rightChoices) {
                const label = labels.get(right.key) || String(right.key);
                const optionText = label + ' — ' + String(right.content_text || '').replace(/<br\s*\/?>/gi, ' ').replace(/\s+/g, ' ').trim();
                select.append(new Option(optionText, right.key));
            }

            select.value = String(current[left.key] || '');
            select.addEventListener('change', () => {
                const chosen = select.value || null;

                if (chosen) {
                    for (const [otherLeft, otherRight] of Object.entries(current)) {
                        if (otherLeft !== String(left.key) && String(otherRight || '') === chosen) {
                            current[otherLeft] = null;
                        }
                    }

                    for (const otherSelect of wrap.querySelectorAll('select[data-left-key]')) {
                        if (otherSelect !== select && otherSelect.value === chosen) {
                            otherSelect.value = '';
                        }
                    }
                }

                current[left.key] = chosen;
                scheduleSave({pairs: {...current}});

                for (const answerNode of rightPanel.querySelectorAll('.exam-matching-answer')) {
                    answerNode.classList.toggle(
                        'is-used',
                        Object.values(current).some(value => String(value || '') === answerNode.dataset.rightKey)
                    );
                }
            });

            row.append(number, content, select);
            leftPanel.append(row);
        });

        for (const answerNode of rightPanel.querySelectorAll('.exam-matching-answer')) {
            answerNode.classList.toggle(
                'is-used',
                Object.values(current).some(value => String(value || '') === answerNode.dataset.rightKey)
            );
        }

        grid.append(leftPanel, rightPanel);
        wrap.append(grid);

        const note = document.createElement('div');
        note.className = 'exam-matching-note';
        note.textContent = 'Setiap pilihan jawaban hanya dapat digunakan satu kali.';
        wrap.append(note);

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
