(() => {
    'use strict';

    const app = document.getElementById('examConfirmationApp');
    if (!app) return;

    const button = document.getElementById('examStartButton');
    const agreement = document.getElementById('examAgreement');
    const tokenInput = document.getElementById('examToken');
    const feedback = document.getElementById('examStartFeedback');
    if (!button || !agreement || !feedback) return;

    const csrf = document.querySelector('meta[name="csrf-token"]')?.content ?? '';

    const message = (text, error = false) => {
        feedback.textContent = text;
        feedback.className = 'cbt-inline-feedback' + (text ? (error ? ' is-error' : ' is-info') : '');
    };

    const getClientUuid = () => {
        const key = 'cbtHeroClientUuid';
        let value = localStorage.getItem(key) || '';
        if (/^[A-Za-z0-9._:-]{16,128}$/.test(value)) return value;

        value = window.crypto?.randomUUID?.()
            ?? ('client-' + Date.now() + '-' + Math.random().toString(36).slice(2));
        localStorage.setItem(key, value);
        return value;
    };

    const idempotencyKey = (prefix) =>
        prefix + ':' + (window.crypto?.randomUUID?.()
            ?? (Date.now() + '-' + Math.random().toString(36).slice(2)));

    const saveAttemptIdentity = (data, clientUuid) => {
        const attemptId = Number(data.attempt_id);
        if (!attemptId) return;
        localStorage.setItem('cbtHeroAttempt:' + attemptId, JSON.stringify({
            attempt_id: attemptId,
            client_uuid: clientUuid,
            client_generation: Number(data.client_generation),
            saved_at: new Date().toISOString()
        }));
    };

    const request = async () => {
        if (!agreement.checked) {
            message('Centang konfirmasi identitas sebelum melanjutkan.', true);
            return;
        }

        const tokenEnabled = app.dataset.tokenEnabled === '1';
        const token = tokenInput ? tokenInput.value.trim().toUpperCase() : '';
        if (tokenEnabled && token === '') {
            message('Masukkan Token Ujian.', true);
            tokenInput?.focus();
            return;
        }

        const mode = app.dataset.mode;
        const clientUuid = getClientUuid();
        const examBrowserRequired = app.dataset.examBrowserRequired === '1';
        const proof = typeof window.CBT_EXAM_BROWSER_PROOF === 'string'
            ? window.CBT_EXAM_BROWSER_PROOF
            : '';

        if (examBrowserRequired && proof === '') {
            message('Ujian ini harus dibuka melalui Exam Browser.', true);
            return;
        }

        button.disabled = true;
        message(mode === 'RESUME' ? 'Memeriksa akses ujian...' : 'Menyiapkan Attempt...');

        try {
            let url;
            let payload;
            let key;

            if (mode === 'RESUME') {
                const attemptId = Number(app.dataset.attemptId);
                const generation = Number(app.dataset.clientGeneration);
                if (!attemptId || !generation) throw new Error('Data Attempt tidak lengkap. Muat ulang halaman.');

                url = app.dataset.resumeBase + '/' + attemptId + '/resume';
                key = idempotencyKey('resume');
                payload = {
                    token,
                    client_uuid: clientUuid,
                    client_generation: generation,
                    exam_browser_proof: proof || null
                };
            } else {
                url = app.dataset.startUrl;
                key = idempotencyKey('start');
                payload = {
                    agreement: true,
                    token,
                    client_uuid: clientUuid,
                    exam_browser_proof: proof || null
                };
            }

            const response = await fetch(url, {
                method: 'POST',
                credentials: 'same-origin',
                headers: {
                    'Accept': 'application/json',
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': csrf,
                    'Idempotency-Key': key
                },
                body: JSON.stringify(payload)
            });
            const result = await response.json().catch(() => null);
            if (!response.ok || result?.ok !== true)
                throw new Error(result?.error?.message ?? 'Ujian belum dapat dibuka.');

            saveAttemptIdentity(result.data, clientUuid);
            window.location.assign(result.data.redirect);
        } catch (error) {
            message(error.message || 'Ujian belum dapat dibuka.', true);
            button.disabled = false;
        }
    };

    button.addEventListener('click', request);

    const formatDate = (iso) => {
        const date = new Date(iso);
        if (Number.isNaN(date.getTime())) return '-';
        return new Intl.DateTimeFormat('id-ID', {
            day: '2-digit', month: 'long', year: 'numeric',
            hour: '2-digit', minute: '2-digit', hour12: false
        }).format(date);
    };

    document.querySelectorAll('[data-format-date]').forEach(node => {
        node.textContent = formatDate(node.dataset.formatDate);
    });
    document.querySelectorAll('[data-duration]').forEach(node => {
        const seconds = Number(node.dataset.duration) || 0;
        const minutes = Math.floor(seconds / 60);
        const hours = Math.floor(minutes / 60);
        const remainder = minutes % 60;
        node.textContent = hours > 0
            ? (remainder > 0 ? hours + ' jam ' + remainder + ' menit' : hours + ' jam')
            : minutes + ' menit';
    });
})();
