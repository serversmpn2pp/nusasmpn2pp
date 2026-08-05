<script>
    (() => {
        const form = document.querySelector('[data-perangkat-ajar-form]');
        const input = form?.querySelector('[data-pdf-input]');
        const pesan = form?.querySelector('[data-pdf-client-error]');
        const status = form?.querySelector('[data-pdf-upload-status]');
        const tombol = form?.querySelector('[data-pdf-submit]');

        if (!form || !input || !pesan || !tombol) return;

        const batasByte = Number(input.dataset.maxBytes || 10485760);
        const batasLabel = input.dataset.maxLabel || '10 MB';
        const labelAwal = tombol.textContent.trim();

        const formatUkuran = (byte) => `${(byte / 1024 / 1024).toLocaleString('id-ID', {
            minimumFractionDigits: 1,
            maximumFractionDigits: 1,
        })} MB`;

        const hapusPesan = () => {
            input.setCustomValidity('');
            input.classList.remove('is-invalid');
            pesan.textContent = '';
            pesan.hidden = true;
        };

        const tampilkanPesan = (teks) => {
            input.setCustomValidity(teks);
            input.classList.add('is-invalid');
            pesan.textContent = teks;
            pesan.hidden = false;
        };

        const validasi = () => {
            const file = input.files?.[0];
            hapusPesan();

            if (!file) return !input.required;

            if (!file.name.toLocaleLowerCase('id-ID').endsWith('.pdf')) {
                tampilkanPesan('File perangkat ajar harus menggunakan format PDF.');
                return false;
            }

            if (file.size > batasByte) {
                tampilkanPesan(
                    `Ukuran file ${formatUkuran(file.size)}, sedangkan batas unggah saat ini ${batasLabel}. Pilih PDF yang lebih kecil.`,
                );
                return false;
            }

            return true;
        };

        const pulihkanTombol = () => {
            tombol.disabled = false;
            tombol.removeAttribute('aria-busy');
            tombol.textContent = labelAwal;
            if (status) status.hidden = true;
        };

        input.addEventListener('change', validasi);
        form.addEventListener('submit', (event) => {
            if (!validasi()) {
                event.preventDefault();
                input.focus();
                form.reportValidity();
                return;
            }

            tombol.disabled = true;
            tombol.setAttribute('aria-busy', 'true');
            tombol.textContent = input.files?.length ? 'Mengunggah PDF...' : 'Menyimpan...';

            if (status) {
                status.textContent = input.files?.length
                    ? 'PDF sedang diunggah. Jangan tutup halaman sampai proses selesai.'
                    : 'Perubahan sedang disimpan.';
                status.hidden = false;
            }
        });
        window.addEventListener('pageshow', pulihkanTombol);
    })();
</script>
