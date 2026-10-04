<script>
(() => {
    const form = document.querySelector('[data-dashboard-filter]');
    if (!form) return;
    const year = form.querySelector('#tahun_pelajaran_id'), period = form.querySelector('#periode');
    const start = form.querySelector('#tanggal_mulai'), end = form.querySelector('#tanggal_selesai');
    const years = @json($tahun->mapWithKeys(fn ($t) => [$t->id => ['start' => $t->tanggal_mulai->format('Y-m-d'), 'end' => $t->tanggal_selesai->format('Y-m-d')]]));
    const update = () => {
        const custom = period.value === 'kustom';
        year.disabled = custom;
        year.required = !custom;
        start.disabled = end.disabled = !custom;
        start.required = end.required = custom;
        if (!custom && years[year.value]) {
            const range = years[year.value], next = Number(range.start.slice(0, 4)) + 1;
            start.value = period.value === 'genap' && `${next}-01-01` > range.start ? `${next}-01-01` : range.start;
            end.value = period.value === 'ganjil' && `${next - 1}-12-31` < range.end ? `${next - 1}-12-31` : range.end;
        }
        end.min = start.value || '1900-01-01';
    };
    year.addEventListener('change', update);
    period.addEventListener('change', update);
    start.addEventListener('change', () => { end.min = start.value || '1900-01-01'; });
    update();
})();
</script>
