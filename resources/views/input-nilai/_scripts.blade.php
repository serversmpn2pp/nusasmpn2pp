<script>
    (() => {
        const filterForm = document.querySelector('#filter-input-nilai');
        const gradeForm = document.querySelector('[data-grade-form]');
        const fields = [...(gradeForm?.querySelectorAll('[data-grade-value]') || [])];
        const component = document.querySelector('#komponen_nilai_id');
        const controls = [...filterForm.querySelectorAll('select')];
        const initialFilter = new Map(controls.map(field => [field, field.value]));
        const status = gradeForm?.querySelector('[data-grade-unsaved]');
        let leaving = false;
        const countChanges = () => fields.filter(field => field.value !== field.dataset.savedValue).length;
        const refreshStatus = () => {
            if (!status) return;
            const count = countChanges();
            status.hidden = count === 0;
            status.textContent = `${count} perubahan belum disimpan`;
        };
        const confirmLeave = () => leaving || countChanges() === 0 || window.confirm('Ada nilai atau catatan yang belum disimpan. Tinggalkan perubahan dan lanjutkan?');
        const restoreFilter = () => initialFilter.forEach((value, field) => { field.value = value; });
        const resetBusy = () => {
            filterForm.removeAttribute('aria-busy');
            filterForm.querySelector('[data-filter-state]').hidden = true;
        };
        fields.forEach(field => {
            field.addEventListener('input', refreshStatus);
            field.addEventListener('change', refreshStatus);
        });
        refreshStatus();

        filterForm.querySelectorAll('[data-grade-filter]').forEach(field => field.addEventListener('change', () => {
            if (!confirmLeave()) { restoreFilter(); return; }
            leaving = true;
            if (field.name === 'tahun_pelajaran_id') document.querySelector('#kelas_id').value = '';
            const selected = component.selectedOptions[0];
            const match = (name, key, all) => !filterForm.elements[name].value || filterForm.elements[name].value === all || filterForm.elements[name].value === selected?.dataset[key];
            if (!match('tahun_pelajaran_id', 'tahun', '') || !match('kelas_id', 'kelas', '') || !match('semester', 'semester', 'semua') || !match('jenis_komponen', 'jenis', 'semua')) component.value = '';
            filterForm.requestSubmit();
        }));

        document.addEventListener('submit', event => {
            const form = event.target;
            if (form === gradeForm) {
                leaving = true;
                return;
            }
            if (!confirmLeave()) {
                event.preventDefault();
                event.stopImmediatePropagation();
                if (form === filterForm) restoreFilter();
                return;
            }
            leaving = true;
            if (form === filterForm) {
                filterForm.setAttribute('aria-busy', 'true');
                filterForm.querySelector('[data-filter-state]').hidden = false;
            }
        }, true);
        document.addEventListener('click', event => {
            const link = event.target.closest('a[href]');
            if (!link || link.target === '_blank' || link.hasAttribute('download') || event.ctrlKey || event.metaKey || event.shiftKey || event.altKey || event.button !== 0) return;
            const href = link.getAttribute('href');
            if (!href || href.startsWith('#') || !/^https?:$/.test(new URL(link.href).protocol)) return;
            if (!confirmLeave()) { event.preventDefault(); event.stopImmediatePropagation(); return; }
            leaving = true;
        }, true);
        window.addEventListener('beforeunload', event => {
            if (leaving || countChanges() === 0) return;
            event.preventDefault();
            event.returnValue = '';
        });
        window.addEventListener('pageshow', () => { leaving = false; resetBusy(); refreshStatus(); });
    })();
</script>
