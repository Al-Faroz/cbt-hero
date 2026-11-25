(() => {
    'use strict';

    const runtime = window.CbtExamRuntime;
    if (!runtime) return;

    const node = document.getElementById('examTimer');
    let deadlineMs = null;
    let timeoutSent = false;
    let lastText = '';

    const format = seconds => {
        const value = Math.max(0, Math.ceil(seconds));
        const h = Math.floor(value / 3600);
        const m = Math.floor((value % 3600) / 60);
        const s = value % 60;
        return [h, m, s].map(part => String(part).padStart(2, '0')).join(':');
    };

    const tick = () => {
        if (!Number.isFinite(deadlineMs) || runtime.paused) return;
        const remaining = Math.max(0, (deadlineMs - runtime.serverNowMs()) / 1000);
        const text = format(remaining);
        if (node && text !== lastText) {
            node.textContent = text;
            lastText = text;
            node.classList.toggle('text-warning', remaining <= 300 && remaining > 60);
            node.classList.toggle('text-danger', remaining <= 60);
        }

        if (remaining <= 0 && !timeoutSent) {
            timeoutSent = true;
            runtime.lockInputs('Waktu habis');
            runtime.emit('cbt:timeout', {source: 'timer'});
        }
    };

    const setDeadline = value => {
        const parsed = Date.parse(value || '');
        if (!Number.isFinite(parsed)) return;
        deadlineMs = parsed;
        if (deadlineMs > runtime.serverNowMs()) timeoutSent = false;
        tick();
    };

    document.addEventListener('cbt:bootstrap-ready', event => {
        const attempt = event.detail?.data?.attempt || {};
        if (attempt.paused) {
            runtime.paused = true;
            const remaining = Number(attempt.remaining_seconds);
            if (node && Number.isFinite(remaining)) {
                lastText = format(remaining);
                node.textContent = lastText;
            }
            deadlineMs = Date.parse(attempt.deadline_at || '');
            return;
        }
        setDeadline(attempt.deadline_at);
    });
    document.addEventListener('cbt:deadline-updated', event => {
        runtime.paused = false;
        setDeadline(event.detail?.deadline_at);
    });
    document.addEventListener('cbt:attempt-paused', event => {
        runtime.paused = true;
        const remaining = Number(event.detail?.remaining_seconds);
        if (node && Number.isFinite(remaining)) {
            lastText = format(remaining);
            node.textContent = lastText;
        }
    });
    document.addEventListener('cbt:timeout-authority', () => {
        if (!timeoutSent) {
            timeoutSent = true;
            runtime.lockInputs('Waktu habis');
            runtime.emit('cbt:timeout', {source: 'server'});
        }
    });

    setInterval(tick, 250);
})();
