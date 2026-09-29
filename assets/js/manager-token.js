(() => {
    'use strict';
    const app = document.getElementById('tokenApp');
    if (!app) return;
    const $ = id => document.getElementById(id);
    const base = app.dataset.api;
    let state = null;

    const feedback = (text, error = false) => {
        const node = $('tokenFeedback');
        node.textContent = text;
        node.className = 'cbt-inline-feedback' + (text ? (error ? ' is-error' : ' is-info') : '');
    };

    const api = async (url, method = 'GET', payload = null) => {
        const headers = {Accept: 'application/json'};
        if (method !== 'GET') headers['X-CSRF-TOKEN'] = document.querySelector('meta[name="csrf-token"]').content;
        if (payload !== null) headers['Content-Type'] = 'application/json';
        const response = await fetch(url, {
            method, credentials: 'same-origin', headers,
            ...(payload === null ? {} : {body: JSON.stringify(payload)}),
        });
        const result = await response.json().catch(() => null);
        if (!response.ok || result?.ok !== true)
            throw new Error(result?.error?.message ?? 'Permintaan gagal.');
        return result.data;
    };

    const displayDate = value => {
        if (!value) return '-';
        const text = String(value).replace('T', ' ');
        const [date, time] = text.split(' ');
        if (!date || !time) return text;
        const [y, m, d] = date.split('-');
        return d + '/' + m + '/' + y + ' ' + time.slice(0, 5);
    };

    const render = data => {
        state = data;
        $('tokenStateBadge').textContent = data.enabled ? 'AKTIF' : 'NONAKTIF';
        $('tokenStateBadge').className = 'badge fs-6 ' + (data.enabled ? 'text-bg-success' : 'text-bg-secondary');
        $('tokenToggle').textContent = data.enabled ? 'Nonaktifkan Token' : 'Aktifkan Token';
        $('tokenCurrent').textContent = data.current_token || '------';
        $('tokenNext').textContent = data.next_token || '------';
        $('tokenRotatedAt').textContent = data.rotated_at ? 'Dibuat/dirotasi ' + displayDate(data.rotated_at) : 'Belum pernah dibuat.';
        $('tokenNextAt').textContent = data.next_rotate_at
            ? 'Rotasi berikutnya ' + displayDate(data.next_rotate_at)
            : 'Rotasi otomatis tidak aktif.';
        $('tokenAutoRotate').value = data.auto_rotate_minutes == null ? '' : String(data.auto_rotate_minutes);
    };

    const load = async () => {
        feedback('Memuat Token...');
        try {
            render(await api(base));
            feedback('');
        } catch (error) {
            feedback(error.message, true);
        }
    };

    $('tokenToggle').addEventListener('click', async () => {
        if (!state) return;
        const target = !state.enabled;
        if (!window.confirm(target
            ? 'Aktifkan Token? Peserta akan diminta Token pada START dan masuk kembali.'
            : 'Nonaktifkan Token? Peserta tidak lagi diminta Token pada START/masuk kembali.')) return;
        try {
            render(await api(base + '/state', 'PATCH', {enabled: target}));
            feedback('Status Token diperbarui.');
        } catch (error) {
            feedback(error.message, true);
        }
    });

    $('tokenRotate').addEventListener('click', async () => {
        if (!window.confirm('Rotasi Token sekarang? Token lama langsung tidak berlaku untuk START/masuk kembali berikutnya.')) return;
        try {
            render(await api(base + '/rotate', 'POST', {}));
            feedback('Token berhasil dirotasi.');
        } catch (error) {
            feedback(error.message, true);
        }
    });

    $('tokenGenerate').addEventListener('click', async () => {
        if (!window.confirm('Buat Token baru? Token sekarang akan langsung diganti.')) return;
        try {
            render(await api(base + '/generate', 'POST', {}));
            feedback('Token baru berhasil dibuat.');
        } catch (error) {
            feedback(error.message, true);
        }
    });

    $('tokenSaveAuto').addEventListener('click', async () => {
        try {
            const value = $('tokenAutoRotate').value;
            render(await api(base + '/auto-rotate', 'PATCH', {
                auto_rotate_minutes: value === '' ? null : Number(value),
            }));
            feedback('Pengaturan rotasi otomatis disimpan.');
        } catch (error) {
            feedback(error.message, true);
        }
    });

    load();
})();
