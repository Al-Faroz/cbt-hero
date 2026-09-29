(() => {
    'use strict';

    const runtime = window.CbtExamRuntime;
    const db = window.CbtExamDb;
    if (!runtime || !db) return;

    let syncing = false;
    let retryTimer = null;
    let retryDelay = 1000;

    const offlineBanner = document.getElementById('examOfflineBanner');

    const setOnlineState = () => {
        if (offlineBanner) offlineBanner.hidden = navigator.onLine;
        if (!navigator.onLine) runtime.setSyncState('Offline · tersimpan lokal', 'offline');
    };

    const permanentReject = new Set(['STALE_REVISION', 'ANSWER_INVALID', 'ITEM_NOT_ASSIGNED', 'INVALID_MUTATION', 'TIME_EXPIRED']);
    let statusChecking = false;

    const applyRevisionChanges = async changes => {
        if (!Array.isArray(changes) || !changes.length || !runtime.bootstrap?.package?.items) return false;
        let changed = false;
        let rerender = false;

        await db.transaction('rw', db.question_cache, db.answer_store, db.sync_queue, db.media_manifest, async () => {
            for (const change of changes) {
                const itemId = Number(change.item_id || 0);
                const revisionId = Number(change.revision_id || 0);
                const index = runtime.bootstrap.package.items.findIndex(item => Number(item.item_id) === itemId);
                if (itemId < 1 || revisionId < 1 || index < 0) continue;

                const current = runtime.bootstrap.package.items[index];
                if (Number(current.revision_id || 0) === revisionId) continue;

                const media = Array.isArray(change.media_manifest) ? change.media_manifest : [];
                const next = {...change, attempt_id: runtime.attemptId};
                delete next.media_manifest;
                delete next.change_kind;
                delete next.change_note;
                delete next.answer_policy;

                runtime.bootstrap.package.items[index] = next;
                await db.question_cache.put(next);

                for (const asset of media) {
                    const mediaId = Number(asset.id || 0);
                    if (mediaId < 1) continue;
                    runtime.mediaMap[String(mediaId)] = {
                        kind: asset.kind,
                        provider: asset.provider,
                        url: asset.url
                    };
                    await db.media_manifest.put({
                        attempt_id: runtime.attemptId,
                        media_id: mediaId,
                        id: mediaId,
                        kind: asset.kind,
                        provider: asset.provider,
                        url: asset.url,
                        critical: false,
                        prefetch_order: 999999
                    });
                }

                const policy = String(change.answer_policy || 'PRESERVE');
                if (policy === 'REANSWER') {
                    await db.answer_store.delete([runtime.attemptId, itemId]);
                    await db.sync_queue.where('attempt_id').equals(runtime.attemptId)
                        .filter(row => Number(row.item_id) === itemId).delete();
                } else if (change.voided) {
                    await db.sync_queue.where('attempt_id').equals(runtime.attemptId)
                        .filter(row => Number(row.item_id) === itemId).delete();
                }

                if (index === Number(runtime.currentIndex || 0)) rerender = true;
                changed = true;
            }
        });

        if (changed) {
            runtime.setSyncState('Pembaruan soal diterapkan', 'synced');
            runtime.emit('cbt:revision-applied', {changes});
            if (rerender) window.CbtExamRenderer?.renderIndex(runtime.currentIndex || 0);
        }
        return changed;
    };

    const checkStatus = async () => {
        if (statusChecking || !navigator.onLine || runtime.tabBlocked) return;
        statusChecking = true;
        try {
            const response = await fetch(runtime.apiBase + '/status', {
                credentials: 'same-origin',
                headers: {'Accept': 'application/json', ...runtime.clientHeaders()}
            });
            const result = await response.json().catch(() => null);
            if (!response.ok || result?.ok !== true) return;

            const data = result.data || {};
            runtime.serverSyncRevision = Math.max(
                runtime.serverSyncRevision,
                Number(data.server_sync_revision || 0)
            );
            if (data.deadline_at && runtime.bootstrap?.attempt) {
                runtime.bootstrap.attempt.deadline_at = data.deadline_at;
                runtime.emit('cbt:deadline-updated', {deadline_at: data.deadline_at});
            }
            await applyRevisionChanges(data.revision_changes || []);
            if (String(data.status || '') !== 'ACTIVE') {
                runtime.lockInputs('Ujian sudah selesai');
                runtime.emit('cbt:attempt-status-changed', {status: data.status});
            }
        } catch (_) {
        } finally {
            statusChecking = false;
        }
    };

    const syncOnce = async () => {
        if (syncing || !navigator.onLine || runtime.inputLocked && runtime.bootstrap?.attempt?.status !== 'ACTIVE') return false;
        const queue = await db.sync_queue.where('attempt_id').equals(runtime.attemptId).sortBy('queued_at');
        if (!queue.length) {
            runtime.setSyncState('Tersinkron', 'synced');
            return true;
        }

        syncing = true;
        clearTimeout(retryTimer);
        runtime.setSyncState('Menyinkronkan...', 'syncing');

        const batch = queue.slice(0, 20);
        try {
            const response = await fetch(runtime.apiBase + '/sync', {
                method: 'POST',
                credentials: 'same-origin',
                headers: {
                    'Accept': 'application/json',
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.content ?? '',
                    ...runtime.clientHeaders()
                },
                body: JSON.stringify({
                    base_server_revision: runtime.serverSyncRevision,
                    mutations: batch.map(row => ({
                        mutation_id: row.mutation_id,
                        item_id: row.item_id,
                        client_revision: row.client_revision,
                        answer_payload: row.answer_payload,
                        is_flagged: row.is_flagged,
                        answered_at_client: row.answered_at_client,
                        client_elapsed_ms: row.client_elapsed_ms
                    }))
                })
            });
            const result = await response.json().catch(() => null);
            if (!response.ok || result?.ok !== true)
                throw new Error(result?.error?.message ?? 'Sinkronisasi gagal.');

            const data = result.data;
            runtime.serverSyncRevision = Number(data.server_sync_revision || runtime.serverSyncRevision);
            if (data.deadline_at && runtime.bootstrap?.attempt) {
                runtime.bootstrap.attempt.deadline_at = data.deadline_at;
                runtime.emit('cbt:deadline-updated', {deadline_at: data.deadline_at});
            }

            await db.transaction('rw', db.sync_queue, db.answer_store, db.runtime_state, async () => {
                for (const ack of data.acks || []) {
                    const queued = await db.sync_queue.get(ack.mutation_id);
                    if (!queued) continue;
                    if (ack.accepted || permanentReject.has(String(ack.reason || ''))) {
                        await db.sync_queue.delete(ack.mutation_id);
                    }
                    if (ack.accepted) {
                        const answer = await db.answer_store.get([runtime.attemptId, Number(ack.item_id)]);
                        if (answer && Number(answer.client_revision) <= Number(ack.client_revision)) {
                            answer.server_revision = Number(ack.server_revision || 0);
                            answer.updated_at = Date.now();
                            await db.answer_store.put(answer);
                        }
                    }
                    if (ack.reason === 'TIME_EXPIRED') runtime.emit('cbt:timeout-authority');
                }

                const state = await db.runtime_state.get(runtime.attemptId) || {attempt_id: runtime.attemptId};
                state.server_sync_revision = runtime.serverSyncRevision;
                state.deadline_at = data.deadline_at || state.deadline_at || null;
                state.updated_at = Date.now();
                await db.runtime_state.put(state);
            });

            await applyRevisionChanges(data.revision_changes || []);

            retryDelay = 1000;
            const remaining = await db.sync_queue.where('attempt_id').equals(runtime.attemptId).count();
            runtime.setSyncState(remaining ? 'Belum sinkron' : 'Tersinkron', remaining ? 'pending' : 'synced');
            syncing = false;
            if (remaining) queueMicrotask(syncOnce);
            return remaining === 0;
        } catch (error) {
            syncing = false;
            runtime.setSyncState(navigator.onLine ? 'Belum sinkron' : 'Offline · tersimpan lokal', 'pending');
            if (navigator.onLine) {
                const jitter = Math.floor(Math.random() * 350);
                retryTimer = setTimeout(syncOnce, retryDelay + jitter);
                retryDelay = Math.min(30000, retryDelay * 2);
            }
            return false;
        }
    };

    const drain = async (timeoutMs = 12000) => {
        const start = Date.now();
        while (Date.now() - start < timeoutMs) {
            const count = await db.sync_queue.where('attempt_id').equals(runtime.attemptId).count();
            if (count === 0) return true;
            if (!navigator.onLine) return false;
            await syncOnce();
            await new Promise(resolve => setTimeout(resolve, 120));
        }
        return (await db.sync_queue.where('attempt_id').equals(runtime.attemptId).count()) === 0;
    };

    const postEvent = async (eventType, metadata = {}) => {
        if (!navigator.onLine) return;
        try {
            await fetch(runtime.apiBase + '/event', {
                method: 'POST',
                credentials: 'same-origin',
                headers: {
                    'Accept': 'application/json',
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.content ?? '',
                    ...runtime.clientHeaders()
                },
                body: JSON.stringify({event_type: eventType, metadata})
            });
        } catch (_) {}
    };

    document.addEventListener('cbt:sync-needed', syncOnce);
    document.addEventListener('cbt:client-event', event => {
        postEvent(event.detail?.event_type, event.detail?.metadata || {});
    });
    window.addEventListener('online', () => {
        setOnlineState();
        postEvent('SYNC_RECOVERED', {queued: true});
        syncOnce();
        runtime.emit('cbt:online');
    });
    window.addEventListener('offline', setOnlineState);
    document.addEventListener('cbt:bootstrap-ready', () => {
        setOnlineState();
        syncOnce();
        checkStatus();
    });

    setOnlineState();
    setInterval(checkStatus, 10000);
    window.CbtExamSync = {syncOnce, drain, postEvent, checkStatus, applyRevisionChanges};
})();
