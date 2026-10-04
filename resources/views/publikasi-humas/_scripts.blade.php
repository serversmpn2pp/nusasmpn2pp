@push('scripts')
<script>
(() => {
    let busy = false;
    let uploading = false;
    window.addEventListener('beforeunload', event => {if (uploading) {event.preventDefault(); event.returnValue = '';}});
    document.querySelectorAll('[data-publikasi-submit]').forEach(form => form.addEventListener('submit', event => {
        if (busy) {event.preventDefault(); return;}
        if (!form.checkValidity()) return;
        busy = true;
        form.setAttribute('aria-busy', 'true');
        form.querySelectorAll('button[type=submit]').forEach(button => {button.disabled = true; button.textContent = 'Memproses...';});
    }));
    document.querySelectorAll('[data-publikasi-upload]').forEach(form => form.addEventListener('submit', event => {
        event.preventDefault();
        if (busy || !form.reportValidity()) return;
        const data = new FormData(form);
        const controls = [...document.querySelectorAll('.publikasi-page button, .publikasi-page input, .publikasi-page select, .publikasi-page textarea')];
        const disabled = controls.map(el => el.disabled);
        const state = form.querySelector('[data-upload-state]');
        const label = state.querySelector('[data-upload-label]');
        const progress = state.querySelector('progress');
        const button = form.querySelector('button[type=submit]');
        const buttonText = button.textContent;
        const noun = form.dataset.uploadNoun || 'konten';
        busy = true;
        uploading = true;
        form.setAttribute('aria-busy', 'true');
        state.hidden = false;
        state.classList.remove('publikasi-upload--error');
        progress.hidden = false;
        progress.value = 0;
        label.textContent = `Sedang mengirim dan menyimpan ${noun}...`;
        button.textContent = 'Menyimpan...';
        controls.forEach(el => {el.disabled = true;});
        const failed = message => {
            busy = false;
            uploading = false;
            form.removeAttribute('aria-busy');
            controls.forEach((el,index) => {el.disabled = disabled[index];});
            button.textContent = buttonText;
            label.textContent = message;
            progress.hidden = true;
            state.classList.add('publikasi-upload--error');
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
            label.textContent = percent < 100 ? `Mengirim ${noun}: ${percent}%` : `Data terkirim. Sedang menyimpan ${noun}...`;
        });
        xhr.addEventListener('load', () => {
            let result;
            try {result = JSON.parse(xhr.responseText);} catch (_) {}
            if (xhr.status >= 200 && xhr.status < 300 && result?.redirect) {
                busy = false;
                uploading = false;
                label.textContent = result.pesan;
                window.location.assign(result.redirect);
            } else failed(Object.values(result?.errors || {}).flat().join(' ') || 'Penyimpanan belum dapat dikonfirmasi. Periksa koneksi atau sesi login, lalu coba kembali.');
        });
        xhr.addEventListener('error', () => failed(`Koneksi terputus. Coba kembali atau buka daftar ${noun} untuk memeriksa hasil penyimpanan.`));
        xhr.addEventListener('timeout', () => failed(`Konfirmasi penyimpanan terlalu lama. Periksa daftar ${noun} sebelum mencoba kembali.`));
        xhr.addEventListener('abort', () => failed('Pengiriman dibatalkan. Silakan coba kembali.'));
        xhr.send(data);
    }));
    const input = document.getElementById('foto');
    const preview = document.querySelector('[data-foto-preview]');
    let urls = [];
    input?.addEventListener('change', () => {
        urls.forEach(url => URL.revokeObjectURL(url));
        urls = [];
        preview.replaceChildren();
        const files = [...input.files];
        input.setCustomValidity(files.length > 5 || files.some(file => file.size > 2 * 1024 * 1024) ? 'Maksimal 5 foto, masing-masing 2 MB.' : '');
        files.slice(0,5).filter(file => ['image/jpeg','image/png','image/webp'].includes(file.type)).forEach(file => {
            const figure = document.createElement('figure');
            figure.className = 'publikasi-photo';
            const img = document.createElement('img');
            const url = URL.createObjectURL(file);
            urls.push(url);
            img.src = url;
            img.alt = file.name;
            const caption = document.createElement('figcaption');
            caption.textContent = file.name;
            figure.append(img,caption);
            preview.append(figure);
        });
    });
})();
</script>
@endpush
