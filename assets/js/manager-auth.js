(() => {
    'use strict';

    const form = document.querySelector('[data-manager-login-form]');
    if (! form) return;

    const button = form.querySelector('[type="submit"]');
    const feedback = form.querySelector('[data-login-feedback]');
    const username = form.querySelector('[name="username"]');
    const password = form.querySelector('[name="password"]');
    const csrf = document.querySelector('meta[name="csrf-token"]')?.content ?? '';

    form.addEventListener('submit', async (event) => {
        event.preventDefault();

        feedback.className = 'cbt-inline-feedback';
        feedback.textContent = '';

        button.disabled = true;
        button.dataset.originalText ??= button.textContent;
        button.textContent = 'Memproses...';

        try {
            const response = await fetch(form.dataset.loginUrl, {
                method: 'POST',
                credentials: 'same-origin',
                headers: {
                    'Accept': 'application/json',
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': csrf
                },
                body: JSON.stringify({
                    username: username.value.trim().toUpperCase(),
                    password: password.value
                })
            });

            const result = await response.json().catch(() => null);

            if (! response.ok || result?.ok !== true) {
                throw new Error(result?.error?.message ?? 'Login gagal.');
            }

            window.location.assign(result.data.redirect);
        } catch (error) {
            feedback.textContent = error.message || 'Login gagal.';
            feedback.className = 'cbt-inline-feedback is-error';
            password.value = '';
            password.focus();
        } finally {
            button.disabled = false;
            button.textContent = button.dataset.originalText || 'Masuk';
        }
    });
})();
