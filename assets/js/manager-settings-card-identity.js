(() => {
    'use strict';
    const app = document.getElementById('cardIdentityApp');
    if (!app) return;
    const $ = (id) => document.getElementById(id);
    const feedback = (message, error = false) => {
        const node = $('cardIdentityFeedback'); node.textContent = message;
        node.className = 'cbt-inline-feedback mt-2' + (message ? (error ? ' is-error' : ' is-info') : '');
    };
    const show = (settings) => {
        $('cardInstitutionName').value = settings.institution_name ?? '';
        $('cardInstitutionAddress').value = settings.institution_address ?? '';
        $('cardCbtUrl').value = settings.cbt_url ?? '';
        $('cardLogoPreview').src = settings.logo_url + (settings.custom_logo ? '?v=' + Date.now() : '');
        $('cardLogoStatus').textContent = settings.custom_logo ? 'Logo madrasah tersimpan' : 'Logo bawaan CBT-HERO';
        $('cardLogo').value = '';
        $('cardUseDefaultLogo').checked = false;
        $('cardLogo').disabled = false;
    };
    const request = async (method = 'GET', form = null) => {
        const headers = {'Accept': 'application/json'};
        if (method !== 'GET') headers['X-CSRF-TOKEN'] = document.querySelector('meta[name="csrf-token"]').content;
        const response = await fetch(app.dataset.api, {method, credentials: 'same-origin', headers,
            ...(form === null ? {} : {body: form})});
        const result = await response.json().catch(() => null);
        if (!response.ok || result?.ok !== true)
            throw new Error(Object.values(result?.error?.fields ?? {})[0] ?? result?.error?.message ?? 'Permintaan gagal.');
        return result.data.settings;
    };
    request().then(show).catch((error) => feedback(error.message, true));
    $('cardUseDefaultLogo').addEventListener('change', () => {
        const checked = $('cardUseDefaultLogo').checked;
        $('cardLogo').disabled = checked;
        if (checked) $('cardLogo').value = '';
    });
    $('cardIdentityForm').addEventListener('submit', async (event) => {
        event.preventDefault();
        const button = $('cardIdentitySave'); button.disabled = true;
        feedback('Menyimpan identitas...');
        try {
            const form = new FormData();
            form.append('institution_name', $('cardInstitutionName').value.trim());
            form.append('institution_address', $('cardInstitutionAddress').value.trim());
            form.append('cbt_url', $('cardCbtUrl').value.trim());
            form.append('use_default_logo', $('cardUseDefaultLogo').checked ? '1' : '0');
            if ($('cardLogo').files.length) form.append('logo', $('cardLogo').files[0]);
            show(await request('POST', form));
            feedback('Identitas kartu berhasil disimpan.');
        } catch (error) {feedback(error.message, true);}
        finally {button.disabled = false;}
    });
})();
