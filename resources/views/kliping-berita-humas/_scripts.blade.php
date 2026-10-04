@push('scripts')
<script>
(() => {
    const form = document.querySelector('.kliping-page [data-publikasi-upload]');
    if (!form) return;
    const updateSource = () => {
        const method = form.querySelector('[name="metode"]:checked')?.value;
        form.querySelectorAll('[data-kliping-source]').forEach(section => {
            const active = section.dataset.klipingSource === method;
            section.hidden = !active;
            section.querySelectorAll('input,select').forEach(input => {
                input.disabled = !active;
                input.required = active && ['berkas','dokumen_humas_id'].includes(input.name);
            });
        });
        form.querySelector('#tautan').required = ['tanpa','hapus'].includes(method) || (method === 'tetap' && form.dataset.buktiTersimpan !== '1');
        const current = form.querySelector('[data-bukti-lama]');
        if (current) current.hidden = method !== 'tetap';
    };
    form.querySelectorAll('[name="metode"]').forEach(input => input.addEventListener('change', updateSource));
    updateSource();
    const fileInput = form.querySelector('#berkas');
    const preview = form.querySelector('[data-kliping-preview]');
    let previewUrl;
    fileInput?.addEventListener('change', () => {
        if (previewUrl) URL.revokeObjectURL(previewUrl);
        preview.replaceChildren();
        const file = fileInput.files[0];
        fileInput.setCustomValidity(file && (file.size > 20 * 1024 * 1024 || !['application/pdf','image/jpeg','image/png','image/webp'].includes(file.type)) ? 'Pilih bukti PDF/gambar, maksimal 20 MB.' : '');
        if (file && ['image/jpeg','image/png','image/webp'].includes(file.type)) {
            const img = document.createElement('img');
            previewUrl = URL.createObjectURL(file);
            img.src = previewUrl; img.alt = file.name; img.className = 'kliping-preview';
            preview.append(img);
        }
    });
    const search = form.querySelector('[data-dokumen-search]');
    if (!search) return;
    const select = form.querySelector('#dokumen_humas_id');
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
                const options = [new Option('Pilih dokumen','')];
                data.dokumen.forEach(doc => options.push(new Option(doc.judul, doc.id)));
                if (selected?.value && !options.some(option => option.value === selected.value)) options.push(new Option(selected.textContent,selected.value));
                const value = select.value;
                select.replaceChildren(...options); select.value = value;
                state.textContent = data.dokumen.length ? `${data.dokumen.length} dokumen ditemukan` : 'Tidak ada bukti yang sesuai.';
            } catch (error) {
                if (error.name !== 'AbortError') state.textContent = 'Pencarian gagal. Coba kembali.';
            }
        }, 300);
    });
})();
</script>
@endpush
