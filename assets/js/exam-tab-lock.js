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
    let heartbeat = window.setInterval(write, 2000);

    const channel = 'BroadcastChannel' in window
        ? new BroadcastChannel('cbt-hero-attempt-' + runtime.attemptId)
        : null;
    const blockDuplicateTab = () => {
        if (runtime.tabBlocked) return;
        runtime.tabBlocked = true;
        clearInterval(heartbeat);
        try {
            const latest = JSON.parse(localStorage.getItem(key) || 'null');
            if (latest?.tab_id === tabId) localStorage.removeItem(key);
        } catch (_) {}
        channel?.close();
        runtime.hardLock(
            'Ujian sudah terbuka di tab lain',
            'Tutup tab ujian yang lain terlebih dahulu. Satu Attempt hanya boleh aktif pada satu tab.'
        );
    };

    channel?.addEventListener('message', event => {
        if (event.data?.tab_id === tabId) return;
        if (event.data?.type === 'PROBE') {
            channel.postMessage({type: 'ALIVE', tab_id: tabId});
        } else if (event.data?.type === 'ALIVE') {
            blockDuplicateTab();
        }
    });
    channel?.postMessage({type: 'PROBE', tab_id: tabId});

    const release = (closeChannel = false) => {
        if (heartbeat) clearInterval(heartbeat);
        heartbeat = null;
        try {
            const latest = JSON.parse(localStorage.getItem(key) || 'null');
            if (latest?.tab_id === tabId) localStorage.removeItem(key);
        } catch (_) {}
        if (closeChannel) channel?.close();
    };
    window.addEventListener('beforeunload', () => release(true), {once: true});
    window.addEventListener('pagehide', () => release(false));
    window.addEventListener('pageshow', event => {
        if (!event.persisted || runtime.tabBlocked) return;
        let latest = null;
        try { latest = JSON.parse(localStorage.getItem(key) || 'null'); } catch (_) {}
        if (latest && latest.tab_id !== tabId
            && Date.now() - Number(latest.updated_at || 0) < 5000) {
            blockDuplicateTab();
            return;
        }
        write();
        if (!heartbeat) heartbeat = window.setInterval(write, 2000);
    });
})();
