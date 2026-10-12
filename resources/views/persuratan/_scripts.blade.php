<script>
    (() => {
        const form = document.querySelector('[data-surat-form]');
        if (!form) return;
        const jenis = form.querySelector('#jenis');
        const ubahJenis = () => {
            form.querySelectorAll('[data-surat-jenis]').forEach(bagian => {
                const aktif = bagian.dataset.suratJenis.split(',').includes(jenis.value);
                bagian.hidden = !aktif;
                bagian.querySelectorAll('input,select,textarea').forEach(input => {
                    input.disabled = !aktif;
                    if (input.hasAttribute('data-wajib')) input.required = aktif;
                });
            });
            form.querySelector('[data-label-pihak]').textContent = jenis.value === 'masuk' ? 'Pengirim surat' : 'Penerima / tujuan surat';
        };
        jenis.addEventListener('change', ubahJenis);
        ubahJenis();
        const pilihan = [...form.querySelectorAll('[data-pilihan-pegawai]')];
        const hitung = () => form.querySelector('[data-jumlah-pilihan]').textContent = '(' + pilihan.filter(item => item.querySelector('input').checked).length + ' dipilih)';
        pilihan.forEach(item => item.querySelector('input').addEventListener('change', hitung));
        hitung();
        form.querySelector('#cari_pegawai')?.addEventListener('input', event => {
            const cari = event.target.value.trim().toLocaleLowerCase('id-ID');
            pilihan.forEach(item => item.hidden = !item.textContent.toLocaleLowerCase('id-ID').includes(cari));
            form.querySelector('[data-pencarian-kosong]').hidden = pilihan.some(item => !item.hidden);
        });
        let sibuk = false;
        form.addEventListener('submit', event => {
            if (sibuk) { event.preventDefault(); return; }
            sibuk = true;
            form.querySelectorAll('button[type="submit"]').forEach(button => button.disabled = true);
            form.querySelector('[data-surat-progress]').hidden = false;
            form.setAttribute('aria-busy', 'true');
        });
        document.addEventListener('click', event => {
            if (sibuk && event.target.closest('a')) event.preventDefault();
        });
        window.addEventListener('pageshow', () => {
            sibuk = false;
            form.querySelectorAll('button[type="submit"]').forEach(button => button.disabled = false);
            form.querySelector('[data-surat-progress]').hidden = true;
            form.removeAttribute('aria-busy');
        });
    })();
</script>
