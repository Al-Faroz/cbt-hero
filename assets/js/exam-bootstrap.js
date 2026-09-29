(() => {
    'use strict';

    const runtime = window.CbtExamRuntime;
    const db = window.CbtExamDb;
    if (!runtime || !db || runtime.tabBlocked || runtime.inputLocked) return;

    const offlineBanner = document.getElementById('examOfflineBanner');

    const storeNetworkPackage = async (data, serverTime) => {
        const attemptId = runtime.attemptId;
        const questions = (data.package?.items || []).map(item => ({...item, attempt_id: attemptId}));
        const parsedServerTime = serverTime ? Date.parse(serverTime) : NaN;
        const serverOffsetMs = Number.isFinite(parsedServerTime) ? parsedServerTime - Date.now() : 0;
        const media = (data.media_manifest || []).map(item => ({
            attempt_id: attemptId,
            media_id: Number(item.id),
            ...item
        }));

        await db.transaction('rw',
            db.attempt_meta, db.question_cache, db.answer_store, db.media_manifest, db.runtime_state,
            async () => {
                await db.attempt_meta.put({
                    attempt_id: attemptId,
                    attempt: data.attempt,
                    participant: data.participant,
                    exam: data.exam,
                    server_time: serverTime,
                    server_offset_ms: serverOffsetMs,
                    updated_at: Date.now()
                });

                await db.question_cache.where('attempt_id').equals(attemptId).delete();
                if (questions.length) await db.question_cache.bulkPut(questions);

                await db.media_manifest.where('attempt_id').equals(attemptId).delete();
                if (media.length) await db.media_manifest.bulkPut(media);

                for (const [itemIdText, serverAnswer] of Object.entries(data.answers || {})) {
                    const itemId = Number(itemIdText);
                    const local = await db.answer_store.get([attemptId, itemId]);
                    if (local && Number(local.client_revision) > Number(serverAnswer.client_revision || 0)) continue;
                    await db.answer_store.put({
                        attempt_id: attemptId,
                        item_id: itemId,
                        answer_payload: serverAnswer.answer_payload,
                        is_flagged: Boolean(serverAnswer.is_flagged),
                        client_revision: Number(serverAnswer.client_revision || 0),
                        server_revision: Number(serverAnswer.server_revision || 0),
                        updated_at: Date.now()
                    });
                }

                const state = await db.runtime_state.get(attemptId) || {attempt_id: attemptId};
                state.server_sync_revision = Number(data.attempt?.server_sync_revision || 0);
                state.deadline_at = data.attempt?.deadline_at || null;
                state.updated_at = Date.now();
                await db.runtime_state.put(state);
            }
        );

        runtime.setBootstrap(data, serverTime);
        if (offlineBanner) offlineBanner.hidden = true;
        runtime.emit('cbt:bootstrap-ready', {source: 'network', data});
    };

    const loadCachedPackage = async () => {
        const attemptId = runtime.attemptId;
        const meta = await db.attempt_meta.get(attemptId);
        const questions = await db.question_cache.where('attempt_id').equals(attemptId).sortBy('sequence_no');
        if (!meta || !questions.length) return false;

        const media = await db.media_manifest.where('attempt_id').equals(attemptId).sortBy('prefetch_order');
        const state = await db.runtime_state.get(attemptId);
        const data = {
            attempt: {
                ...(meta.attempt || {}),
                server_sync_revision: Number(state?.server_sync_revision ?? meta.attempt?.server_sync_revision ?? 0),
                deadline_at: state?.deadline_at || meta.attempt?.deadline_at
            },
            participant: meta.participant || {},
            exam: meta.exam || {},
            package: {mode: 'FULL', items: questions, item_count: questions.length},
            answers: {},
            media_manifest: media
        };
        runtime.setBootstrap(data, null);
        runtime.serverOffsetMs = Number(meta.server_offset_ms || 0);
        if (offlineBanner) offlineBanner.hidden = false;
        runtime.setSyncState('Offline · tersimpan lokal', 'offline');
        runtime.emit('cbt:bootstrap-ready', {source: 'cache', data});
        runtime.emit('cbt:client-event', {event_type: 'CACHE_RECOVERED', metadata: {attempt_id: attemptId}});
        return true;
    };

    const load = async () => {
        runtime.setSyncState('Memuat paket...', 'loading');
        try {
            const response = await fetch(runtime.apiBase + '/bootstrap', {
                credentials: 'same-origin',
                headers: {
                    'Accept': 'application/json',
                    ...runtime.clientHeaders()
                }
            });
            const result = await response.json().catch(() => null);
            if (!response.ok || result?.ok !== true)
                throw new Error(result?.error?.message || 'Paket ujian gagal dimuat.');

            await storeNetworkPackage(result.data, result.meta?.server_time || null);
            runtime.setSyncState('Tersinkron', 'synced');
        } catch (error) {
            const recovered = await loadCachedPackage().catch(() => false);
            if (!recovered) {
                runtime.hardLock(
                    'Paket ujian belum tersedia',
                    error.message || 'Periksa koneksi lalu buka kembali ujian.'
                );
            }
        }
    };

    load();
})();
