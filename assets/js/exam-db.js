(() => {
    'use strict';
    if (!window.Dexie) return;

    const db = new Dexie('CBTHeroExam');
    db.version(1).stores({
        attempt_meta: 'attempt_id, updated_at',
        question_cache: '[attempt_id+item_id], attempt_id, sequence_no',
        answer_store: '[attempt_id+item_id], attempt_id, item_id, client_revision, server_revision',
        sync_queue: 'mutation_id, attempt_id, item_id, client_revision, queued_at',
        media_manifest: '[attempt_id+media_id], attempt_id, media_id, critical, prefetch_order',
        runtime_state: 'attempt_id'
    });

    window.CbtExamDb = db;
})();
