(() => {
    const form = document.querySelector('[data-pemilih-anggota]');
    if (!form) return;

    const search = form.querySelector('[data-roster-search]');
    const grade = form.querySelector('[data-roster-grade]');
    const classroom = form.querySelector('[data-roster-class]');
    const status = form.querySelector('[data-roster-status]');
    const all = form.querySelector('[data-roster-all]');
    const count = form.querySelector('[data-roster-count]');
    const visibleCount = form.querySelector('[data-roster-visible]');
    const empty = form.querySelector('[data-roster-empty]');
    const normalize = (value) => value.trim().toLocaleLowerCase('id-ID');
    const rows = [...form.querySelectorAll('[data-roster-row]')].map((element) => ({
        element,
        box: element.querySelector('input'),
        search: normalize(element.dataset.search),
        grade: element.dataset.grade,
        classroom: element.dataset.class,
    }));
    let visibleBoxes = [];

    const refresh = () => {
        const keywords = normalize(search.value).split(/\s+/);
        const visible = rows.filter((row) => {
            const matches = keywords.every((word) => row.search.includes(word))
                && (!grade.value || row.grade === grade.value)
                && (!classroom.value || row.classroom === classroom.value)
                && (status.value !== 'selected' || row.box.checked)
                && (status.value !== 'available' || (!row.box.checked && !row.box.disabled));
            row.element.hidden = !matches;
            return matches;
        });
        visibleBoxes = visible.map((row) => row.box).filter((box) => !box.disabled);
        const checked = visibleBoxes.filter((box) => box.checked).length;
        all.disabled = visibleBoxes.length === 0;
        all.checked = checked > 0 && checked === visibleBoxes.length;
        all.indeterminate = checked > 0 && checked < visibleBoxes.length;
        count.textContent = `${rows.filter((row) => row.box.checked).length} terpilih`;
        visibleCount.textContent = `${visible.length} ditampilkan`;
        empty.hidden = visible.length > 0;
    };

    grade.addEventListener('change', () => {
        for (const option of classroom.options) {
            option.hidden = Boolean(grade.value && option.value && option.dataset.grade !== grade.value);
            option.disabled = option.hidden;
        }
        if (classroom.selectedOptions[0]?.disabled) classroom.value = '';
    });
    form.addEventListener('change', (event) => {
        if (event.target === all) {
            for (const box of visibleBoxes) box.checked = all.checked;
        }
        refresh();
    });
    search.addEventListener('input', refresh);
    search.addEventListener('keydown', (event) => {
        if (event.key === 'Enter') event.preventDefault();
    });
    form.querySelector('[data-roster-filters]').hidden = false;
    form.querySelector('[data-roster-bulk]').hidden = false;
    refresh();
})();
