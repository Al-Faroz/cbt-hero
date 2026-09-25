(() => {
    'use strict';

    const form = document.querySelector('[data-participant-ui-form]');
    if (! form) return;

    const feedback = form.querySelector('[data-participant-feedback]');
    const password = form.querySelector('[name="password"]');

    form.addEventListener('submit', (event) => {
        event.preventDefault();

        feedback.textContent =
            'Tampilan login peserta sudah memakai tema final. Engine login peserta diaktifkan pada Phase 1D.';
        feedback.className = 'cbt-inline-feedback is-info';

        if (password) {
            password.value = '';
        }
    });
})();
