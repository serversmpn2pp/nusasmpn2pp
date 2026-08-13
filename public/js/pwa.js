(() => {
    let permintaanPemasangan = null;
    const berjalanSebagaiAplikasi = window.matchMedia('(display-mode: standalone)').matches
        || window.navigator.standalone === true;

    const tombolPemasangan = () => [...document.querySelectorAll('[data-pwa-install]')];
    const wadahPemasangan = () => [...document.querySelectorAll('[data-pwa-install-container]')];
    const statusPemasangan = () => [...document.querySelectorAll('[data-pwa-install-status]')];

    const tampilkanPilihanPemasangan = (ditampilkan) => {
        tombolPemasangan().forEach((tombol) => {
            tombol.hidden = !ditampilkan;
        });
        wadahPemasangan().forEach((wadah) => {
            wadah.hidden = !ditampilkan;
        });
    };

    const perbaruiStatus = (pesan) => {
        statusPemasangan().forEach((elemen) => {
            elemen.textContent = pesan;
            elemen.hidden = pesan === '';
        });
    };

    if ('serviceWorker' in navigator) {
        window.addEventListener('load', () => {
            navigator.serviceWorker.register('/service-worker.js').catch(() => {
                // NUSA tetap dapat digunakan jika service worker tidak tersedia.
            });
        });
    }

    if (berjalanSebagaiAplikasi) {
        tampilkanPilihanPemasangan(false);
        return;
    }

    window.addEventListener('beforeinstallprompt', (event) => {
        event.preventDefault();
        permintaanPemasangan = event;
        tampilkanPilihanPemasangan(true);
    });

    document.addEventListener('click', async (event) => {
        const tombol = event.target.closest('[data-pwa-install]');

        if (!tombol || !permintaanPemasangan) {
            return;
        }

        tombolPemasangan().forEach((item) => {
            item.disabled = true;
        });
        perbaruiStatus('Menyiapkan pemasangan NUSA...');

        try {
            await permintaanPemasangan.prompt();
            const pilihan = await permintaanPemasangan.userChoice;

            if (pilihan.outcome === 'accepted') {
                perbaruiStatus('NUSA sedang dipasang di perangkat ini.');
            } else {
                perbaruiStatus('Pemasangan dibatalkan. NUSA tetap dapat digunakan melalui browser.');
            }
        } finally {
            permintaanPemasangan = null;
            tampilkanPilihanPemasangan(false);
        }
    });

    window.addEventListener('appinstalled', () => {
        permintaanPemasangan = null;
        tampilkanPilihanPemasangan(false);
        perbaruiStatus('NUSA berhasil dipasang. Ikonnya sudah tersedia di layar utama.');
    });
})();
