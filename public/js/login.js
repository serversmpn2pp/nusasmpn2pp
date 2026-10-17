(() => {
    const form = document.querySelector('[data-login-form]');
    const button = form?.querySelector('[data-login-submit]');
    const label = button?.querySelector('[data-login-submit-label]');
    const status = form?.querySelector('[data-login-status]');

    if (!form || !button || !label) {
        return;
    }

    const reset = () => {
        button.disabled = false;
        button.classList.remove('is-loading');
        form.removeAttribute('aria-busy');
        label.textContent = 'Masuk';
        if (status) status.textContent = '';
    };

    // A restored browser page must not keep the previous submitting state.
    window.addEventListener('pageshow', reset);
    form.addEventListener('invalid', reset, true);
    form.addEventListener('submit', (event) => {
        if (event.defaultPrevented || !form.checkValidity()) {
            return;
        }
        if (button.disabled) {
            event.preventDefault();
            return;
        }

        button.disabled = true;
        button.classList.add('is-loading');
        form.setAttribute('aria-busy', 'true');
        label.textContent = 'Memproses...';
        if (status) status.textContent = 'Sedang masuk ke NUSA.';
    });
})();
