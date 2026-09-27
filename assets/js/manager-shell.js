(() => {
    'use strict';

    const STORAGE_KEY = 'cbthero.manager.sidebar.collapsed';
    const desktopQuery = window.matchMedia('(min-width: 992px)');
    const body = document.body;
    const root = document.documentElement;
    const toggle = document.querySelector('[data-manager-sidebar-toggle]');
    const themeToggle = document.querySelector('[data-manager-theme-toggle]');
    const themeQuery = window.matchMedia('(prefers-color-scheme: dark)');
    const themeKey = 'cbthero.manager.theme';

    const updateThemeControl = () => {
        if (! themeToggle) return;
        const dark = root.getAttribute('data-bs-theme') === 'dark';
        const label = dark ? 'Aktifkan tema terang' : 'Aktifkan tema gelap';
        themeToggle.setAttribute('aria-label', label);
        themeToggle.setAttribute('title', label);
        themeToggle.setAttribute('aria-pressed', dark ? 'true' : 'false');
        themeToggle.querySelector('i').className = dark ? 'bi bi-sun' : 'bi bi-moon-stars';
        themeToggle.querySelector('span').textContent = dark ? 'Tema terang' : 'Tema gelap';
    };
    updateThemeControl();
    themeToggle?.addEventListener('click', () => {
        const next = root.getAttribute('data-bs-theme') === 'dark' ? 'light' : 'dark';
        root.setAttribute('data-bs-theme', next);
        try { localStorage.setItem(themeKey, next); } catch (_) { /* storage may be disabled */ }
        updateThemeControl();
    });
    themeQuery.addEventListener?.('change', () => {
        try { if (localStorage.getItem(themeKey)) return; } catch (_) { /* use system preference */ }
        root.setAttribute('data-bs-theme', themeQuery.matches ? 'dark' : 'light');
        updateThemeControl();
    });

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
