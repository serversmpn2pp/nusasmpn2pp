@push('scripts')
<script>
    document.querySelectorAll('[data-program-form]').forEach(form => {
        const status = form.querySelector('#status_program');
        const update = () => {
            form.querySelector('#capaian').required = status.value === 'selesai';
            form.querySelector('#catatan_evaluasi').required = status.value === 'dibatalkan';
            form.querySelector('#pengurus_komite_humas_id').required = ['berjalan','selesai'].includes(status.value);
        };
        status.addEventListener('change', update);
        update();
        const mulai = form.querySelector('#tanggal_mulai');
        const selesai = form.querySelector('#tanggal_selesai');
        const updateDate = () => { selesai.min = mulai.value || mulai.min; };
        mulai.addEventListener('change', updateDate);
        updateDate();
    });
    document.querySelectorAll('[data-confirm-unlink]').forEach(form => form.addEventListener('submit', event => {
        if (!confirm('Lepas hubungan rapat dari program ini? Agenda dan notulen tetap tersimpan.')) {
            event.preventDefault();
            event.stopImmediatePropagation();
        }
    }, true));
</script>
@endpush
