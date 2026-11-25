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

        // Persist any debounced text input before applying REANSWER/VOID policy.
        await window.CbtExamRenderer?.flushPending?.();

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

    const lockForStatusError = async (response, result) => {
        const authoritative = response.status >= 400
            && response.status < 500
            && ![408, 429].includes(response.status);
        if (!authoritative) return;

        const code = String(result?.error?.code || '');
        const messages = {
            CLIENT_MISMATCH: 'Attempt sudah aktif pada client lain. Hubungi pengawas untuk pemeriksaan atau Reset Akses.',
            CLIENT_GENERATION_MISMATCH: 'Akses ujian telah diubah oleh pengawas. Kembali ke daftar ujian dan buka kembali melalui Konfirmasi.',
            CLIENT_ID_INVALID: 'Identitas client tidak valid. Kembali ke daftar ujian dan buka kembali melalui Konfirmasi.',
            ATTEMPT_NOT_ACTIVE: 'Attempt ini sudah tidak aktif.',
            NOT_FOUND: 'Attempt tidak ditemukan atau aksesnya telah berakhir.'
        };
        const message = messages[code] || result?.error?.message || 'Server menolak akses ke Attempt ini.';

        const terminal = ['ATTEMPT_NOT_ACTIVE', 'NOT_FOUND'].includes(code);
        runtime.paused = terminal;
        runtime.pauseReason = terminal ? 'TERMINAL' : null;
        if (runtime.bootstrap?.attempt) runtime.bootstrap.attempt.status = 'BLOCKED';
        runtime.lockInputs('Sesi ujian tidak dapat dilanjutkan');
        runtime.hardLock('Sesi ujian tidak dapat dilanjutkan', message);

        const resumeButton = document.getElementById('examFullscreenResumeButton');
        const exitLink = document.getElementById('examLockExitLink');
        if (resumeButton) resumeButton.hidden = true;
        if (exitLink) exitLink.hidden = false;

        const state = await db.runtime_state.get(runtime.attemptId) || {attempt_id: runtime.attemptId};
        state.server_attempt_status = 'BLOCKED';
        state.updated_at = Date.now();
        await db.runtime_state.put(state);
    };

    const checkStatus = async () => {
        if (statusChecking || !navigator.onLine || runtime.tabBlocked) return;
        statusChecking = true;
        try {
            const statusUrl = runtime.apiBase + '/status?since=' + encodeURIComponent(runtime.serverSyncRevision);
            const response = await fetch(statusUrl, {
                credentials: 'same-origin',
                headers: {'Accept': 'application/json', ...runtime.clientHeaders()}
            });
            const result = await response.json().catch(() => null);
            if (!response.ok || result?.ok !== true) {
                await lockForStatusError(response, result);
                return;
            }

            const data = result.data || {};
            const nextServerRevision = Math.max(
                runtime.serverSyncRevision,
                Number(data.server_sync_revision || 0)
            );
            if (data.deadline_at && runtime.bootstrap?.attempt) {
                runtime.bootstrap.attempt.deadline_at = data.deadline_at;
                if (!data.paused) runtime.emit('cbt:deadline-updated', {deadline_at: data.deadline_at});
            }
            await applyRevisionChanges(data.revision_changes || []);
            runtime.serverSyncRevision = nextServerRevision;
            const persistedState = await db.runtime_state.get(runtime.attemptId) || {attempt_id: runtime.attemptId};
            persistedState.server_attempt_status = String(data.status || 'ACTIVE');
            persistedState.server_sync_revision = nextServerRevision;
            persistedState.deadline_at = data.deadline_at || persistedState.deadline_at || null;
            persistedState.updated_at = Date.now();
            await db.runtime_state.put(persistedState);

            const attemptStatus = String(data.status || 'ACTIVE');
            const serverPaused = Boolean(data.paused);
            if (runtime.bootstrap?.attempt) {
                runtime.bootstrap.attempt.status = attemptStatus;
                runtime.bootstrap.attempt.paused = serverPaused;
                runtime.bootstrap.attempt.pause_reason = data.pause_reason || null;
            }

            const overlay = document.getElementById('examLockOverlay');
            const title = document.getElementById('examLockTitle');
            const message = document.getElementById('examLockMessage');
            const resumeButton = document.getElementById('examFullscreenResumeButton');
            const exitLink = document.getElementById('examLockExitLink');

            if (attemptStatus !== 'ACTIVE') {
                runtime.paused = true;
                runtime.pauseReason = 'TERMINAL';
                runtime.lockInputs('Ujian sudah selesai');
                if (overlay) overlay.hidden = false;
                if (title) title.textContent = 'Ujian sudah selesai';
                if (message) message.textContent = 'Attempt telah diakhiri oleh server. Kembali ke daftar ujian.';
                if (resumeButton) resumeButton.hidden = true;
                if (exitLink) exitLink.hidden = false;
                runtime.emit('cbt:attempt-status-changed', {status: attemptStatus});
            } else if (serverPaused) {
                const reason = String(data.pause_reason || runtime.pauseReason || 'OPERATOR');
                runtime.pauseReason = reason === 'FULLSCREEN_EXIT' ? 'FULLSCREEN_EXIT' : 'OPERATOR';
                runtime.paused = true;
                runtime.lockInputs(runtime.pauseReason === 'FULLSCREEN_EXIT'
                    ? 'Ujian dijeda · layar penuh diperlukan'
                    : 'Ujian dijeda oleh pengawas');
                runtime.emit('cbt:attempt-paused', {
                    reason: runtime.pauseReason,
                    remaining_seconds: data.remaining_seconds
                });
                if (overlay) overlay.hidden = false;
                if (title) title.textContent = 'Ujian dijeda';
                if (message) message.textContent = runtime.pauseReason === 'FULLSCREEN_EXIT'
                    ? 'Attempt dijeda karena keluar dari layar penuh. Kembali ke layar penuh untuk melanjutkan ujian.'
                    : 'Pengawas sedang menjeda ujian. Tunggu sampai pengawas melanjutkan sesi.';
                if (resumeButton) {
                    resumeButton.hidden = runtime.pauseReason !== 'FULLSCREEN_EXIT';
                    resumeButton.disabled = false;
                }
                if (exitLink) exitLink.hidden = true;
            } else if (runtime.pauseReason === 'OPERATOR' || runtime.pauseReason === 'FULLSCREEN_EXIT') {
                runtime.paused = false;
                runtime.pauseReason = null;
                runtime.unlockInputs();
                if (overlay) overlay.hidden = true;
                if (resumeButton) resumeButton.hidden = true;
                if (exitLink) exitLink.hidden = false;
                if (data.deadline_at) runtime.emit('cbt:deadline-updated', {deadline_at: data.deadline_at});
                runtime.emit('cbt:server-resumed');
                runtime.emit('cbt:sync-needed');
            }
        } catch (_) {
        } finally {
            statusChecking = false;
        }
    };

    const syncOnce = async () => {
        if (syncing || retryTimer || !navigator.onLine || runtime.inputLocked && runtime.bootstrap?.attempt?.status !== 'ACTIVE') return false;
        const queue = await db.sync_queue.where('attempt_id').equals(runtime.attemptId).sortBy('queued_at');
        if (!queue.length) {
            runtime.setSyncState('Tersinkron', 'synced');
            return true;
        }

        syncing = true;
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
            const previousServerRevision = runtime.serverSyncRevision;
            const nextServerRevision = Number(data.server_sync_revision || previousServerRevision);
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
                state.server_sync_revision = previousServerRevision;
                state.deadline_at = data.deadline_at || state.deadline_at || null;
                state.updated_at = Date.now();
                await db.runtime_state.put(state);
            });

            await applyRevisionChanges(data.revision_changes || []);
            runtime.serverSyncRevision = nextServerRevision;
            const runtimeState = await db.runtime_state.get(runtime.attemptId) || {attempt_id: runtime.attemptId};
            runtimeState.server_sync_revision = runtime.serverSyncRevision;
            runtimeState.deadline_at = data.deadline_at || runtimeState.deadline_at || null;
            runtimeState.updated_at = Date.now();
            await db.runtime_state.put(runtimeState);

            const remaining = await db.sync_queue.where('attempt_id').equals(runtime.attemptId).count();
            runtime.setSyncState(remaining ? 'Belum sinkron' : 'Tersinkron', remaining ? 'pending' : 'synced');
            const ackByMutation = new Map((data.acks || []).map(ack => [String(ack.mutation_id), ack]));
            const retryableBatch = batch.some(row => {
                const ack = ackByMutation.get(String(row.mutation_id));
                return !ack || (!ack.accepted && !permanentReject.has(String(ack.reason || '')));
            });
            syncing = false;
            if (!remaining) {
                retryDelay = 1000;
            } else if (retryableBatch) {
                const jitter = Math.floor(Math.random() * 350);
                retryTimer = setTimeout(() => {
                    retryTimer = null;
                    void syncOnce();
                }, retryDelay + jitter);
                retryDelay = Math.min(30000, retryDelay * 2);
            } else {
                retryDelay = 1000;
                queueMicrotask(syncOnce);
            }
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

    document.addEventListener('cbt:bootstrap-ready', event => {
        const attempt = event.detail?.data?.attempt || {};
        if (!attempt.paused) return;

        const reason = String(attempt.pause_reason || runtime.pauseReason || 'OPERATOR');
        runtime.pauseReason = reason === 'FULLSCREEN_EXIT' ? 'FULLSCREEN_EXIT' : 'OPERATOR';
        runtime.paused = true;
        runtime.lockInputs(runtime.pauseReason === 'FULLSCREEN_EXIT'
            ? 'Ujian dijeda · layar penuh diperlukan'
            : 'Ujian dijeda oleh pengawas');

        const overlay = document.getElementById('examLockOverlay');
        const title = document.getElementById('examLockTitle');
        const message = document.getElementById('examLockMessage');
        const resumeButton = document.getElementById('examFullscreenResumeButton');
        const exitLink = document.getElementById('examLockExitLink');
        if (overlay) overlay.hidden = false;
        if (title) title.textContent = 'Ujian dijeda';
        if (message) message.textContent = runtime.pauseReason === 'FULLSCREEN_EXIT'
            ? 'Attempt dijeda karena keluar dari layar penuh. Kembali ke layar penuh untuk melanjutkan ujian.'
            : 'Pengawas sedang menjeda ujian. Tunggu sampai pengawas melanjutkan sesi.';
        if (resumeButton) {
            resumeButton.hidden = runtime.pauseReason !== 'FULLSCREEN_EXIT';
            resumeButton.disabled = false;
        }
        if (exitLink) exitLink.hidden = true;
    });

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
    setInterval(checkStatus, 30000);
    window.CbtExamSync = {syncOnce, drain, postEvent, checkStatus, applyRevisionChanges};
})();
