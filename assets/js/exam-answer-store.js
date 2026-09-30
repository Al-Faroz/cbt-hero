(() => {
    'use strict';

    const runtime = window.CbtExamRuntime;
    const db = window.CbtExamDb;
    if (!runtime || !db) return;

    const uuid = () => window.crypto?.randomUUID?.()
        ?? ('mut-' + Date.now() + '-' + Math.random().toString(36).slice(2));

    const save = async (itemId, answerPayload, isFlagged, options = {}) => {
        if (runtime.inputLocked && options.allowLocked !== true) return;
        const attemptId = runtime.attemptId;

        const row = await db.transaction('rw', db.answer_store, db.sync_queue, async () => {
            const current = await db.answer_store.get([attemptId, itemId]);
            const revision = Number(current?.client_revision || 0) + 1;
            const mutationId = uuid();
            const answer = {
                attempt_id: attemptId,
                item_id: itemId,
                answer_payload: answerPayload,
                is_flagged: Boolean(isFlagged),
                client_revision: revision,
                server_revision: Number(current?.server_revision || 0),
                updated_at: Date.now()
            };

            await db.answer_store.put(answer);
            await db.sync_queue.where('attempt_id').equals(attemptId)
                .and(item => Number(item.item_id) === itemId)
                .delete();
            await db.sync_queue.put({
                mutation_id: mutationId,
                attempt_id: attemptId,
                item_id: itemId,
                client_revision: revision,
                answer_payload: answerPayload,
                is_flagged: Boolean(isFlagged),
                answered_at_client: typeof options.answeredAtClient === 'string'
                    ? options.answeredAtClient
                    : new Date(runtime.serverNowMs()).toISOString(),
                client_elapsed_ms: Number.isFinite(Number(options.clientElapsedMs))
                    ? Math.max(0, Number(options.clientElapsedMs))
                    : runtime.elapsedMs(),
                queued_at: Date.now()
            });
            return answer;
        });

        const local = document.getElementById('examLocalStatus');
        if (local) local.textContent = 'Tersimpan di perangkat';
        runtime.setSyncState(navigator.onLine ? 'Belum sinkron' : 'Offline · tersimpan lokal', 'pending');
        runtime.emit('cbt:answer-saved', {itemId, row});
        runtime.emit('cbt:sync-needed');
        return row;
    };

    const get = itemId => db.answer_store.get([runtime.attemptId, itemId]);

    window.CbtExamAnswerStore = {save, get};
})();
