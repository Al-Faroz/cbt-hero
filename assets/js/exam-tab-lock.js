(() => {
    'use strict';

    const runtime = window.CbtExamRuntime;
    if (!runtime) return;

    const key = 'cbtHeroTabLock:' + runtime.attemptId;
    const tabId = window.crypto?.randomUUID?.()
        ?? ('tab-' + Date.now() + '-' + Math.random().toString(36).slice(2));
    const now = Date.now();

    let current = null;
    try { current = JSON.parse(localStorage.getItem(key) || 'null'); } catch (_) {}

    if (current && current.tab_id !== tabId && now - Number(current.updated_at || 0) < 5000) {
        runtime.tabBlocked = true;
        runtime.hardLock(
            'Ujian sudah terbuka di tab lain',
            'Tutup tab ujian yang lain terlebih dahulu. Satu Attempt hanya boleh aktif pada satu tab.'
        );
        return;
    }

    const write = () => {
        try {
            localStorage.setItem(key, JSON.stringify({tab_id: tabId, updated_at: Date.now()}));
        } catch (_) {}
    };
    write();
    const heartbeat = window.setInterval(write, 2000);

    const channel = 'BroadcastChannel' in window
        ? new BroadcastChannel('cbt-hero-attempt-' + runtime.attemptId)
        : null;
    channel?.addEventListener('message', event => {
        if (event.data?.type === 'PROBE' && event.data?.tab_id !== tabId) {
            channel.postMessage({type: 'ALIVE', tab_id: tabId});
        }
    });
    channel?.postMessage({type: 'PROBE', tab_id: tabId});

    window.addEventListener('beforeunload', () => {
        clearInterval(heartbeat);
        try {
            const latest = JSON.parse(localStorage.getItem(key) || 'null');
            if (latest?.tab_id === tabId) localStorage.removeItem(key);
        } catch (_) {}
        channel?.close();
    });
})();
