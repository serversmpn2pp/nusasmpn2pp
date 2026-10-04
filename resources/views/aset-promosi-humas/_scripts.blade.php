<script>
(() => {
    const form = document.querySelector('.aset-page [data-publikasi-upload]');
    if (!form) return;
    const select = document.getElementById('dokumen_humas_id');
    const updateSource = () => {
        const method = form.querySelector('[name="metode"]:checked')?.value;
        form.querySelectorAll('[data-aset-source]').forEach(section => {
            const active = section.dataset.asetSource === method;
            section.hidden = !active;
            section.querySelectorAll('input,select').forEach(input => {
                input.disabled = !active;
                input.required = active && ['berkas', 'dokumen_humas_id', 'tautan'].includes(input.name);
            });
        });
        const note = document.getElementById('catatan_revisi');
        if (note) note.required = method !== 'tetap';
    };
    form.querySelectorAll('[name="metode"]').forEach(input => input.addEventListener('change', updateSource));
    updateSource();
    const search = form.querySelector('[data-dokumen-search]');
    if (!search) return;
    const state = form.querySelector('[data-dokumen-state]');
    let timer, controller;
    search.addEventListener('input', () => {
        clearTimeout(timer);
        controller?.abort();
        timer = setTimeout(async () => {
            controller = new AbortController();
            const signal = controller.signal;
            state.textContent = 'Mencari dokumen...';
            try {
                const url = new URL(search.dataset.dokumenSearch, location.origin);
                url.searchParams.set('cari', search.value);
                const response = await fetch(url, {headers:{Accept:'application/json'}, signal});
                if (!response.ok) throw new Error();
                const data = await response.json();
                if (signal.aborted) return;
                const selected = select.selectedOptions[0];
                const options = [new Option('Pilih dokumen', '')];
                data.dokumen.forEach(doc => options.push(new Option(doc.judul, doc.id)));
                if (selected?.value && !options.some(option => option.value === selected.value)) options.push(new Option(selected.textContent, selected.value));
                const value = select.value;
                select.replaceChildren(...options);
                select.value = value;
                state.textContent = data.dokumen.length ? `${data.dokumen.length} dokumen ditemukan` : 'Tidak ada dokumen yang sesuai.';
            } catch (error) {
                if (error.name !== 'AbortError') state.textContent = 'Pencarian gagal. Coba kembali.';
            }
        }, 300);
    });
})();
</script>
