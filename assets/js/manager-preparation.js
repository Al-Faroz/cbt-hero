(() => {
    'use strict';

    const host = document.querySelector('[data-preparation-api-base]');
    const modalNode = document.getElementById('preparationModal');
    if (!host || !modalNode) return;

    const $ = id => document.getElementById(id);
    const apiBase = host.dataset.preparationApiBase;
    const modal = bootstrap.Modal.getOrCreateInstance(modalNode);
    const state = {
        jadwalId: null, title: '', running: false, status: null,
        assignmentPage: 1, assignmentPages: 1, assignmentSeq: 0, detailSeq: 0,
    };

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

    const cell = value => {
        const node = document.createElement('td');
        node.textContent = value == null ? '' : String(value);
        return node;
    };

    const render = data => {
        state.status = data;
        $('prepTotal').textContent = String(data.total_target ?? 0);
        $('prepReady').textContent = String(data.ready ?? 0);
        $('prepMissing').textContent = String(data.missing ?? 0);
        $('prepStale').textContent = String((data.stale ?? 0) + (data.failed ?? 0));

        const progress = Math.max(0, Math.min(100, Number(data.progress ?? 0)));
        $('prepProgressBar').style.width = progress + '%';
        $('prepProgressBar').textContent = progress + '%';

        const invalidTargets = Number(data.invalid_target_count ?? 0);
        $('prepStatusText').textContent = invalidTargets > 0
            ? invalidTargets + ' target Susulan tidak lagi memenuhi syarat. Batalkan/perbaiki target tersebut pada halaman Susulan sebelum Jadwal dapat START.'
            : (data.can_start
                ? 'Prepared Assignment seluruh target sudah siap dan pemeriksaan peserta lulus.'
                : (data.assignments_ready
                    ? 'Prepared Assignment sudah lengkap, tetapi masih ada pemeriksaan peserta yang perlu diselesaikan sebelum START.'
                    : 'Preparation belum lengkap. Peserta belum boleh START bila assignment-nya belum READY.'));

        const preflight = data.preflight ?? {};
        $('prepPreflightText').textContent = [
            preflight.message ?? '-',
            invalidTargets > 0 ? invalidTargets + ' target Susulan invalid; target ini tidak dibuatkan assignment.' : '',
        ].filter(Boolean).join(' ');
        $('prepPreflightText').className = 'small ' + (preflight.pass && invalidTargets === 0 ? 'text-success' : 'text-warning');

        const pendingWork = Number(data.missing ?? 0) + Number(data.stale ?? 0) + Number(data.failed ?? 0);
        $('prepRun').disabled = state.running || Number(data.total_target ?? 0) < 1 || pendingWork < 1;
        $('prepRebuild').disabled = state.running
            || (Number(data.stale ?? 0) + Number(data.failed ?? 0)) < 1;
    };

    const refresh = async () => {
        if (!state.jadwalId) return;
        const data = await api(apiBase + '/' + state.jadwalId + '/preparation');
        render(data);
    };

    const renderAssignments = data => {
        const body = $('prepAssignmentRows');
        body.replaceChildren();
        for (const item of data.items || []) {
            const row = document.createElement('tr');
            row.append(
                cell([item.nomor_peserta, item.nama, item.rombel].filter(Boolean).join(' · ')),
                cell('#' + item.assignment_seq),
                cell(item.status),
                cell(item.item_count),
                cell(item.used_at ? 'Ya' : 'Belum'),
            );
            const action = document.createElement('td');
            const button = document.createElement('button');
            button.type = 'button';
            button.className = 'btn btn-outline-primary btn-sm';
            button.textContent = 'Detail';
            button.addEventListener('click', () => loadAssignmentDetail(Number(item.id)));
            action.append(button);
            row.append(action);
            body.append(row);
        }
        if (!data.items?.length) {
            const row = document.createElement('tr');
            const empty = cell('Belum ada Prepared Assignment.');
            empty.colSpan = 6;
            empty.className = 'text-center text-secondary py-3';
            row.append(empty);
            body.append(row);
        }
        state.assignmentPage = Number(data.pagination?.page ?? 1);
        state.assignmentPages = Number(data.pagination?.pages ?? 1);
        $('prepAssignmentInfo').textContent = (data.pagination?.filtered ?? 0)
            + ' dari ' + (data.pagination?.total ?? 0) + ' assignment';
        $('prepAssignmentPageInfo').textContent = state.assignmentPage + ' / ' + state.assignmentPages;
        $('prepAssignmentPrev').disabled = state.assignmentPage <= 1;
        $('prepAssignmentNext').disabled = state.assignmentPage >= state.assignmentPages;
    };

    const loadAssignments = async () => {
        if (!state.jadwalId) return;
        const seq = ++state.assignmentSeq;
        const params = new URLSearchParams({
            page: String(state.assignmentPage),
            per_page: '25',
            q: $('prepAssignmentSearch').value.trim(),
        });
        const data = await api(apiBase + '/' + state.jadwalId + '/prepared-assignments?' + params);
        if (seq !== state.assignmentSeq) return;
        renderAssignments(data);
    };

    const loadAssignmentDetail = async assignmentId => {
        const seq = ++state.detailSeq;
        const panel = $('prepAssignmentDetail');
        panel.hidden = false;
        $('prepAssignmentDetailTitle').textContent = 'Memuat detail assignment #' + assignmentId + '...';
        $('prepAssignmentDetailMeta').textContent = '';
        $('prepAssignmentDetailItems').replaceChildren();
        $('prepAssignmentDetailMedia').replaceChildren();
        try {
            const data = await api(apiBase + '/prepared-assignments/' + assignmentId);
            if (seq !== state.detailSeq) return;
            const assignment = data.assignment;
            $('prepAssignmentDetailTitle').textContent = 'Assignment #' + assignment.id
                + ' · ' + (assignment.nomor_peserta || '-') + ' · ' + (assignment.nama || '-');
            $('prepAssignmentDetailMeta').textContent = [
                'Rombel ' + (assignment.rombel || '-'),
                'Versi ' + assignment.assignment_seq,
                'Status ' + assignment.status,
                'Dibuat ' + (assignment.generated_at || '-'),
                'Dipakai ' + (assignment.used_at || 'Belum'),
                'Sumber ' + (assignment.source_version || '-'),
            ].join(' · ');

            const mediaMap = {};
            for (const media of data.media || []) {
                mediaMap[String(media.media_asset_id)] = {
                    kind: media.media_kind,
                    provider: media.provider,
                    url: media.url,
                };
            }

            const itemBody = $('prepAssignmentDetailItems');
            for (const item of data.items || []) {
                const row = document.createElement('tr');
                row.append(
                    cell(item.sequence_no),
                    cell(item.question_type),
                    cell(item.soal_id),
                    cell(item.soal_revision_id),
                    cell(item.max_point),
                );
                const content = document.createElement('td');
                const preview = document.createElement('div');
                preview.className = 'small';
                if (window.CbtQuestionRenderer) {
                    if (item.stimulus_html) preview.append(window.CbtQuestionRenderer.renderContent(item.stimulus_html, mediaMap, 'mb-2'));
                    if (item.question_html) preview.append(window.CbtQuestionRenderer.renderContent(item.question_html, mediaMap, 'fw-semibold'));
                } else {
                    preview.textContent = item.question_html || '';
                }
                content.append(preview);
                if (item.mapping_json) {
                    const details = document.createElement('details');
                    const summary = document.createElement('summary');
                    summary.textContent = 'Mapping Menjodohkan';
                    const pre = document.createElement('pre');
                    pre.className = 'small text-wrap mt-2 mb-0';
                    try { pre.textContent = JSON.stringify(JSON.parse(item.mapping_json), null, 2); }
                    catch (_) { pre.textContent = String(item.mapping_json); }
                    details.append(summary, pre);
                    content.append(details);
                }
                row.append(content);
                itemBody.append(row);
            }
            if (!data.items?.length) {
                const row = document.createElement('tr');
                const empty = cell('Assignment tidak memiliki item.');
                empty.colSpan = 6;
                row.append(empty);
                itemBody.append(row);
            }

            const mediaBox = $('prepAssignmentDetailMedia');
            for (const media of data.media || []) {
                const card = document.createElement('div');
                card.className = 'border rounded p-2 small';
                const label = document.createElement('div');
                label.className = 'fw-semibold';
                label.textContent = media.media_kind + ' #' + media.media_asset_id
                    + (media.is_critical ? ' · Critical' : '');
                const status = document.createElement('div');
                status.className = 'text-secondary';
                status.textContent = media.status + ' · ' + media.storage_type
                    + ' · Prefetch ' + media.prefetch_order;
                card.append(label, status);
                if (media.url) {
                    if (media.media_kind === 'IMAGE') {
                        const image = document.createElement('img');
                        image.src = media.url;
                        image.alt = 'Media assignment #' + media.media_asset_id;
                        image.loading = 'lazy';
                        image.style.maxWidth = '180px';
                        image.style.maxHeight = '120px';
                        image.className = 'd-block rounded mt-2';
                        card.append(image);
                    } else {
                        const link = document.createElement('a');
                        link.href = media.url;
                        link.target = '_blank';
                        link.rel = 'noopener noreferrer';
                        link.textContent = 'Buka media';
                        link.className = 'd-inline-block mt-2';
                        card.append(link);
                    }
                }
                mediaBox.append(card);
            }
            if (!(data.media || []).length) {
                const empty = document.createElement('span');
                empty.className = 'small text-secondary';
                empty.textContent = 'Tidak ada media pada assignment ini.';
                mediaBox.append(empty);
            }
        } catch (error) {
            if (seq === state.detailSeq) {
                $('prepAssignmentDetailTitle').textContent = 'Detail tidak dapat dimuat';
                $('prepAssignmentDetailMeta').textContent = error.message;
            }
        }
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
            await loadAssignments();
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
        state.assignmentPage = 1;
        state.assignmentSeq++;
        state.detailSeq++;
        $('prepAssignmentDetail').hidden = true;
        $('prepAssignmentSearch').value = '';
        $('preparationModalTitle').textContent = 'Preparation · ' + state.title;
        feedback('Memuat status Preparation...');
        modal.show();

        try {
            await refresh();
            await loadAssignments();
            feedback('');
        } catch (error) {
            feedback(error.message, true);
        }
    });

    $('prepRun').addEventListener('click', () => runPreparation(false));
    $('prepRebuild').addEventListener('click', () => runPreparation(true));
    $('prepAssignmentSearchButton').addEventListener('click', () => {
        state.assignmentPage = 1;
        loadAssignments().catch(error => feedback(error.message, true));
    });
    $('prepAssignmentSearch').addEventListener('keydown', event => {
        if (event.key === 'Enter') {
            event.preventDefault();
            state.assignmentPage = 1;
            loadAssignments().catch(error => feedback(error.message, true));
        }
    });
    $('prepAssignmentPrev').addEventListener('click', () => {
        if (state.assignmentPage <= 1) return;
        state.assignmentPage--;
        loadAssignments().catch(error => feedback(error.message, true));
    });
    $('prepAssignmentNext').addEventListener('click', () => {
        if (state.assignmentPage >= state.assignmentPages) return;
        state.assignmentPage++;
        loadAssignments().catch(error => feedback(error.message, true));
    });
    $('prepAssignmentDetailClose').addEventListener('click', () => {
        state.detailSeq++;
        $('prepAssignmentDetail').hidden = true;
    });
})();
