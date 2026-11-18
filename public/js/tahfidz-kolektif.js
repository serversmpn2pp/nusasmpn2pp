(() => {
    const filters = document.querySelector('[data-tf-batch-filter]');
    const surahCount = filters?.querySelector('[data-tf-surah-count]');
    filters?.addEventListener('change', () => {
        surahCount.textContent = filters.querySelectorAll('input[name="surah[]"]:checked').length + '/37';
    });

    const form = document.querySelector('[data-tf-batch-form]');
    if (!form) return;
    const cells = [...form.querySelectorAll('[data-tf-batch-cell]')];
    const save = form.querySelector('[data-tf-batch-save]');
    const count = form.querySelector('[data-tf-batch-count]');
    let saving = false;

    function refresh() {
        const filled = cells.filter(cell => cell.value.trim() !== '').length;
        count.textContent = filled + ' setoran baru';
        save.disabled = saving || filled === 0;
    }

    function validate(cell) {
        const raw = cell.value.trim().replace(',', '.');
        const valid = raw === '' || (/^\d{1,3}(?:\.\d{1,2})?$/.test(raw) && Number(raw) <= 100);
        cell.setCustomValidity(valid ? '' : 'Masukkan nilai 0-100 dengan maksimal dua angka desimal.');
    }

    form.addEventListener('input', event => {
        if (!event.target.matches('[data-tf-batch-cell]')) return;
        validate(event.target);
        refresh();
    });
    form.addEventListener('submit', event => {
        if (saving) {
            event.preventDefault();
            return;
        }
        cells.forEach(validate);
        if (!form.reportValidity() || !cells.some(cell => cell.value.trim() !== '')) {
            event.preventDefault();
            return;
        }
        saving = true;
        save.disabled = true;
        count.textContent = 'Menyimpan setoran...';
    });
    form.addEventListener('keydown', event => {
        if (event.key !== 'Enter' || event.isComposing || !event.target.matches('[data-tf-batch-cell]')) return;
        event.preventDefault();
        const index = cells.indexOf(event.target);
        const target = cells[index + (event.shiftKey ? -1 : 1)];
        if (target) {
            target.focus();
            target.select();
        }
    });
    document.querySelector('[data-tf-meeting-close]')?.addEventListener('submit', event => {
        const filled = cells.find(cell => cell.value.trim() !== '');
        if (filled) {
            event.preventDefault();
            event.stopImmediatePropagation();
            window.alert('Masih ada nilai yang belum disimpan. Simpan kolektif terlebih dahulu.');
            filled.focus();
        }
    }, true);
    window.addEventListener('beforeunload', event => {
        if (!saving && cells.some(cell => cell.value.trim() !== '')) {
            event.preventDefault();
            event.returnValue = '';
        }
    });
    window.addEventListener('pageshow', () => {
        saving = false;
        cells.forEach(validate);
        refresh();
    });
    cells.forEach(validate);
    refresh();
})();
