import { toJpeg, toPng } from 'html-to-image';
import JSZip from 'jszip';

const BATAS_KARTU_GAMBAR = 50;
const RASIO_PIKSEL = 4;

const namaBerkasAman = (nilai, cadangan = 'kartu') => {
    const hasil = String(nilai || '')
        .normalize('NFD')
        .replace(/[\u0300-\u036f]/g, '')
        .toLowerCase()
        .replace(/[^a-z0-9]+/g, '-')
        .replace(/^-+|-+$/g, '')
        .slice(0, 80);

    return hasil || cadangan;
};

const dataUrlKeBase64 = (dataUrl) => dataUrl.split(',', 2)[1];

const unduhBlob = (blob, namaBerkas) => {
    const url = URL.createObjectURL(blob);
    const tautan = document.createElement('a');

    tautan.href = url;
    tautan.download = namaBerkas;
    document.body.appendChild(tautan);
    tautan.click();
    tautan.remove();
    window.setTimeout(() => URL.revokeObjectURL(url), 1000);
};

const tungguGambar = async (akar) => {
    await document.fonts?.ready;

    const daftarGambar = Array.from(akar.querySelectorAll('img'));

    await Promise.all(daftarGambar.map((gambar) => {
        if (gambar.complete && gambar.naturalWidth > 0) {
            return Promise.resolve();
        }

        return new Promise((selesai) => {
            gambar.addEventListener('load', selesai, { once: true });
            gambar.addEventListener('error', selesai, { once: true });
        });
    }));
};

const buatGambar = (elemen, format) => {
    const opsi = {
        backgroundColor: '#ffffff',
        cacheBust: true,
        pixelRatio: RASIO_PIKSEL,
    };

    return format === 'png'
        ? toPng(elemen, opsi)
        : toJpeg(elemen, { ...opsi, quality: 0.96 });
};

document.querySelectorAll('[data-card-export-root]').forEach((akar) => {
    const dialog = document.querySelector('[data-card-export-dialog]');
    const tombolBuka = document.querySelectorAll('[data-card-export-open]');
    const tombolPdf = dialog?.querySelector('[data-card-export-pdf]');
    const tombolGambar = dialog?.querySelectorAll('[data-card-export-image]') || [];
    const tombolTutup = dialog?.querySelectorAll('[data-card-export-close]') || [];
    const panelProgres = dialog?.querySelector('[data-card-export-progress]');
    const teksStatus = dialog?.querySelector('[data-card-export-status]');
    const bilahProgres = dialog?.querySelector('[data-card-export-progress-bar]');
    const semuaTombolAksi = dialog?.querySelectorAll('button') || [];

    if (!dialog) {
        return;
    }

    const aturStatus = (pesan, progres = 0, galat = false) => {
        panelProgres.hidden = false;
        panelProgres.classList.toggle('is-error', galat);
        teksStatus.textContent = pesan;
        bilahProgres.value = progres;
    };

    const aturSibuk = (sibuk) => {
        semuaTombolAksi.forEach((tombol) => {
            tombol.disabled = sibuk;
        });
    };

    tombolBuka.forEach((tombol) => {
        tombol.addEventListener('click', () => {
            panelProgres.hidden = true;
            panelProgres.classList.remove('is-error');
            bilahProgres.value = 0;
            dialog.showModal();
        });
    });

    tombolTutup.forEach((tombol) => {
        tombol.addEventListener('click', () => dialog.close());
    });

    tombolPdf?.addEventListener('click', () => {
        dialog.close();
        window.setTimeout(() => window.print(), 120);
    });

    tombolGambar.forEach((tombol) => {
        tombol.addEventListener('click', async () => {
            const format = tombol.dataset.cardExportImage;
            const ekstensi = format === 'png' ? 'png' : 'jpg';
            const daftarKartu = Array.from(akar.querySelectorAll('[data-card-export-item]'));

            if (daftarKartu.length === 0) {
                aturStatus('Belum ada kartu yang dapat diekspor.', 0, true);
                return;
            }

            if (daftarKartu.length > BATAS_KARTU_GAMBAR) {
                aturStatus(`Ekspor gambar maksimal ${BATAS_KARTU_GAMBAR} kartu sekali proses. Gunakan filter agar jumlah kartu lebih sedikit.`, 0, true);
                return;
            }

            aturSibuk(true);
            aturStatus('Menyiapkan gambar dan jenis huruf...', 2);

            try {
                await tungguGambar(akar);

                const zip = new JSZip();
                const totalGambar = daftarKartu.length * 2;
                let gambarSelesai = 0;

                for (let indeks = 0; indeks < daftarKartu.length; indeks += 1) {
                    const kartu = daftarKartu[indeks];
                    const nama = namaBerkasAman(kartu.dataset.cardName, `kartu-${indeks + 1}`);
                    const nomor = namaBerkasAman(kartu.dataset.cardNumber, String(indeks + 1));
                    const awalan = `${String(indeks + 1).padStart(2, '0')}-${nama}-${nomor}`;

                    for (const sisi of ['depan', 'belakang']) {
                        const elemen = kartu.querySelector(`[data-card-side="${sisi}"]`);

                        if (!elemen) {
                            continue;
                        }

                        aturStatus(`Membuat ${sisi} kartu ${indeks + 1} dari ${daftarKartu.length}...`, Math.round((gambarSelesai / totalGambar) * 85));

                        const dataUrl = await buatGambar(elemen, format);
                        zip.file(`${awalan}-${sisi}.${ekstensi}`, dataUrlKeBase64(dataUrl), { base64: true });
                        gambarSelesai += 1;
                    }
                }

                aturStatus('Mengemas gambar ke dalam ZIP...', 90);

                const blob = await zip.generateAsync(
                    { type: 'blob', compression: 'DEFLATE', compressionOptions: { level: 6 } },
                    (metadata) => {
                        bilahProgres.value = 90 + Math.round(metadata.percent * 0.1);
                    },
                );
                const namaZip = `${namaBerkasAman(akar.dataset.exportName, 'kartu-nusa')}-${format}.zip`;

                unduhBlob(blob, namaZip);
                aturStatus(`${daftarKartu.length} kartu berhasil disimpan dalam ${namaZip}.`, 100);
            } catch (error) {
                console.error(error);
                aturStatus('Gambar belum berhasil dibuat. Muat ulang halaman lalu coba kembali.', 0, true);
            } finally {
                aturSibuk(false);
            }
        });
    });
});
