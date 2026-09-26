(() => {
    'use strict';

    const root = document.querySelector('[data-exam-list]');
    if (! root) return;

    const apiUrl = root.dataset.apiUrl;
    const confirmationBase = root.dataset.confirmationBase;

    const formatDate = (iso) => {
        if (! iso) return '-';

        const date = new Date(iso);

        if (Number.isNaN(date.getTime())) {
            return '-';
        }

        return new Intl.DateTimeFormat('id-ID', {
            day: '2-digit',
            month: 'short',
            year: 'numeric',
            hour: '2-digit',
            minute: '2-digit',
            hour12: false
        }).format(date);
    };

    const formatDuration = (seconds) => {
        const total = Number(seconds) || 0;
        const minutes = Math.floor(total / 60);

        if (minutes < 60) {
            return `${minutes} menit`;
        }

        const hours = Math.floor(minutes / 60);
        const remainder = minutes % 60;

        return remainder > 0
            ? `${hours} jam ${remainder} menit`
            : `${hours} jam`;
    };

    const statePresentation = (item) => {
        switch (item.ui_state) {
            case 'BISA_DIMULAI':
                return {
                    label: 'Bisa Dimulai',
                    className: 'is-ready'
                };

            case 'LANJUTKAN':
                return {
                    label: 'Lanjutkan',
                    className: 'is-resume'
                };

            case 'SELESAI':
                return {
                    label: 'Selesai',
                    className: 'is-finished'
                };

            default:
                return {
                    label: item.availability === 'START_WINDOW_CLOSED'
                        ? 'Waktu Mulai Berakhir'
                        : item.availability === 'HELD'
                            ? 'Ditahan'
                            : 'Belum Dibuka',
                    className: ''
                };
        }
    };

    const el = (tag, className, text) => {
        const node = document.createElement(tag);

        if (className) {
            node.className = className;
        }

        if (text !== undefined && text !== null) {
            node.textContent = text;
        }

        return node;
    };

    const createMeta = (label, value) => {
        const item = el('div', 'participant-exam-meta-item');
        item.append(
            el('span', 'participant-exam-meta-label', label),
            el('span', 'participant-exam-meta-value', value)
        );

        return item;
    };

    const createCard = (item) => {
        const state = statePresentation(item);

        const card = el(
            'article',
            `participant-exam-card ${state.className}`.trim()
        );

        const body = el('div', 'participant-exam-card-body');
        const topline = el('div', 'participant-exam-topline');

        topline.append(
            el(
                'span',
                `participant-state-badge ${state.className}`.trim(),
                state.label
            ),
            el(
                'span',
                'participant-type-badge',
                item.tipe === 'PSIKOLOGIS' ? 'Psikologis' : 'Akademik'
            )
        );

        if (item.jenis_jadwal === 'SUSULAN') {
            topline.append(
                el('span', 'participant-type-badge', 'Susulan')
            );
        }

        const title = el(
            'h3',
            'participant-exam-title',
            item.nama_ujian || 'Ujian'
        );

        const activity = el(
            'p',
            'participant-exam-activity',
            item.nama_kegiatan || ''
        );

        const meta = el('div', 'participant-exam-meta');

        meta.append(
            createMeta('Mulai', formatDate(item.mulai_at)),
            createMeta('Durasi', formatDuration(item.durasi_seconds))
        );

        if (item.ruang) {
            meta.append(createMeta('Ruang', item.ruang));
        }

        if (item.nomor_peserta) {
            meta.append(
                createMeta('No Peserta', item.nomor_peserta)
            );
        }

        const message = el(
            'p',
            'participant-exam-message',
            item.availability_message || ''
        );

        const actions = el('div', 'participant-exam-actions');

        if (
            item.action_enabled === true
            && ['BISA_DIMULAI', 'LANJUTKAN'].includes(item.ui_state)
        ) {
            const link = el(
                'a',
                'btn btn-cbt-primary flex-grow-1',
                item.ui_state === 'LANJUTKAN'
                    ? 'Lihat & Lanjutkan'
                    : 'Konfirmasi Ujian'
            );

            link.href = `${confirmationBase}/${item.jadwal_id}/konfirmasi`;
            actions.append(link);
        } else {
            const button = el(
                'button',
                'btn btn-outline-secondary flex-grow-1',
                item.ui_state === 'SELESAI'
                    ? 'Ujian Selesai'
                    : 'Belum Dapat Dibuka'
            );

            button.type = 'button';
            button.disabled = true;
            actions.append(button);
        }

        body.append(
            topline,
            title,
            activity,
            meta,
            message,
            actions
        );

        card.append(body);

        return card;
    };

    const renderState = (icon, title, message, retry = false) => {
        root.innerHTML = '';

        const state = el('div', 'participant-list-state');
        const iconBox = el('div', 'participant-list-state-icon');
        iconBox.innerHTML = `<i class="bi ${icon}"></i>`;

        state.append(
            iconBox,
            el('strong', '', title),
            el('span', '', message)
        );

        if (retry) {
            const button = el(
                'button',
                'btn btn-outline-secondary btn-sm mt-3',
                'Coba Lagi'
            );

            button.type = 'button';
            button.addEventListener('click', load);
            state.append(button);
        }

        root.append(state);
    };

    const renderItems = (items) => {
        root.innerHTML = '';

        if (! Array.isArray(items) || items.length === 0) {
            renderState(
                'bi-calendar2-check',
                'Belum ada ujian',
                'Ujian yang menjadi hak Anda akan tampil di halaman ini.'
            );
            return;
        }

        items.forEach((item) => {
            root.append(createCard(item));
        });
    };

    const load = async () => {
        root.innerHTML = `
            <div class="participant-skeleton"></div>
            <div class="participant-skeleton"></div>
        `;

        try {
            const response = await fetch(apiUrl, {
                method: 'GET',
                credentials: 'same-origin',
                headers: {
                    'Accept': 'application/json'
                }
            });

            const result = await response.json().catch(() => null);

            if (response.status === 401) {
                window.location.assign(
                    document.documentElement.dataset.baseUrl || '/'
                );
                return;
            }

            if (! response.ok || result?.ok !== true) {
                throw new Error(
                    result?.error?.message ?? 'Daftar ujian gagal dimuat.'
                );
            }

            renderItems(result.data?.items ?? []);
        } catch (error) {
            renderState(
                'bi-exclamation-triangle',
                'Daftar ujian gagal dimuat',
                error.message || 'Periksa koneksi lalu coba kembali.',
                true
            );
        }
    };

    load();
})();
