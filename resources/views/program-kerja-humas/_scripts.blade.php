@push('scripts')
<script>
(() => {
    const status = document.getElementById('status_program');
    const evaluation = document.getElementById('evaluasi');
    const setStatus = () => {if (evaluation) evaluation.required = ['selesai','dibatalkan'].includes(status?.value);};
    status?.addEventListener('change', setStatus);
    setStatus();
    const year = document.getElementById('tahun_pelajaran_id');
    const start = document.getElementById('tanggal_mulai');
    const end = document.getElementById('tanggal_selesai');
    const range = () => {
        if (!start || !end) return;
        const option = year?.selectedOptions[0];
        const minimum = option?.dataset.mulai || start.dataset.minimum || '';
        const maximum = option?.dataset.selesai || start.dataset.maximum || '';
        start.min = minimum;
        start.max = maximum;
        end.min = start.value > minimum ? start.value : minimum;
        end.max = maximum;
    };
    year?.addEventListener('change', range);
    start?.addEventListener('change', range);
    range();
    const file = document.getElementById('berkas');
    file?.addEventListener('change', () => file.setCustomValidity(file.files[0]?.size > 10 * 1024 * 1024 ? 'Maksimal 10 MB per berkas.' : ''));
})();
</script>
@endpush
