@push('scripts')<script>
(() => {
    const form = document.querySelector('[data-mou-upload]');
    if (!form) return;
    let busy = false;
    window.addEventListener('beforeunload', event => { if (busy) { event.preventDefault(); event.returnValue = ''; } });
    form.addEventListener('submit', event => {
        event.preventDefault();
        if (busy || !form.reportValidity()) return;
        const state = form.querySelector('[data-mou-upload-state]');
        const label = state.querySelector('[data-mou-upload-label]');
        const progress = state.querySelector('progress');
        const data = new FormData(form);
        const controls = [...form.querySelectorAll('button,input,select,textarea')];
        const disabled = controls.map(control => control.disabled);
        busy = true;
        form.setAttribute('aria-busy', 'true');
        state.hidden = false;
        progress.hidden = false;
        progress.value = 0;
        label.textContent = 'Mengirim berkas MoU...';
        controls.forEach(control => { control.disabled = true; });
        const failed = message => {
            busy = false;
            form.removeAttribute('aria-busy');
            controls.forEach((control, index) => { control.disabled = disabled[index]; });
            label.textContent = message;
            progress.hidden = true;
            state.scrollIntoView({block:'nearest'});
        };
        const xhr = new XMLHttpRequest();
        xhr.open('POST', form.action);
        xhr.setRequestHeader('Accept', 'application/json');
        xhr.setRequestHeader('X-Requested-With', 'XMLHttpRequest');
        xhr.timeout = 180000;
        xhr.upload.addEventListener('progress', event => {
            if (!event.lengthComputable) return;
            const percent = Math.round(event.loaded / event.total * 100);
            progress.value = percent;
            label.textContent = percent < 100 ? `Mengunggah MoU: ${percent}%` : 'Berkas terkirim. Sedang menyimpan dan menghubungkan MoU...';
        });
        xhr.addEventListener('load', () => {
            let result;
            try { result = JSON.parse(xhr.responseText); } catch (_) {}
            if (xhr.status >= 200 && xhr.status < 300 && result?.redirect) {
                busy = false;
                label.textContent = result.pesan;
                window.location.assign(result.redirect);
            } else failed(Object.values(result?.errors || {}).flat().join(' ') || 'Penyimpanan belum dapat dikonfirmasi. Periksa koneksi atau sesi login, lalu coba kembali.');
        });
        xhr.addEventListener('error', () => failed('Koneksi terputus. Coba kembali; berkas yang sudah tersimpan tidak akan digandakan.'));
        xhr.addEventListener('timeout', () => failed('Konfirmasi penyimpanan terlalu lama. Silakan coba kembali.'));
        xhr.send(data);
    });
})();
</script>@endpush
