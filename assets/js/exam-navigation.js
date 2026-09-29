(() => {
    'use strict';

    const runtime = window.CbtExamRuntime;
    const db = window.CbtExamDb;
    if (!runtime || !db) return;

    const grid = document.getElementById('examPaletteGrid');
    const prev = document.getElementById('examPrev');
    const next = document.getElementById('examNext');
    const palette = document.getElementById('examPalette');
    const toggle = document.getElementById('examPaletteToggle');
    const close = document.getElementById('examPaletteClose');
    const backdrop = document.getElementById('examPaletteBackdrop');

    const refreshPalette = async () => {
        const items = runtime.bootstrap?.package?.items || [];
        const answers = await db.answer_store.where('attempt_id').equals(runtime.attemptId).toArray();
        const byItem = new Map(answers.map(row => [Number(row.item_id), row]));

        if (grid) {
            grid.replaceChildren();
            items.forEach((item, index) => {
                const row = byItem.get(Number(item.item_id));
                const button = document.createElement('button');
                button.type = 'button';
                button.className = 'exam-palette-button';
                button.textContent = String(index + 1);
                if (index === runtime.currentIndex) button.classList.add('is-current');
                if (window.CbtExamRenderer?.isAnswered(row?.answer_payload, item.question_type))
                    button.classList.add('is-answered');
                if (row?.is_flagged) button.classList.add('is-flagged');
                button.addEventListener('click', () => {
                    window.CbtExamRenderer?.renderIndex(index);
                    closePalette();
                });
                grid.append(button);
            });
        }

        const answered = items.reduce((count, item) => {
            const row = byItem.get(Number(item.item_id));
            return count + (window.CbtExamRenderer?.isAnswered(row?.answer_payload, item.question_type) ? 1 : 0);
        }, 0);
        const flagged = items.reduce((count, item) => {
            const row = byItem.get(Number(item.item_id));
            return count + (row?.is_flagged ? 1 : 0);
        }, 0);
        const answeredNode = document.getElementById('examAnsweredCount');
        const flaggedNode = document.getElementById('examFlaggedCount');
        if (answeredNode) answeredNode.textContent = answered + ' dijawab';
        if (flaggedNode) flaggedNode.textContent = flagged + ' ditandai';
    };

    const updatePosition = () => {
        const items = runtime.bootstrap?.package?.items || [];
        const node = document.getElementById('examQuestionPosition');
        if (node) node.textContent = items.length
            ? 'Soal ' + (runtime.currentIndex + 1) + ' / ' + items.length
            : 'Soal - / -';
        if (prev) prev.disabled = runtime.currentIndex <= 0;
        if (next) next.disabled = runtime.currentIndex >= items.length - 1;
        refreshPalette();
    };

    prev?.addEventListener('click', () => {
        if (runtime.currentIndex > 0)
            window.CbtExamRenderer?.renderIndex(runtime.currentIndex - 1);
    });
    next?.addEventListener('click', () => {
        const items = runtime.bootstrap?.package?.items || [];
        if (runtime.currentIndex < items.length - 1)
            window.CbtExamRenderer?.renderIndex(runtime.currentIndex + 1);
    });
    const openPalette = () => {
        if (!window.matchMedia('(max-width: 991.98px)').matches) return;
        palette?.classList.add('is-open');
        if (backdrop) backdrop.hidden = false;
    };
    const closePalette = () => {
        palette?.classList.remove('is-open');
        if (backdrop) backdrop.hidden = true;
    };

    toggle?.addEventListener('click', openPalette);
    close?.addEventListener('click', closePalette);
    backdrop?.addEventListener('click', closePalette);

    document.addEventListener('cbt:bootstrap-ready', updatePosition);
    document.addEventListener('cbt:question-rendered', updatePosition);
    document.addEventListener('cbt:answer-saved', refreshPalette);
})();
