@push('scripts')<script>
(() => {
    const status = document.querySelector('[data-mou-status]');
    const alasan = document.querySelector('[data-alasan-diakhiri]');
    const aturStatus = () => {
        if (!status || !alasan) return;
        alasan.hidden = status.value !== 'diakhiri';
        alasan.querySelector('textarea').required = status.value === 'diakhiri';
        const aktif = status.value === 'aktif';
        ['tanggal_mulai','tanggal_selesai'].forEach(id => { const input = document.getElementById(id); if (input) input.required = aktif; });
        const dokumen = document.getElementById('dokumen_humas_id');
        if (dokumen) dokumen.required = aktif;
    };
    status?.addEventListener('change', aturStatus);
    aturStatus();
    document.querySelectorAll('form[data-kemitraan-submit]').forEach(form => form.addEventListener('submit', event => {
        if (form.dataset.submitting === '1') { event.preventDefault(); return; }
        if (!form.checkValidity()) return;
        form.dataset.submitting = '1';
        form.setAttribute('aria-busy', 'true');
        form.querySelectorAll('button[type=submit]').forEach(button => { button.disabled = true; button.textContent = 'Menyimpan...'; });
    }));
})();
</script>@endpush
