@push('scripts')
<script>
(() => {
    const tujuan = document.querySelector('[data-tujuan]');
    const tujuanLain = document.querySelector('[data-tujuan-lain]');
    const aturTujuan = () => {
        if (!tujuan || !tujuanLain) return;
        tujuanLain.hidden = Boolean(tujuan.value);
        const input = tujuanLain.querySelector('input');
        input.required = !tujuan.value;
        input.disabled = Boolean(tujuan.value);
    };
    tujuan?.addEventListener('change', aturTujuan);
    aturTujuan();
    const periode = document.querySelector('[data-periode]');
    const aturPeriode = () => {
        document.querySelectorAll('[data-period-fields]').forEach(group => {
            const aktif = group.dataset.periodFields === periode?.value;
            group.hidden = !aktif;
            group.querySelectorAll('input,select').forEach(input => { input.disabled = !aktif; input.required = aktif; });
        });
    };
    periode?.addEventListener('change', aturPeriode);
    aturPeriode();

    let sibuk = false;
    window.addEventListener('beforeunload', event => {
        if (!sibuk) return;
        event.preventDefault();
        event.returnValue = '';
    });
    document.querySelectorAll('form[data-tamu-upload]').forEach(form => {
        form.addEventListener('submit', event => {
            if (!window.XMLHttpRequest || !window.FormData) return;
            event.preventDefault();
            if (sibuk || !form.reportValidity()) return;
            const state = form.querySelector('[data-upload-state]');
            const label = state.querySelector('[data-upload-label]');
            const progress = state.querySelector('progress');
            const controls = [...form.querySelectorAll('button,input,select,textarea')];
            const data = new FormData(form);
            const disabled = controls.map(control => control.disabled);
            sibuk = true;
            form.setAttribute('aria-busy', 'true');
            state.hidden = false;
            state.classList.remove('tamu-upload-error');
            progress.hidden = false;
            progress.value = 0;
            label.textContent = 'Mengirim data dan berkas...';
            controls.forEach(control => { control.disabled = true; });
            const gagal = pesan => {
                sibuk = false;
                form.removeAttribute('aria-busy');
                controls.forEach((control, i) => { control.disabled = disabled[i]; });
                state.classList.add('tamu-upload-error');
                label.textContent = pesan;
                progress.hidden = true;
                state.scrollIntoView({block:'nearest'});
            };
            const xhr = new XMLHttpRequest();
            xhr.open(form.method.toUpperCase(), form.action);
            xhr.setRequestHeader('Accept', 'application/json');
            xhr.setRequestHeader('X-Requested-With', 'XMLHttpRequest');
            xhr.timeout = 180000;
            xhr.upload.addEventListener('progress', event => {
                if (!event.lengthComputable) return;
                const persen = Math.round(event.loaded / event.total * 100);
                progress.value = persen;
                label.textContent = persen < 100 ? `Mengunggah berkas: ${persen}%` : 'Unggahan terkirim. Sedang menyimpan kunjungan dan berkas...';
            });
            xhr.addEventListener('load', () => {
                let response;
                try { response = JSON.parse(xhr.responseText); } catch (_) {}
                if (xhr.status >= 200 && xhr.status < 300 && response?.redirect) {
                    sibuk = false;
                    label.textContent = response.pesan || 'Data berhasil disimpan.';
                    window.location.assign(response.redirect);
                } else {
                    const pesan = Object.values(response?.errors || {}).flat().join(' ');
                    gagal(pesan || (xhr.status === 401 || xhr.status === 419 ? 'Sesi telah berakhir. Muat ulang halaman dan masuk kembali.' : 'Penyimpanan belum dapat dikonfirmasi. Periksa koneksi dan coba kembali.'));
                }
            });
            xhr.addEventListener('error', () => gagal('Koneksi terputus. Periksa koneksi dan coba kembali; data yang sudah tersimpan tidak akan digandakan.'));
            xhr.addEventListener('timeout', () => gagal('Konfirmasi penyimpanan terlalu lama. Periksa koneksi dan coba kembali.'));
            xhr.addEventListener('abort', () => gagal('Unggahan terhenti. Silakan coba kembali.'));
            xhr.send(data);
        });
    });
})();
</script>
@endpush
