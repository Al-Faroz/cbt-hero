(() => {
    'use strict';

    const runtime = window.CbtExamRuntime;
    const db = window.CbtExamDb;
    if (!runtime || !db) return;

    const objectUrls = new Set();

    const localUrl = value => {
        try {
            const url = new URL(String(value || ''), window.location.href);
            return url.origin === window.location.origin ? url.href : null;
        } catch (_) {
            return null;
        }
    };

    const prepareOne = async media => {
        if (media.kind !== 'IMAGE' || !media.critical || !media.url) return false;
        const mediaId = Number(media.id || media.media_id);
        if (!Number.isInteger(mediaId) || mediaId < 1) return false;

        let blob = null;
        try {
            const cached = await db.media_cache.get([runtime.attemptId, mediaId]);
            if (cached?.blob instanceof Blob) blob = cached.blob;
        } catch (_) {}

        if (!blob && navigator.onLine) {
            const url = localUrl(media.url);
            if (url) {
                const controller = new AbortController();
                const timeout = window.setTimeout(() => controller.abort(), 8000);
                try {
                    const response = await fetch(url, {
                        credentials: 'same-origin',
                        headers: {'Accept': 'image/*'},
                        signal: controller.signal
                    });
                    if (response.ok) {
                        const candidate = await response.blob();
                        if (candidate.type.startsWith('image/')) {
                            blob = candidate;
                            await db.media_cache.put({
                                attempt_id: runtime.attemptId,
                                media_id: mediaId,
                                blob,
                                updated_at: Date.now()
                            });
                        }
                    }
                } catch (_) {
                } finally {
                    window.clearTimeout(timeout);
                }
            }
        }

        if (!blob) return false;
        try {
            const objectUrl = URL.createObjectURL(blob);
            objectUrls.add(objectUrl);
            const asset = runtime.mediaMap[String(mediaId)];
            if (asset) asset.url = objectUrl;
            return true;
        } catch (_) {
            return false;
        }
    };

    const prepare = async (manifest, options = {}) => {
        const images = (manifest || []).filter(item =>
            item && item.kind === 'IMAGE' && item.critical && item.url
        );
        const results = await Promise.allSettled(images.map(prepareOne));
        const cachedCount = results.filter(result => result.status === 'fulfilled' && result.value).length;
        if (options.emitReady !== false) {
            runtime.emit('cbt:client-event', {
                event_type: 'PACKAGE_READY',
                metadata: {critical_images: images.length, cached_images: cachedCount}
            });
        }
        return {total: images.length, cached: cachedCount};
    };

    window.addEventListener('beforeunload', () => {
        for (const url of objectUrls) URL.revokeObjectURL(url);
        objectUrls.clear();
    });

    window.CbtExamMedia = {prepare};
})();
