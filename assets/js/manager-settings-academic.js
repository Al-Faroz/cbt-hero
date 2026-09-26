(() => {
    'use strict';
    const app = document.getElementById('academicSettingsApp');
    if (!app) return;
    const $ = (id) => document.getElementById(id);
    const feedback = (message, error = false) => {
        const node = $('academicFeedback');
        node.textContent = message;
        node.className = 'cbt-inline-feedback mt-3' + (message ? (error ? ' is-error' : ' is-info') : '');
    };
    const request = async (method = 'GET', payload = null) => {
        const headers = {'Accept': 'application/json'};
        if (method !== 'GET') {
            headers['Content-Type'] = 'application/json';
            headers['X-CSRF-TOKEN'] = document.querySelector('meta[name="csrf-token"]').content;
        }
        const response = await fetch(app.dataset.api, {method, credentials: 'same-origin', headers,
            ...(payload === null ? {} : {body: JSON.stringify(payload)})});
        const result = await response.json().catch(() => null);
        if (!response.ok || result?.ok !== true) {
            const fields = result?.error?.fields ?? {};
            throw new Error(Object.values(fields)[0] ?? result?.error?.message ?? 'Permintaan gagal.');
        }
        return result.data.settings;
    };
    request().then((settings) => {
        $('academicYear').value = settings.default_tahun_pelajaran ?? '';
        $('academicSemester').value = settings.default_semester ?? '';
    }).catch((error) => feedback(error.message, true));
    $('academicSettingsForm').addEventListener('submit', async (event) => {
        event.preventDefault();
        const button = $('academicSave'); button.disabled = true;
        feedback('Menyimpan...');
        try {
            const saved = await request('PUT', {
                default_tahun_pelajaran: $('academicYear').value.trim(),
                default_semester: $('academicSemester').value,
            });
            $('academicYear').value = saved.default_tahun_pelajaran;
            $('academicSemester').value = saved.default_semester;
            feedback('Default akademik berhasil disimpan.');
        } catch (error) { feedback(error.message, true); }
        finally { button.disabled = false; }
    });
})();
