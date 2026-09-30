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
    let pendingTextSave = null;
    let writeChain = Promise.resolve();

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

    const persistSnapshot = snapshot => {
        const run = writeChain
            .catch(() => undefined)
            .then(() => store()?.save(
                snapshot.itemId,
                snapshot.payload,
                snapshot.isFlagged,
                {
                    allowLocked: true,
                    answeredAtClient: snapshot.answeredAtClient,
                    clientElapsedMs: snapshot.clientElapsedMs
                }
            ));
        writeChain = run;
        return run;
    };

    const flushPending = async () => {
        clearTimeout(textTimer);
        textTimer = null;
        const pending = pendingTextSave;
        pendingTextSave = null;
        if (pending) persistSnapshot(pending);
        await writeChain;
        return true;
    };

    const scheduleSave = (payload, immediate = true) => {
        currentPayload = payload;
        clearTimeout(textTimer);
        textTimer = null;
        if (!currentItem) return;

        const snapshot = {
            itemId: Number(currentItem.item_id),
            payload,
            isFlagged: Boolean(flagged?.checked),
            answeredAtClient: new Date(runtime.serverNowMs()).toISOString(),
            clientElapsedMs: runtime.elapsedMs()
        };

        if (immediate) {
            pendingTextSave = null;
            persistSnapshot(snapshot);
        } else {
            pendingTextSave = snapshot;
            textTimer = setTimeout(() => {
                const pending = pendingTextSave;
                pendingTextSave = null;
                textTimer = null;
                if (pending) persistSnapshot(pending);
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

    const plainText = value => String(value ?? '')
        .replace(/<br\s*\/?>/gi, ' ')
        .replace(/(?:\*{1,2})?(Audio|Video)\s*:(?:\*{1,2})?\s*https:\/\/(?:www\.)?drive\.google\.com\/[^\s<]+/gi, '$1')
        .replace(/\[\[media:[^\]]+\]\]/gi, 'Gambar')
        .replace(/\*\*([^*]+)\*\*/g, '$1')
        .replace(/\*([^*]+)\*/g, '$1')
        .replace(/\$\$?([^$]+)\$\$?/g, '$1')
        .replace(/&apos;|&#39;/gi, "'")
        .replace(/&quot;/gi, '"')
        .replace(/&lt;/gi, '<')
        .replace(/&gt;/gi, '>')
        .replace(/&amp;/gi, '&')
        .replace(/&nbsp;/gi, ' ')
        .replace(/&#(\d{1,7});/g, (_, code) => {
            const point = Number(code);
            return Number.isInteger(point) && point >= 0 && point <= 0x10FFFF
                ? String.fromCodePoint(point)
                : _;
        })
        .replace(/&#x([0-9a-f]{1,6});/gi, (_, code) => {
            const point = Number.parseInt(code, 16);
            return Number.isInteger(point) && point >= 0 && point <= 0x10FFFF
                ? String.fromCodePoint(point)
                : _;
        })
        .replace(/\s+/g, ' ')
        .trim();

    const renderMatching = (item, answer) => {
        const wrap = document.createElement('div');
        wrap.className = 'exam-matching-v2';

        const current = answer?.pairs && typeof answer.pairs === 'object' ? {...answer.pairs} : {};
        const leftItems = item.matching_left || [];
        const rightChoices = item.matching_right || [];
        const rightLabels = new Map(
            rightChoices.map((right, index) => [String(right.key), String.fromCharCode(65 + index)])
        );

        const hint = document.createElement('div');
        hint.className = 'exam-question-hint';
        hint.innerHTML = '<i class="bi bi-diagram-3" aria-hidden="true"></i><span>Pasangkan setiap item bernomor dengan item berhuruf yang paling sesuai.</span>';
        wrap.append(hint);

        const referenceScroll = document.createElement('div');
        referenceScroll.className = 'exam-matching-reference-scroll';

        const reference = document.createElement('div');
        reference.className = 'exam-matching-reference';

        const makeReferenceTable = (title, rows, side) => {
            const table = document.createElement('section');
            table.className = 'exam-match-ref-table ' + side;

            const header = document.createElement('div');
            header.className = 'exam-match-ref-header';
            header.textContent = title;
            table.append(header);

            rows.forEach((row, index) => {
                const line = document.createElement('div');
                line.className = 'exam-match-ref-row';

                const badge = document.createElement('span');
                badge.className = 'exam-match-badge ' + side;
                badge.textContent = side === 'left'
                    ? String(index + 1)
                    : String.fromCharCode(65 + index);

                const content = document.createElement('div');
                content.className = 'exam-match-ref-content';
                content.append(rich(row.content_text || ''));

                line.append(badge, content);
                table.append(line);
            });
            return table;
        };

        reference.append(
            makeReferenceTable('Pernyataan 1', leftItems, 'left'),
            makeReferenceTable('Pernyataan 2', rightChoices, 'right')
        );
        referenceScroll.append(reference);
        wrap.append(referenceScroll);

        const instruction = document.createElement('div');
        instruction.className = 'exam-match-answer-title';
        instruction.textContent = 'Pasangkan item di atas dengan benar';
        wrap.append(instruction);

        const answerTable = document.createElement('div');
        answerTable.className = 'exam-match-answer-table';

        leftItems.forEach((left, index) => {
            const row = document.createElement('div');
            row.className = 'exam-match-answer-row';

            const number = document.createElement('span');
            number.className = 'exam-match-badge left';
            number.textContent = String(index + 1);

            const connector = document.createElement('span');
            connector.className = 'exam-match-connector';
            connector.textContent = 'Dengan';

            const select = document.createElement('select');
            select.className = 'form-select exam-match-select';
            select.dataset.leftKey = String(left.key);
            select.setAttribute('aria-label', 'Pasangan untuk item ' + (index + 1));
            select.append(new Option('Pilih', ''));

            rightChoices.forEach((right, rightIndex) => {
                const letter = String.fromCharCode(65 + rightIndex);
                const text = plainText(right.content_text || '');
                select.append(new Option(letter + (text ? ' — ' + text : ''), right.key));
            });

            select.value = String(current[left.key] || '');

            const syncUsed = () => {
                const used = new Set(
                    Object.values(current).filter(Boolean).map(value => String(value))
                );
                for (const other of answerTable.querySelectorAll('select[data-left-key]')) {
                    const own = String(other.value || '');
                    for (const option of other.options) {
                        if (!option.value) continue;
                        option.disabled = used.has(String(option.value)) && String(option.value) !== own;
                    }
                }
            };

            select.addEventListener('change', () => {
                current[left.key] = select.value || null;
                scheduleSave({pairs: {...current}});
                syncUsed();
                updateProgress();
            });

            row.append(number, connector, select);
            answerTable.append(row);
        });

        const progress = document.createElement('div');
        progress.className = 'exam-match-progress';

        const updateProgress = () => {
            const filled = leftItems.filter(left => Boolean(current[left.key])).length;
            progress.textContent = filled + ' dari ' + leftItems.length + ' pasangan sudah diisi';
        };

        wrap.append(answerTable, progress);

        // Initial disabled-state and progress.
        const used = new Set(Object.values(current).filter(Boolean).map(value => String(value)));
        for (const other of answerTable.querySelectorAll('select[data-left-key]')) {
            const own = String(other.value || '');
            for (const option of other.options) {
                if (!option.value) continue;
                option.disabled = used.has(String(option.value)) && String(option.value) !== own;
            }
        }
        updateProgress();

        return wrap;
    };

    const renderTextAnswer = (item, answer) => {
        const wrap = document.createElement('div');
        wrap.className = 'exam-text-answer';

        if (item.question_type === 'URAIAN') {
            const hint = document.createElement('div');
            hint.className = 'exam-question-hint';
            hint.innerHTML = '<i class="bi bi-pencil-square" aria-hidden="true"></i><span>Tuliskan jawaban secara jelas pada kotak jawaban.</span>';
            wrap.append(hint);
            const area = document.createElement('textarea');
            area.className = 'form-control exam-answer-textarea';
            area.placeholder = 'Tulis jawaban Anda di sini...';
            area.value = String(answer?.text ?? '');
            area.addEventListener('input', () => scheduleSave({text: area.value}, false));
            wrap.append(area);
            return wrap;
        }

        const hint = document.createElement('div');
        hint.className = 'exam-question-hint';
        hint.innerHTML = item.short_answer_mode === 'NUMERIC'
            ? '<i class="bi bi-123" aria-hidden="true"></i><span>Masukkan jawaban berupa angka. Desimal dapat ditulis menggunakan koma.</span>'
            : '<i class="bi bi-input-cursor-text" aria-hidden="true"></i><span>Masukkan jawaban singkat pada kolom berikut.</span>';
        wrap.append(hint);

        const input = document.createElement('input');
        input.className = 'form-control';
        input.type = item.short_answer_mode === 'NUMERIC' ? 'text' : 'text';
        input.inputMode = item.short_answer_mode === 'NUMERIC' ? 'decimal' : 'text';
        input.autocomplete = 'off';
        input.placeholder = item.short_answer_mode === 'NUMERIC' ? 'Masukkan angka' : 'Masukkan jawaban singkat';
        input.value = String(answer?.value ?? '');
        input.addEventListener('input', () => scheduleSave({value: input.value}, false));
        wrap.append(input);
        return wrap;
    };

    const renderIndex = async index => {
        const items = runtime.bootstrap?.package?.items || [];
        if (!items.length || index < 0 || index >= items.length || !card) return;
        await flushPending();
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
        scheduleSave(currentPayload, true);
    });

    document.addEventListener('visibilitychange', () => {
        if (document.visibilityState === 'hidden') void flushPending();
    });
    window.addEventListener('pagehide', () => {
        void flushPending();
    });

    document.addEventListener('cbt:bootstrap-ready', () => renderIndex(runtime.currentIndex || 0));
    window.CbtExamRenderer = {renderIndex, isAnswered, flushPending};
})();
