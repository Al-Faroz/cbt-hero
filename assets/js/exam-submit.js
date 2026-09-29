(() => {
    'use strict';

    const runtime = window.CbtExamRuntime;
    const db = window.CbtExamDb;
    if (!runtime || !db) return;

    const button = document.getElementById('examSubmit');
    let finalizing = false;

    const key = () => 'finalize:' + (window.crypto?.randomUUID?.()
        ?? (Date.now() + '-' + Math.random().toString(36).slice(2)));

    const runtimeState = async () =>
        await db.runtime_state.get(runtime.attemptId) || {attempt_id: runtime.attemptId};

    const saveRuntimeState = state => db.runtime_state.put({...state, attempt_id: runtime.attemptId, updated_at: Date.now()});

    const cleanup = async () => {
        const attemptId = runtime.attemptId;
        await db.transaction(
            'rw',
            db.attempt_meta, db.question_cache, db.answer_store, db.sync_queue, db.media_manifest, db.runtime_state,
            async () => {
                await db.attempt_meta.delete(attemptId);
                await db.question_cache.where('attempt_id').equals(attemptId).delete();
                await db.answer_store.where('attempt_id').equals(attemptId).delete();
                await db.sync_queue.where('attempt_id').equals(attemptId).delete();
                await db.media_manifest.where('attempt_id').equals(attemptId).delete();
                await db.runtime_state.delete(attemptId);
            }
        );
        localStorage.removeItem('cbtHeroAttempt:' + attemptId);
    };

    const finalize = async (reason, timeoutMode = false) => {
        if (finalizing) return false;
        finalizing = true;
        if (button) button.disabled = true;
        runtime.lockInputs(reason === 'TIMEOUT' ? 'Waktu habis · menyimpan' : 'Menyelesaikan ujian...');

        try {
            const drained = await window.CbtExamSync?.drain(timeoutMode ? 15000 : 12000);
            if (!drained) {
                if (timeoutMode) {
                    const state = await runtimeState();
                    state.timeout_pending = true;
                    state.finish_key = state.finish_key || key();
                    await saveRuntimeState(state);
                    runtime.setSyncState(
                        navigator.onLine ? 'Menunggu sinkron jawaban' : 'Waktu habis · menunggu koneksi',
                        'pending'
                    );
                    return false;
                }
                throw new Error('Masih ada jawaban yang belum diterima server. Tunggu koneksi stabil lalu coba Selesai lagi.');
            }

            const state = await runtimeState();
            state.finish_key = state.finish_key || key();
            state.timeout_pending = reason === 'TIMEOUT';
            await saveRuntimeState(state);

            const response = await fetch(runtime.apiBase + '/finalize', {
                method: 'POST',
                credentials: 'same-origin',
                headers: {
                    'Accept': 'application/json',
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.content ?? '',
                    'Idempotency-Key': state.finish_key,
                    ...runtime.clientHeaders()
                },
                body: JSON.stringify({
                    finish_reason: reason,
                    last_server_revision: runtime.serverSyncRevision
                })
            });
            const result = await response.json().catch(() => null);
            if (!response.ok || result?.ok !== true)
                throw new Error(result?.error?.message ?? 'Ujian belum dapat diselesaikan.');

            await cleanup();
            window.location.assign(result.data.redirect);
            return true;
        } catch (error) {
            if (timeoutMode) {
                runtime.setSyncState('Waktu habis · finalisasi tertunda', 'pending');
                const state = await runtimeState();
                state.timeout_pending = true;
                state.finish_key = state.finish_key || key();
                await saveRuntimeState(state);
            } else {
                runtime.unlockInputs();
                if (button) button.disabled = false;
                window.alert(error.message || 'Ujian belum dapat diselesaikan.');
            }
            return false;
        } finally {
            finalizing = false;
        }
    };

    button?.addEventListener('click', async () => {
        if (runtime.inputLocked) return;
        if (!window.confirm('Apakah Anda yakin ingin menyelesaikan ujian?')) return;
        if (!window.confirm('Konfirmasi terakhir: setelah selesai, jawaban tidak dapat diubah.')) return;
        await finalize('SUBMIT', false);
    });

    document.addEventListener('cbt:timeout', () => finalize('TIMEOUT', true));

    window.addEventListener('online', async () => {
        const state = await runtimeState().catch(() => null);
        if (state?.timeout_pending) {
            runtime.lockInputs('Waktu habis · menyelesaikan');
            await finalize('TIMEOUT', true);
        }
    });

    document.addEventListener('cbt:bootstrap-ready', async () => {
        const state = await runtimeState().catch(() => null);
        if (state?.timeout_pending) {
            runtime.lockInputs('Waktu habis · menyelesaikan');
            if (navigator.onLine) finalize('TIMEOUT', true);
        }
    });

    window.CbtExamSubmit = {finalize};
})();
