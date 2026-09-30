(() => {
    'use strict';

    const workspace = document.getElementById('examWorkspace');
    const card = document.getElementById('examQuestionCard');
    if (!workspace || !card) return;

    const FONT_KEY = 'cbthero.exam.font-size';
    const allowed = new Set(['small', 'normal', 'large']);

    const applyFont = value => {
        const mode = allowed.has(value) ? value : 'normal';
        card.classList.remove('exam-font-small', 'exam-font-normal', 'exam-font-large');
        card.classList.add('exam-font-' + mode);
        document.querySelectorAll('[data-exam-font]').forEach(button => {
            const active = button.dataset.examFont === mode;
            button.classList.toggle('is-active', active);
            button.setAttribute('aria-pressed', active ? 'true' : 'false');
        });
        localStorage.setItem(FONT_KEY, mode);
    };

    document.querySelectorAll('[data-exam-font]').forEach(button => {
        button.addEventListener('click', () => applyFont(button.dataset.examFont));
    });
    applyFont(localStorage.getItem(FONT_KEY) || 'normal');

    const modalNode = document.getElementById('examImageModal');
    const image = document.getElementById('examImageZoomTarget');
    const stage = document.getElementById('examImageStage');
    if (!modalNode || !image || !stage || typeof window.Panzoom !== 'function') return;

    const modal = bootstrap.Modal.getOrCreateInstance(modalNode);
    let panzoom = null;

    const resetZoom = () => {
        if (!panzoom) return;
        panzoom.reset({animate: false});
    };

    const openImage = source => {
        image.src = source.currentSrc || source.src;
        image.alt = source.alt || 'Gambar soal diperbesar';
        modal.show();
    };

    card.addEventListener('click', event => {
        const source = event.target.closest('img');
        if (!source) return;
        event.preventDefault();
        openImage(source);
    });

    modalNode.addEventListener('shown.bs.modal', () => {
        panzoom?.destroy();
        panzoom = window.Panzoom(image, {
            maxScale: 5,
            minScale: 1,
            contain: 'outside',
            cursor: 'grab'
        });
        stage.addEventListener('wheel', panzoom.zoomWithWheel, {passive: false});
        resetZoom();
    });

    modalNode.addEventListener('hidden.bs.modal', () => {
        if (panzoom) {
            stage.removeEventListener('wheel', panzoom.zoomWithWheel);
            panzoom.destroy();
            panzoom = null;
        }
        image.removeAttribute('src');
    });

    document.getElementById('examImageZoomIn')?.addEventListener('click', () => {
        panzoom?.zoomIn();
    });
    document.getElementById('examImageZoomOut')?.addEventListener('click', () => {
        panzoom?.zoomOut();
    });
    document.getElementById('examImageZoomReset')?.addEventListener('click', resetZoom);
})();
