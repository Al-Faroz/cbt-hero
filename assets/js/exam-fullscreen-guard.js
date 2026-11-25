(() => {
    'use strict';

    const runtime = window.CbtExamRuntime;
    const root = document.getElementById('examWorkspace');
    if (!runtime || !root) return;

    const required = root.dataset.examBrowserRequired === '1';
    const enabled = !required;
    if (!enabled) return;

    const overlay = document.getElementById('examLockOverlay');
    const title = document.getElementById('examLockTitle');
    const message = document.getElementById('examLockMessage');
    const resumeButton = document.getElementById('examFullscreenResumeButton');
    const exitLink = document.getElementById('examLockExitLink');
    const csrf = document.querySelector('meta[name="csrf-token"]')?.content ?? '';

    let pausePending = false;
    let pauseConfirmed = false;
    let pauseKey = '';
    let busy = false;

    const key = prefix => prefix + ':' + (window.crypto?.randomUUID?.()
        ?? (Date.now() + '-' + Math.random().toString(36).slice(2)));

    const lock = (text = 'Ujian dijeda karena Anda keluar dari mode layar penuh.', confirmed = false) => {
        if (confirmed) pauseConfirmed = true;
        runtime.paused = true;
        runtime.lockInputs('Ujian dijeda · layar penuh diperlukan');
        if (title) title.textContent = 'Ujian dijeda';
        if (message) message.textContent = text;
        if (resumeButton) {
            resumeButton.hidden = false;
            resumeButton.disabled = !pauseConfirmed;
        }
        if (exitLink) exitLink.hidden = true;
        if (overlay) overlay.hidden = false;
        runtime.emit('cbt:attempt-paused', {reason: 'FULLSCREEN_EXIT'});
    };

    const headers = () => ({
        'Accept': 'application/json',
        'Content-Type': 'application/json',
        'X-CSRF-TOKEN': csrf,
        ...runtime.clientHeaders()
    });

    const sendPause = async () => {
        if (pausePending || !pauseKey) return;
        pausePending = true;
        try {
            const response = await fetch(runtime.apiBase + '/fullscreen-pause', {
                method: 'POST',
                credentials: 'same-origin',
                keepalive: true,
                headers: {...headers(), 'Idempotency-Key': pauseKey},
                body: JSON.stringify({
                    fullscreen_active: false,
                    exam_browser_proof: null
                })
            });
            const result = await response.json().catch(() => null);
            if (!response.ok || result?.ok !== true)
                throw new Error(result?.error?.message || 'Status jeda belum tersimpan ke server.');
            pausePending = false;
            pauseConfirmed = true;
            if (resumeButton) resumeButton.disabled = false;
            runtime.setSyncState('Dijeda · layar penuh diperlukan', 'locked');
        } catch (error) {
            pausePending = false;
            if (message) message(error.message + ' Sistem akan mencoba menyimpan status jeda kembali.');
            window.setTimeout(() => {
                if (runtime.paused && !document.fullscreenElement) sendPause();
            }, 1800);
        }
    };

    const beginPause = () => {
        if (runtime.paused || busy) return;
        pauseKey = key('fullscreen-pause');
        lock();
        sendPause();
    };

    const restore = async () => {
        if (busy) return;
        busy = true;
        resumeButton.disabled = true;
        try {
            if (!document.fullscreenElement) {
                if (!document.documentElement.requestFullscreen)
                    throw new Error('Browser tidak mendukung mode layar penuh.');
                await document.documentElement.requestFullscreen();
            }
            if (!document.fullscreenElement)
                throw new Error('Mode layar penuh belum aktif.');

            const response = await fetch(runtime.apiBase + '/fullscreen-resume', {
                method: 'POST',
                credentials: 'same-origin',
                headers: {...headers(), 'Idempotency-Key': key('fullscreen-resume')},
                body: JSON.stringify({fullscreen_active: true})
            });
            const result = await response.json().catch(() => null);
            if (!response.ok || result?.ok !== true)
                throw new Error(result?.error?.message || 'Ujian belum dapat dilanjutkan.');

            runtime.paused = false;
            runtime.unlockInputs();
            runtime.setSyncState('Tersinkron', 'synced');
            runtime.emit('cbt:deadline-updated', {deadline_at: result.data.deadline_at});
            if (overlay) overlay.hidden = true;
            if (resumeButton) resumeButton.hidden = true;
            if (exitLink) exitLink.hidden = false;
            pauseKey = '';
        } catch (error) {
            lock(error.message + ' Aktifkan layar penuh lalu coba kembali.');
        } finally {
            busy = false;
            if (resumeButton) resumeButton.disabled = false;
        }
    };

    resumeButton?.addEventListener('click', restore);

    document.addEventListener('fullscreenchange', () => {
        if (!document.fullscreenElement) {
            beginPause();
        } else if (runtime.paused) {
            lock('Layar penuh aktif. Tekan tombol lanjutkan untuk memvalidasi sesi ujian.');
        }
    });

    document.addEventListener('cbt:bootstrap-ready', event => {
        const paused = event.detail?.data?.attempt?.paused === true;
        if (paused) {
            if (event.detail?.data?.attempt?.pause_reason === 'FULLSCREEN_EXIT') {
                lock('Attempt dijeda karena keluar dari layar penuh. Kembali ke layar penuh untuk melanjutkan ujian.', true);
            } else {
                runtime.hardLock(
                    'Ujian dijeda oleh pengawas',
                    'Ikuti arahan pengawas ujian. Jangan mencoba melanjutkan sendiri.'
                );
                if (resumeButton) resumeButton.hidden = true;
            }
            return;
        }
        if (!document.fullscreenElement) beginPause();
    });

})();
