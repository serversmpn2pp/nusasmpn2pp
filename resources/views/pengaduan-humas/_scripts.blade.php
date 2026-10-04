@push('scripts')
<script>
(() => {
    const anonymous = document.querySelector('#anonim');
    const identity = document.querySelector('[data-identitas]');
    const updateIdentity = () => {
        if (!anonymous || !identity) return;
        identity.hidden = anonymous.checked;
        identity.querySelectorAll('input').forEach(input => {input.disabled = anonymous.checked;});
        document.querySelector('#nama_pelapor').required = !anonymous.checked;
    };
    anonymous?.addEventListener('change', updateIdentity);
    updateIdentity();
    document.querySelectorAll('[data-pengaduan-files]').forEach(input => input.addEventListener('change', () => {
        const files = [...input.files];
        input.setCustomValidity(files.length > 3 || files.some(file => file.size > 10 * 1024 * 1024 || !['application/pdf','image/jpeg','image/png','image/webp'].includes(file.type)) ? 'Pilih maksimal 3 PDF/gambar, masing-masing maksimal 10 MB.' : '');
        const list = input.closest('form').querySelector('[data-file-list]');
        list.replaceChildren(...files.map(file => {const item = document.createElement('li'); item.textContent = `${file.name} (${(file.size / 1024 / 1024).toFixed(2)} MB)`; return item;}));
    }));
})();
</script>
@endpush
