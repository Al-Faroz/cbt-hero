(() => {
    'use strict';

    const root = document.getElementById('examWorkspace');
    if (!root) return;

    const attemptId = Number(root.dataset.attemptId);
    let identity = null;
    try {
        identity = JSON.parse(localStorage.getItem('cbtHeroAttempt:' + attemptId) || 'null');
    } catch (_) {}

    const runtime = {
        root,
        attemptId,
        apiBase: root.dataset.apiBase,
        clientUuid: String(identity?.client_uuid || ''),
        clientGeneration: Number(identity?.client_generation || 0),
        serverOffsetMs: 0,
        serverSyncRevision: 0,
        bootstrap: null,
        mediaMap: {},
        currentIndex: 0,
        inputLocked: false,
        tabBlocked: false,

        clientHeaders() {
            return {
                'X-CBT-Client-Id': this.clientUuid,
                'X-CBT-Client-Generation': String(this.clientGeneration)
            };
        },

        setBootstrap(data, serverTime = null) {
            this.bootstrap = data;
            this.serverSyncRevision = Number(data?.attempt?.server_sync_revision || 0);
            if (serverTime) {
                const parsed = Date.parse(serverTime);
                if (Number.isFinite(parsed)) this.serverOffsetMs = parsed - Date.now();
            }
            const map = {};
            for (const media of data?.media_manifest || []) {
                map[String(media.id)] = {
                    kind: media.kind,
                    provider: media.provider,
                    url: media.url
                };
            }
            this.mediaMap = map;
        },

        serverNowMs() {
            return Date.now() + this.serverOffsetMs;
        },

        elapsedMs() {
            const start = Date.parse(this.bootstrap?.attempt?.start_at || '');
            if (!Number.isFinite(start)) return 0;
            return Math.max(0, Math.round(this.serverNowMs() - start));
        },

        setSyncState(text, mode = '') {
            const node = document.getElementById('examSyncState');
            if (!node) return;
            node.textContent = text;
            node.dataset.state = mode;
        },

        lockInputs(reason = '') {
            this.inputLocked = true;
            root.classList.add('is-input-locked');
            if (reason) this.setSyncState(reason, 'locked');
        },

        unlockInputs() {
            this.inputLocked = false;
            root.classList.remove('is-input-locked');
        },

        hardLock(title, message) {
            this.inputLocked = true;
            const overlay = document.getElementById('examLockOverlay');
            if (!overlay) return;
            document.getElementById('examLockTitle').textContent = title;
            document.getElementById('examLockMessage').textContent = message;
            overlay.hidden = false;
        },

        emit(name, detail = {}) {
            document.dispatchEvent(new CustomEvent(name, {detail}));
        }
    };

    if (!attemptId || !/^[A-Za-z0-9._:-]{16,128}$/.test(runtime.clientUuid) || runtime.clientGeneration < 1) {
        queueMicrotask(() => runtime.hardLock(
            'Identitas client tidak tersedia',
            'Kembali ke Daftar Ujian lalu buka ujian melalui halaman Konfirmasi.'
        ));
    }

    window.CbtExamRuntime = runtime;
})();
