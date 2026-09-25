(() => {
    'use strict';

    document.querySelectorAll('[data-disabled-link]').forEach((link) => {
        link.addEventListener('click', (event) => event.preventDefault());
    });

    const csrf = document.querySelector('meta[name="csrf-token"]')?.content ?? '';
    const logoutButtons = document.querySelectorAll('[data-manager-logout]');

    logoutButtons.forEach((button) => {
        button.addEventListener('click', async () => {
            const url = button.dataset.logoutUrl;
            if (! url) return;

            button.disabled = true;

            try {
                const response = await fetch(url, {
                    method: 'POST',
                    credentials: 'same-origin',
                    headers: {
                        'Accept': 'application/json',
                        'X-CSRF-TOKEN': csrf
                    }
                });

                const result = await response.json().catch(() => null);

                if (! response.ok || result?.ok !== true) {
                    throw new Error(result?.error?.message ?? 'Logout gagal.');
                }

                window.location.assign(result.data.redirect);
            } catch (error) {
                window.alert(error.message || 'Logout gagal.');
                button.disabled = false;
            }
        });
    });
})();
