<script>
(() => {
    const editor = document.querySelector('[data-sp-editor]');
    let dirty = false;
    function markDirty() {
        dirty = true;
        document.querySelector('[data-sp-save-state]').textContent = 'Ada perubahan belum disimpan';
    }
    editor?.addEventListener('input', markDirty);
    editor?.addEventListener('change', markDirty);
    editor?.addEventListener('submit', () => {
        const packed = editor.querySelector('[name=nilai_json]');
        if (packed) {
            // A single JSON field avoids PHP max_input_vars truncating large instruments.
            const data = new FormData(editor), rows = [];
            editor.querySelectorAll('[data-sp-score]').forEach(select => {
                const index = select.name.match(/^nilai\[(\d+)\]/)[1];
                rows.push({kelompok: Number(data.get(`nilai[${index}][kelompok]`)), indikator: Number(data.get(`nilai[${index}][indikator]`)),
                    skor: select.value === '' ? null : Number(select.value), catatan: data.get(`nilai[${index}][catatan]`)});
            });
            packed.value = JSON.stringify(rows);
            editor.querySelectorAll('[name^="nilai["]').forEach(input => { input.disabled = true; });
        }
        dirty = false;
    });
    window.addEventListener('beforeunload', event => {
        if (dirty) { event.preventDefault(); event.returnValue = ''; }
    });
    document.querySelectorAll('[data-sp-secondary]').forEach(form => form.addEventListener('submit', event => {
        if (dirty) {
            event.preventDefault();
            alert('Simpan perubahan draf terlebih dahulu sebelum melanjutkan.');
        } else if (form.dataset.spConfirm && ! confirm(form.dataset.spConfirm)) {
            event.preventDefault();
        }
    }));
    const scores = [...document.querySelectorAll('[data-sp-score]')];
    function refreshScores() {
        document.querySelectorAll('[data-sp-score-group]').forEach(group => {
            const fields = [...group.querySelectorAll('[data-sp-score]')];
            const count = fields.filter(input => input.value !== '').length;
            group.querySelector('[data-sp-group-count]').textContent = `${count} / ${fields.length} dinilai`;
        });
        for (const stage of ['perencanaan', 'pelaksanaan']) {
            const fields = scores.filter(input => input.dataset.spScore === stage);
            const count = fields.filter(input => input.value !== '').length;
            const counter = document.querySelector(`[data-sp-count="${stage}"]`);
            if (counter) counter.textContent = `${count} / ${fields.length}`;
        }
    }
    scores.forEach(input => input.addEventListener('change', refreshScores));
    if (scores.length) refreshScores();
})();
</script>
