(() => {
    const context = document.querySelector('[data-bulk-context]');
    context?.addEventListener('change', event => {
        const name = event.target.name;
        if (name === 'tahun_pelajaran_id') context.elements.kelas_id.value = '';
        if (name === 'tahun_pelajaran_id' || name === 'kelas_id') context.elements.guru_mata_pelajaran_id.value = '';
        context.querySelectorAll('[data-bulk-selected]').forEach(input => { input.disabled = name !== 'urut_tanggal'; });
        context.requestSubmit();
    });

    const form = document.querySelector('[data-bulk-form]');
    if (!form) return;
    const save = form.querySelector('[data-bulk-save]');
    const status = form.querySelector('[data-bulk-status]');
    const error = form.querySelector('[data-bulk-error]');
    const publication = document.querySelector('[data-bulk-publication]');
    const numeric = form.dataset.skala === 'angka';
    const dirty = new Set();
    let saving = false;
    let success = '';

    function canonical(raw) {
        const text = raw.trim();
        if (!text) return '';
        if (!numeric) return text;
        const number = text.replace(',', '.');
        return /^\d{1,3}(?:\.\d{1,2})?$/.test(number) && Number(number) <= 100
            ? Number(number).toFixed(2)
            : text;
    }

    function track(cell) {
        const changed = canonical(cell.value) !== cell.dataset.nilaiAwal;
        if (changed) dirty.add(cell);
        else dirty.delete(cell);
        cell.toggleAttribute('data-changed', changed);
    }

    function refresh() {
        save.disabled = saving || dirty.size === 0;
        status.textContent = [
            saving ? 'Menyimpan...' : success,
            dirty.size ? `${dirty.size} nilai belum disimpan.` : '',
        ].filter(Boolean).join(' ');
    }

    function markError(cell, message = '') {
        const label = document.getElementById(cell.getAttribute('aria-describedby'));
        label.textContent = message;
        label.hidden = !message;
        if (message) cell.setAttribute('aria-invalid', 'true');
        else cell.removeAttribute('aria-invalid');
        cell.classList.toggle('is-invalid', Boolean(message));
    }

    function showError(message) {
        error.textContent = message;
        error.hidden = !message;
    }

    form.addEventListener('input', event => {
        const cell = event.target.closest('[data-bulk-cell]');
        if (!cell) return;
        track(cell);
        markError(cell);
        if (!saving) success = '';
        refresh();
    });
    form.addEventListener('keydown', event => {
        const cell = event.target.closest('input[data-bulk-cell]');
        if (!cell || event.key !== 'Enter' || event.isComposing) return;
        event.preventDefault();
        const row = cell.closest('tr');
        const next = event.shiftKey ? row.previousElementSibling : row.nextElementSibling;
        next?.querySelector(`[data-komponen="${cell.dataset.komponen}"]`)?.focus();
    });
    window.addEventListener('beforeunload', event => {
        if (!dirty.size && !saving) return;
        event.preventDefault();
        event.returnValue = '';
    });
    document.querySelector('[data-bulk-export]')?.addEventListener('click', event => {
        if ((dirty.size || saving) && !window.confirm('Ada perubahan yang belum tersimpan. Ekspor hanya nilai yang sudah tersimpan?')) {
            event.preventDefault();
        }
    });

    form.addEventListener('submit', async event => {
        event.preventDefault();
        if (!dirty.size || saving) return;
        const batch = [...dirty].map(cell => ({ cell, value: cell.value.trim() }));
        batch.forEach(({ cell }) => markError(cell));
        showError('');
        saving = true;
        success = '';
        refresh();
        try {
            const response = await fetch(form.action, {
                method: 'POST',
                headers: {
                    'Accept': 'application/json',
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': form.elements._token.value,
                },
                body: JSON.stringify({
                    guru_mata_pelajaran_id: form.dataset.penugasan,
                    semester: form.dataset.semester,
                    perubahan: batch.map(({ cell, value }) => ({
                        komponen_nilai_id: cell.dataset.komponen,
                        siswa_id: cell.dataset.siswa,
                        nilai: value === '' ? null : value,
                        nilai_awal: cell.dataset.nilaiAwal === '' ? null : cell.dataset.nilaiAwal,
                    })),
                }),
            });
            if (response.status === 419 || response.redirected) {
                showError('Sesi telah berakhir. Masuk kembali sebelum menyimpan.');
                return;
            }
            const payload = await response.json();
            if (!response.ok) {
                let first;
                let otherMessage;
                Object.entries(payload.errors ?? {}).forEach(([key, messages]) => {
                    const index = key.match(/^(?:sel|perubahan)\.(\d+)(?:\.|$)/)?.[1];
                    const cell = batch[index]?.cell;
                    if (cell) {
                        markError(cell, messages[0]);
                        first ??= cell;
                    } else {
                        otherMessage ??= messages[0];
                    }
                });
                showError(otherMessage || (first ? 'Belum ada nilai disimpan. Periksa sel yang ditandai.' : 'Nilai tidak dapat disimpan.'));
                first?.focus();
                return;
            }
            batch.forEach(({ cell, value }) => {
                const saved = canonical(value);
                if (cell.value.trim() === value) cell.value = numeric ? saved.replace('.', ',') : saved;
                cell.dataset.nilaiAwal = saved;
                track(cell);
            });
            if (payload.jumlah > 0) {
                publication.textContent = 'Draf';
                publication.classList.replace('badge-active', 'badge-warning');
            }
            success = payload.message;
        } catch {
            showError('Penyimpanan tidak dapat dikonfirmasi. Periksa koneksi sebelum mencoba lagi.');
        } finally {
            saving = false;
            refresh();
        }
    });
})();
