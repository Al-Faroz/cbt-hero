(() => {
    'use strict';

    const STORAGE_KEY = 'cbthero.manager.sidebar.collapsed';
    const desktopQuery = window.matchMedia('(min-width: 992px)');
    const body = document.body;
    const root = document.documentElement;
    const toggle = document.querySelector('[data-manager-sidebar-toggle]');

    const readCollapsedPreference = () => {
        try {
            return localStorage.getItem(STORAGE_KEY) === '1';
        } catch (_) {
            return false;
        }
    };

    const writeCollapsedPreference = (collapsed) => {
        try {
            localStorage.setItem(STORAGE_KEY, collapsed ? '1' : '0');
        } catch (_) {
            //
        }
    };

    const applySidebarState = (collapsed, persist = true) => {
        root.classList.remove('manager-sidebar-collapsed-preset');

        if (! desktopQuery.matches) {
            body.classList.remove('manager-sidebar-collapsed');
            return;
        }

        body.classList.toggle('manager-sidebar-collapsed', collapsed);

        if (toggle) {
            toggle.setAttribute('aria-expanded', collapsed ? 'false' : 'true');
            toggle.setAttribute(
                'aria-label',
                collapsed ? 'Perbesar sidebar' : 'Minimalkan sidebar'
            );
            toggle.setAttribute(
                'title',
                collapsed ? 'Perbesar sidebar' : 'Minimalkan sidebar'
            );
        }

        if (persist) {
            writeCollapsedPreference(collapsed);
        }
    };

    applySidebarState(readCollapsedPreference(), false);

    toggle?.addEventListener('click', () => {
        applySidebarState(
            ! body.classList.contains('manager-sidebar-collapsed')
        );
    });

    /*
     * Jika kategori diklik ketika sidebar sedang mini, buka sidebar dahulu,
     * kemudian Bootstrap tetap menangani collapse submenu.
     */
    document.querySelectorAll('.manager-nav-toggle').forEach((button) => {
        button.addEventListener('click', () => {
            if (
                desktopQuery.matches
                && body.classList.contains('manager-sidebar-collapsed')
            ) {
                applySidebarState(false);
            }
        });
    });

    desktopQuery.addEventListener?.('change', () => {
        applySidebarState(readCollapsedPreference(), false);
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
