(() => {
    'use strict';

    const runtime = window.CbtExamRuntime;
    if (!runtime) return;

    document.addEventListener('cbt:bootstrap-ready', async event => {
        const media = event.detail?.data?.media_manifest || [];
        const critical = media.filter(item => item.critical && item.kind === 'IMAGE' && item.url);
        if (!critical.length) {
            runtime.emit('cbt:client-event', {event_type: 'PACKAGE_READY', metadata: {critical_images: 0}});
            return;
        }

        await Promise.allSettled(critical.map(item => new Promise(resolve => {
            const image = new Image();
            image.onload = resolve;
            image.onerror = resolve;
            image.src = item.url;
        })));
        runtime.emit('cbt:client-event', {
            event_type: 'PACKAGE_READY',
            metadata: {critical_images: critical.length}
        });
    }, {once: true});
})();
