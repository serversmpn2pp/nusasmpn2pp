@props([
    'id' => 'foto',
    'name' => 'foto',
    'label' => 'Foto',
    'fotoUrl' => null,
    'inisial' => 'F',
    'alt' => 'Pratinjau foto',
    'uploadUrl' => null,
    'variant' => 'form',
])

@php
    $punyaFoto = filled($fotoUrl);
    $unggahOtomatis = filled($uploadUrl);
    $statusId = $id . '-status';
@endphp

<div
    class="avatar-upload foto-uploader {{ $variant === 'profile' ? 'foto-uploader-profile' : '' }}"
    data-photo-uploader
    data-upload-url="{{ $uploadUrl }}"
    data-existing-url="{{ $fotoUrl }}"
    data-csrf="{{ csrf_token() }}"
    data-max-source-bytes="{{ 20 * 1024 * 1024 }}"
    data-target-bytes="{{ 1400 * 1024 }}"
>
    <div class="avatar avatar-lg foto-uploader-preview">
        <img
            data-photo-preview
            src="{{ $fotoUrl ?: '' }}"
            alt="{{ $alt }}"
            @if (! $punyaFoto) hidden @endif
        >
        <span data-photo-placeholder @if ($punyaFoto) hidden @endif>{{ $inisial }}</span>
        <span class="foto-uploader-progress" data-photo-progress hidden>Memproses</span>
    </div>

    <div class="foto-uploader-controls">
        <label for="{{ $id }}" class="form-label">{{ $label }}</label>
        <input
            id="{{ $id }}"
            name="{{ $name }}"
            type="file"
            accept="image/png,image/jpeg,image/webp"
            class="file-input"
            aria-describedby="{{ $statusId }}"
        >
        @error($name)
            <p class="error-text">{{ $message }}</p>
        @enderror
        <p class="help-text">
            JPG, PNG, atau WebP hingga 20 MB akan diperkecil otomatis.
            {{ $unggahOtomatis ? 'Perubahan foto langsung tersimpan.' : 'Foto disimpan bersama data utama.' }}
        </p>
        <p id="{{ $statusId }}" class="foto-upload-status" data-photo-status role="status" aria-live="polite" hidden></p>
    </div>
</div>

@once
    <style>
        .foto-uploader-preview {
            position: relative;
            transition: opacity .15s ease;
        }

        .foto-uploader-preview [hidden],
        .foto-upload-status[hidden] {
            display: none !important;
        }

        .foto-uploader-progress {
            position: absolute;
            right: 7px;
            bottom: 7px;
            border-radius: 6px;
            background: rgba(21, 71, 122, .92);
            padding: 4px 7px;
            color: #fff;
            font-size: .68rem;
            font-weight: 800;
        }

        .foto-uploader.is-busy .foto-uploader-preview {
            opacity: .72;
        }

        .foto-upload-status {
            margin: 7px 0 0;
            border-radius: 6px;
            padding: 8px 10px;
            font-size: .78rem;
            font-weight: 750;
            line-height: 1.4;
        }

        .foto-upload-status.is-info {
            background: #e8f0f8;
            color: #0f355c;
        }

        .foto-upload-status.is-success {
            background: #dcfce7;
            color: #166534;
        }

        .foto-upload-status.is-error {
            background: #fee2e2;
            color: #991b1b;
        }

        .foto-uploader-profile {
            width: 100%;
        }

        .foto-uploader-profile .foto-uploader-controls {
            width: 100%;
            text-align: left;
        }
    </style>
@endonce

@once
    @push('scripts')
        <script>
            (() => {
                const tipeFoto = ['image/jpeg', 'image/png', 'image/webp'];
                const lebarMaksimal = 1200;
                const tinggiMaksimal = 1600;

                const ukuranTeks = (jumlahByte) => {
                    if (jumlahByte >= 1024 * 1024) {
                        return `${(jumlahByte / (1024 * 1024)).toFixed(1)} MB`;
                    }

                    return `${Math.max(1, Math.round(jumlahByte / 1024))} KB`;
                };

                const muatGambar = (file) => new Promise((resolve, reject) => {
                    const url = URL.createObjectURL(file);
                    const gambar = new Image();

                    gambar.onload = () => resolve({ gambar, url });
                    gambar.onerror = () => {
                        URL.revokeObjectURL(url);
                        reject(new Error('Foto tidak dapat dibaca oleh browser.'));
                    };
                    gambar.src = url;
                });

                const buatBlob = (kanvas, kualitas) => new Promise((resolve, reject) => {
                    kanvas.toBlob((blob) => {
                        if (blob) {
                            resolve(blob);
                        } else {
                            reject(new Error('Foto tidak dapat dikompresi oleh browser.'));
                        }
                    }, 'image/jpeg', kualitas);
                });

                const gambarKeKanvas = (sumber, lebar, tinggi) => {
                    const kanvas = document.createElement('canvas');
                    kanvas.width = Math.max(1, Math.round(lebar));
                    kanvas.height = Math.max(1, Math.round(tinggi));
                    const konteks = kanvas.getContext('2d');

                    if (! konteks) {
                        throw new Error('Browser tidak mendukung pemrosesan foto.');
                    }

                    konteks.fillStyle = '#ffffff';
                    konteks.fillRect(0, 0, kanvas.width, kanvas.height);
                    konteks.drawImage(sumber, 0, 0, kanvas.width, kanvas.height);

                    return kanvas;
                };

                const prosesFoto = async (file, targetByte) => {
                    const { gambar, url } = await muatGambar(file);

                    try {
                        const rasio = Math.min(
                            1,
                            lebarMaksimal / gambar.naturalWidth,
                            tinggiMaksimal / gambar.naturalHeight,
                        );
                        let kanvas = gambarKeKanvas(
                            gambar,
                            gambar.naturalWidth * rasio,
                            gambar.naturalHeight * rasio,
                        );
                        let kualitas = .86;
                        let blob = await buatBlob(kanvas, kualitas);

                        while (blob.size > targetByte && kualitas > .54) {
                            kualitas -= .08;
                            blob = await buatBlob(kanvas, kualitas);
                        }

                        if (blob.size > targetByte) {
                            const rasioTambahan = Math.max(.62, Math.sqrt(targetByte / blob.size) * .9);
                            const kanvasLebihKecil = gambarKeKanvas(
                                kanvas,
                                kanvas.width * rasioTambahan,
                                kanvas.height * rasioTambahan,
                            );
                            kanvas.width = 1;
                            kanvas.height = 1;
                            kanvas = kanvasLebihKecil;
                            blob = await buatBlob(kanvas, .72);
                        }

                        if (blob.size > targetByte) {
                            throw new Error('Foto tetap terlalu besar setelah diperkecil. Pilih foto lain.');
                        }

                        const namaDasar = file.name.replace(/\.[^.]+$/, '') || 'foto';

                        return new File([blob], `${namaDasar}.jpg`, {
                            type: 'image/jpeg',
                            lastModified: Date.now(),
                        });
                    } finally {
                        URL.revokeObjectURL(url);
                    }
                };

                document.querySelectorAll('[data-photo-uploader]').forEach((uploader) => {
                    const input = uploader.querySelector('input[type="file"]');
                    const preview = uploader.querySelector('[data-photo-preview]');
                    const placeholder = uploader.querySelector('[data-photo-placeholder]');
                    const progress = uploader.querySelector('[data-photo-progress]');
                    const status = uploader.querySelector('[data-photo-status]');
                    const form = uploader.closest('form');
                    const tombolSimpan = form ? Array.from(form.querySelectorAll('button[type="submit"]')) : [];
                    const uploadUrl = uploader.dataset.uploadUrl || '';
                    const csrf = uploader.dataset.csrf;
                    const batasSumber = Number(uploader.dataset.maxSourceBytes);
                    const targetByte = Number(uploader.dataset.targetBytes);
                    let urlTersimpan = uploader.dataset.existingUrl || '';
                    let urlPreview = null;

                    const tampilkanStatus = (pesan, jenis) => {
                        status.textContent = pesan;
                        status.className = `foto-upload-status is-${jenis}`;
                        status.hidden = false;
                    };

                    const aturSibuk = (sibuk) => {
                        uploader.classList.toggle('is-busy', sibuk);
                        input.disabled = sibuk;
                        progress.hidden = ! sibuk;
                        tombolSimpan.forEach((tombol) => {
                            tombol.disabled = sibuk;
                        });
                    };

                    const tampilkanPreview = (file) => {
                        if (urlPreview) {
                            URL.revokeObjectURL(urlPreview);
                        }

                        urlPreview = URL.createObjectURL(file);
                        preview.src = urlPreview;
                        preview.hidden = false;
                        placeholder.hidden = true;
                    };

                    const kembalikanPreview = () => {
                        if (urlPreview) {
                            URL.revokeObjectURL(urlPreview);
                            urlPreview = null;
                        }

                        if (urlTersimpan) {
                            preview.src = urlTersimpan;
                            preview.hidden = false;
                            placeholder.hidden = true;
                        } else {
                            preview.removeAttribute('src');
                            preview.hidden = true;
                            placeholder.hidden = false;
                        }
                    };

                    const pesanRespons = async (respons) => {
                        const payload = await respons.json().catch(() => ({}));

                        return payload.errors?.foto?.[0]
                            || payload.pesan
                            || payload.message
                            || 'Foto gagal disimpan. Silakan coba kembali.';
                    };

                    input.addEventListener('change', async () => {
                        const file = input.files?.[0];

                        if (! file) {
                            return;
                        }

                        if (! tipeFoto.includes(file.type)) {
                            input.value = '';
                            kembalikanPreview();
                            tampilkanStatus('Format foto harus JPG, PNG, atau WebP.', 'error');

                            return;
                        }

                        if (file.size > batasSumber) {
                            input.value = '';
                            kembalikanPreview();
                            tampilkanStatus(
                                `Foto berukuran ${ukuranTeks(file.size)}. Ukuran awal maksimal adalah ${ukuranTeks(batasSumber)}.`,
                                'error',
                            );

                            return;
                        }

                        tampilkanPreview(file);
                        tampilkanStatus('Menyiapkan dan memperkecil foto...', 'info');
                        aturSibuk(true);

                        try {
                            const fotoDiproses = await prosesFoto(file, targetByte);
                            tampilkanPreview(fotoDiproses);

                            if (uploadUrl) {
                                const data = new FormData();
                                data.append('foto', fotoDiproses);
                                const respons = await fetch(uploadUrl, {
                                    method: 'POST',
                                    headers: {
                                        'Accept': 'application/json',
                                        'X-CSRF-TOKEN': csrf,
                                        'X-Requested-With': 'XMLHttpRequest',
                                    },
                                    body: data,
                                });

                                if (! respons.ok) {
                                    throw new Error(await pesanRespons(respons));
                                }

                                const payload = await respons.json();
                                urlTersimpan = `${payload.url}?versi=${Date.now()}`;
                                preview.src = urlTersimpan;
                                input.value = '';
                                tampilkanStatus(
                                    `Foto berhasil diperbarui dan tersimpan otomatis (${ukuranTeks(fotoDiproses.size)}).`,
                                    'success',
                                );
                            } else {
                                if (typeof DataTransfer === 'undefined') {
                                    throw new Error('Browser ini belum mendukung penyiapan foto otomatis.');
                                }

                                const pemindahFile = new DataTransfer();
                                pemindahFile.items.add(fotoDiproses);
                                input.files = pemindahFile.files;
                                tampilkanStatus(
                                    `Foto siap disimpan bersama data utama (${ukuranTeks(fotoDiproses.size)}).`,
                                    'success',
                                );
                            }
                        } catch (error) {
                            input.value = '';
                            kembalikanPreview();
                            tampilkanStatus(
                                error instanceof Error ? error.message : 'Foto gagal diproses. Silakan pilih foto lain.',
                                'error',
                            );
                        } finally {
                            aturSibuk(false);
                        }
                    });
                });
            })();
        </script>
    @endpush
@endonce
