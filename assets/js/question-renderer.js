// Safe shared renderer: text, simple emphasis, KaTeX formulas, and validated media references.
(() => {
    'use strict';
    const appendInline = (element, source, media) => {
        const tokens = /(\[\[media:([1-9][0-9]*)\]\]|\$([^$\n]{1,500})\$|\*\*([^*\n]+)\*\*|(?<!\*)\*([^*\n]+)\*(?!\*))/g;
        let cursor = 0, match;
        while ((match = tokens.exec(source)) !== null) {
            element.append(document.createTextNode(source.slice(cursor, match.index)));
            if (match[2]) {
                const asset = media[match[2]];
                if (!asset) element.append(document.createTextNode(match[0]));
                else if (asset.kind === 'IMAGE') {
                    const image = document.createElement('img'); image.src = asset.url;
                    image.alt = 'Gambar soal'; image.loading = 'eager'; image.style.maxWidth = '100%';
                    image.className = 'd-block my-2'; element.append(image);
                } else if (asset.kind === 'AUDIO') {
                    const player = document.createElement('audio'); player.controls = true; player.src = asset.url;
                    player.className = 'd-block my-2'; element.append(player);
                } else if (asset.kind === 'VIDEO') {
                    const link = document.createElement('a'); link.href = asset.url; link.target = '_blank'; link.rel = 'noopener noreferrer';
                    link.textContent = 'Buka video'; element.append(link);
                }
            } else if (match[3]) {
                const math = document.createElement('span');
                if (window.katex) {
                    try {window.katex.render(match[3], math, {throwOnError:true, trust:false, strict:'warn'});}
                    catch (_) {math.textContent = match[0];}
                } else math.textContent = match[0];
                element.append(math);
            } else if (match[4]) {const strong = document.createElement('strong'); strong.textContent = match[4]; element.append(strong);}
            else {const italic = document.createElement('em'); italic.textContent = match[5]; element.append(italic);}
            cursor = tokens.lastIndex;
        }
        element.append(document.createTextNode(source.slice(cursor)));
    };
    const node = (tag, value, className = '', media = {}) => {
        const element = document.createElement(tag);
        element.className = className; element.dir = 'auto'; element.style.whiteSpace = 'pre-wrap';
        const lines = String(value ?? '').split('\n');
        for (let i = 0; i < lines.length;) {
            if (/^\s*\|.*\|\s*$/.test(lines[i])) {
                const table = document.createElement('table'); table.className = 'table table-bordered table-sm w-auto my-2';
                let rowNumber = 0;
                while (i < lines.length && /^\s*\|.*\|\s*$/.test(lines[i])) {
                    const pieces = lines[i].trim().replace(/^\|/, '').replace(/\|$/, '').split('|');
                    if (!pieces.every(piece => /^\s*:?-{3,}:?\s*$/.test(piece))) {
                        const row = document.createElement('tr');
                        for (const piece of pieces) {
                            const cell = document.createElement(rowNumber === 0 ? 'th' : 'td');
                            appendInline(cell, piece.trim(), media); row.append(cell);
                        }
                        table.append(row); rowNumber++;
                    }
                    i++;
                }
                element.append(table);
            } else {
                appendInline(element, lines[i], media); i++;
                if (i < lines.length) element.append(document.createTextNode('\n'));
            }
        }
        return element;
    };
    const render = (question, {showAnswer = false} = {}) => {
        const article = document.createElement('article'), media = question.media || window.CbtMediaPreview || {};
        if (question.stimulus_text) article.append(node('div', question.stimulus_text, 'border-start ps-3 mb-3', media));
        article.append(node('div', question.question_text, 'fw-semibold mb-3', media));
        const type = question.question_type || 'PG';
        if (['PG', 'PG_KOMPLEKS', 'PG_BERTINGKAT'].includes(type)) {
            const list = document.createElement('div'); list.className = 'd-grid gap-2';
            for (const option of question.options || []) {
                const line = document.createElement('div');
                line.className = 'border rounded p-2' + (showAnswer && Number(option.is_correct) === 1 ? ' border-success' : '');
                line.append(node('strong', (option.option_key || '') + '. '), node('span', option.content_text || '', '', media));
                if (showAnswer && (Number(option.is_correct) === 1 || (type === 'PG' && option.option_key === question.correct_key)))
                    line.append(node('span', 'Kunci', 'badge text-bg-success ms-2'));
                if (showAnswer && type === 'PG_BERTINGKAT')
                    line.append(node('span', 'Poin ' + (option.point_value ?? '0'), 'badge text-bg-info ms-2'));
                list.append(line);
            }
            article.append(list);
        } else if (type === 'MATCHING') {
            const list = document.createElement('div'); list.className = 'd-grid gap-2';
            for (const pair of question.pairs || []) {
                const line = document.createElement('div'); line.className = 'border rounded p-2';
                line.append(node('strong', (pair.left_key || '') + '. '), node('span', pair.left_text || '', '', media));
                if (showAnswer) line.append(node('span', ' → ' + (pair.right_text || ''), 'text-success', media));
                list.append(line);
            }
            article.append(list);
        } else if (type === 'ISIAN_SINGKAT') {
            article.append(node('div', 'Jawaban: ____________________', 'border rounded p-2'));
            if (showAnswer) article.append(node('small', question.short_answer_mode === 'NUMERIC'
                ? 'Angka: ' + question.expected_numeric + ' ± ' + question.numeric_tolerance
                : 'Diterima: ' + (question.accepted_values || []).join('; '), 'd-block text-success mt-2'));
        } else if (type === 'URAIAN') {
            article.append(node('div', 'Area jawaban uraian', 'border rounded p-3 text-secondary'));
            if (showAnswer && question.rubric_text) article.append(node('small', 'Rubrik: ' + question.rubric_text, 'd-block text-success mt-2', media));
        }
        return article;
    };
    window.CbtQuestionRenderer = Object.freeze({render, renderPg: render});
})();
