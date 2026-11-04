(() => {
    const form = document.querySelector('[data-tf-target-form]');
    if (!form) return;

    const mode = form.querySelector('[data-tf-target-mode]');
    const choices = form.querySelector('[data-tf-target-choices]');
    const count = form.querySelector('[data-tf-target-count]');
    const target = form.querySelector('#tf-target');
    const refresh = () => {
        choices.hidden = mode.value !== 'tetap';
        choices.disabled = choices.hidden;
        count.textContent = `${choices.querySelectorAll('input:checked').length}/${target.value || 0} dipilih`;
    };
    form.addEventListener('change', refresh);
    target.addEventListener('input', refresh);
    refresh();
})();
