(() => {
    'use strict';

    const host = document.querySelector('[data-preparation-api-base]');
    const modalNode = document.getElementById('preparationModal');
    if (!host || !modalNode) return;

    const $ = id => document.getElementById(id);
    const apiBase = host.dataset.preparationApiBase;
    const modal = bootstrap.Modal.getOrCreateInstance(modalNode);
    const state = {jadwalId: null, title: '', running: false, status: null};

    const feedback = (text, error = false) => {
        const node = $('prepFeedback');
        node.textContent = text;
        node.className = 'cbt-inline-feedback' + (text ? (error ? ' is-error' : ' is-info') : '');
    };

    const api = async (url, method = 'GET', payload = null, extraHeaders = {}) => {
        const headers = {Accept: 'application/json', ...extraHeaders};
        if (method !== 'GET') {
            headers['X-CSRF-TOKEN'] = document.querySelector('meta[name="csrf-token"]').content;
        }
        if (payload !== null) headers['Content-Type'] = 'application/json';
        const response = await fetch(url, {
            method,
            credentials: 'same-origin',
            headers,
            ...(payload === null ? {} : {body: JSON.stringify(payload)}),
        });
        const result = await response.json().catch(() => null);
        if (!response.ok || result?.ok !== true)
            throw new Error(result?.error?.message ?? 'Permintaan Preparation gagal.');
        return result.data;
    };

    const uniqueKey = prefix =>
        prefix + ':' + (window.crypto?.randomUUID?.()
            ?? (Date.now() + '-' + Math.random().toString(36).slice(2)));

    const render = data => {
        state.status = data;
        $('prepTotal').textContent = String(data.total_target ?? 0);
        $('prepReady').textContent = String(data.ready ?? 0);
        $('prepMissing').textContent = String(data.missing ?? 0);
        $('prepStale').textContent = String((data.stale ?? 0) + (data.failed ?? 0));

        const progress = Math.max(0, Math.min(100, Number(data.progress ?? 0)));
        $('prepProgressBar').style.width = progress + '%';
        $('prepProgressBar').textContent = progress + '%';

        $('prepStatusText').textContent = data.can_start
            ? 'Prepared Assignment seluruh target sudah siap dan pemeriksaan peserta lulus.'
            : (data.assignments_ready
                ? 'Prepared Assignment sudah lengkap, tetapi masih ada pemeriksaan peserta yang perlu diselesaikan sebelum START.'
                : 'Preparation belum lengkap. Peserta belum boleh START bila assignment-nya belum READY.');

        const preflight = data.preflight ?? {};
        $('prepPreflightText').textContent = preflight.message ?? '-';
        $('prepPreflightText').className = 'small ' + (preflight.pass ? 'text-success' : 'text-warning');

        $('prepRun').disabled = state.running || Number(data.total_target ?? 0) < 1 || Boolean(data.assignments_ready);
        $('prepRebuild').disabled = state.running
            || (Number(data.stale ?? 0) + Number(data.failed ?? 0)) < 1;
    };

    const refresh = async () => {
        if (!state.jadwalId) return;
        const data = await api(apiBase + '/' + state.jadwalId + '/preparation');
        render(data);
    };

    const runPreparation = async rebuild => {
        if (!state.jadwalId || state.running) return;
        state.running = true;
        $('prepRun').disabled = true;
        $('prepRebuild').disabled = true;
        feedback(rebuild ? 'Membangun ulang assignment yang terdampak...' : 'Menyiapkan assignment peserta...');

        try {
            let rounds = 0;
            let hasMore = true;
            while (hasMore) {
                rounds++;
                if (rounds > 1000) throw new Error('Preparation dihentikan karena jumlah putaran tidak wajar.');

                const endpoint = rebuild
                    ? apiBase + '/' + state.jadwalId + '/prepare/rebuild'
                    : apiBase + '/' + state.jadwalId + '/prepare';
                const data = await api(endpoint, 'POST', {
                    scope: 'ALL',
                    force_rebuild: false,
                }, {
                    'Idempotency-Key': uniqueKey(rebuild ? 'prep-rebuild' : 'prep'),
                });

                render(data);
                hasMore = Boolean(data.has_more);
                feedback(
                    'Diproses ' + Number(data.processed_count ?? 0)
                    + ' peserta pada putaran ini. Siap '
                    + Number(data.ready ?? 0) + '/' + Number(data.total_target ?? 0) + '.'
                );

                if (Number(data.processed_count ?? 0) === 0 && hasMore)
                    throw new Error('Preparation tidak bergerak. Periksa status assignment.');
            }

            await refresh();
            feedback(rebuild ? 'Rebuild Preparation selesai.' : 'Preparation selesai.');
            document.dispatchEvent(new CustomEvent('cbt:preparation-updated', {
                detail: {jadwalId: state.jadwalId, status: state.status},
            }));
        } catch (error) {
            feedback(error.message, true);
        } finally {
            state.running = false;
            if (state.status) render(state.status);
        }
    };

    document.addEventListener('click', async event => {
        const button = event.target.closest('.js-preparation');
        if (!button) return;
        state.jadwalId = Number(button.dataset.jadwalId);
        state.title = button.dataset.title || ('Jadwal #' + state.jadwalId);
        $('preparationModalTitle').textContent = 'Preparation · ' + state.title;
        feedback('Memuat status Preparation...');
        modal.show();

        try {
            await refresh();
            feedback('');
        } catch (error) {
            feedback(error.message, true);
        }
    });

    $('prepRun').addEventListener('click', () => runPreparation(false));
    $('prepRebuild').addEventListener('click', () => runPreparation(true));
})();
