@php
    $barisRincian = old('rincian', [[
        'barang_id' => '',
        'lokasi_barang_id' => '',
        'jumlah' => 1,
        'harga_satuan' => '',
        'merek' => '',
        'tipe' => '',
        'kondisi' => 'baik',
        'keterangan' => '',
    ]]);
@endphp

<style>
    .receipt-form-shell {
        display: grid;
        grid-template-columns: minmax(0, 1fr) 300px;
        gap: 22px;
        align-items: start;
    }

    .receipt-line {
        padding: 20px;
        border-top: 1px solid #d8e2ec;
    }

    .receipt-line:first-child {
        border-top: 0;
    }

    .receipt-line-head {
        display: flex;
        align-items: center;
        justify-content: space-between;
        gap: 12px;
        margin-bottom: 16px;
    }

    .receipt-line-title {
        display: flex;
        align-items: center;
        gap: 10px;
        min-width: 0;
    }

    .receipt-line-number {
        display: inline-grid;
        width: 30px;
        height: 30px;
        place-items: center;
        flex: 0 0 30px;
        border-radius: 6px;
        background: #15477a;
        color: #fff;
        font-weight: 800;
    }

    .receipt-line-grid {
        display: grid;
        grid-template-columns: minmax(220px, 1.5fr) minmax(170px, 1fr) 120px 160px;
        gap: 14px;
    }

    .receipt-line-grid .span-2 {
        grid-column: span 2;
    }

    .asset-fields {
        display: contents;
    }

    .receipt-guide-list {
        display: grid;
        gap: 12px;
        margin-top: 16px;
    }

    .receipt-guide-item {
        padding: 13px;
        border: 1px solid #d8e2ec;
        border-radius: 6px;
        background: #f8fafc;
    }

    .receipt-guide-item strong,
    .receipt-guide-item span {
        display: block;
    }

    .receipt-guide-item span {
        margin-top: 4px;
        color: #607086;
        font-size: .88rem;
        line-height: 1.45;
    }

    @media (max-width: 1120px) {
        .receipt-form-shell {
            grid-template-columns: 1fr;
        }

        .receipt-form-shell aside {
            order: -1;
        }

        .receipt-line-grid {
            grid-template-columns: repeat(2, minmax(0, 1fr));
        }
    }

    @media (max-width: 640px) {
        .receipt-line {
            padding: 16px;
        }

        .receipt-line-grid {
            grid-template-columns: 1fr;
        }

        .receipt-line-grid .span-2 {
            grid-column: auto;
        }

        .asset-fields {
            display: contents;
        }
    }
</style>

@if ($errors->any())
    <div class="alert alert-danger">
        <strong>Ada data yang perlu diperbaiki.</strong>
        <ul>
            @foreach ($errors->all() as $error)
                <li>{{ $error }}</li>
            @endforeach
        </ul>
    </div>
@endif

@if ($daftarBarang->isEmpty() || $daftarLokasi->isEmpty() || $daftarSumberPerolehan->isEmpty())
    <div class="alert alert-danger">Master barang, lokasi, dan sumber perolehan harus tersedia sebelum mencatat barang datang.</div>
@endif

<div class="receipt-form-shell">
    <div class="section-stack">
        <section class="panel panel-pad">
            <h2 class="panel-title">Informasi penerimaan</h2>
            <div class="form-grid" style="margin-top: 16px;">
                <div class="field">
                    <label for="tanggal_penerimaan">Tanggal barang datang</label>
                    <input id="tanggal_penerimaan" name="tanggal_penerimaan" type="date" max="{{ now()->toDateString() }}" value="{{ old('tanggal_penerimaan', now()->toDateString()) }}" class="input @error('tanggal_penerimaan') is-invalid @enderror" required>
                </div>

                <div class="field">
                    <label for="sumber_perolehan_barang_id">Sumber barang</label>
                    <select id="sumber_perolehan_barang_id" name="sumber_perolehan_barang_id" class="select @error('sumber_perolehan_barang_id') is-invalid @enderror" required>
                        <option value="">Pilih sumber</option>
                        @foreach ($daftarSumberPerolehan as $sumber)
                            <option value="{{ $sumber->id }}" @selected((string) old('sumber_perolehan_barang_id') === (string) $sumber->id)>{{ $sumber->nama }}</option>
                        @endforeach
                    </select>
                </div>

                <div class="field">
                    <label for="cara_perolehan">Cara perolehan</label>
                    <select id="cara_perolehan" name="cara_perolehan" class="select @error('cara_perolehan') is-invalid @enderror" required>
                        @foreach ($daftarCaraPerolehan as $nilai => $label)
                            <option value="{{ $nilai }}" @selected(old('cara_perolehan', 'pembelian') === $nilai)>{{ $label }}</option>
                        @endforeach
                    </select>
                </div>

                <div class="field">
                    <label for="nomor_dokumen">Nomor dokumen</label>
                    <input id="nomor_dokumen" name="nomor_dokumen" type="text" value="{{ old('nomor_dokumen') }}" placeholder="Faktur, BAST, atau surat jalan" class="input @error('nomor_dokumen') is-invalid @enderror">
                </div>

                <div class="field span-2">
                    <label for="asal_barang">Asal barang/penyedia</label>
                    <input id="asal_barang" name="asal_barang" type="text" value="{{ old('asal_barang') }}" placeholder="Contoh: CV Maju Bersama" class="input @error('asal_barang') is-invalid @enderror">
                </div>

                <div class="field span-2">
                    <label for="catatan">Catatan umum</label>
                    <textarea id="catatan" name="catatan" class="textarea @error('catatan') is-invalid @enderror" placeholder="Opsional">{{ old('catatan') }}</textarea>
                </div>
            </div>
        </section>

        <section class="panel">
            <div class="panel-pad" style="display:flex; align-items:center; justify-content:space-between; gap:12px;">
                <div>
                    <h2 class="panel-title">Rincian barang</h2>
                    <p class="help-text">Tambahkan semua barang dari dokumen penerimaan yang sama.</p>
                </div>
                <button id="tambah-rincian" type="button" class="button button-muted">Tambah baris</button>
            </div>

            <div id="daftar-rincian">
                @foreach ($barisRincian as $indeks => $rincian)
                    @php
                        $barangTerpilih = $daftarBarang->firstWhere('id', (int) ($rincian['barang_id'] ?? 0));
                        $jenisTerpilih = $barangTerpilih?->jenis_barang;
                    @endphp
                    <div class="receipt-line" data-rincian>
                        <div class="receipt-line-head">
                            <div class="receipt-line-title">
                                <span class="receipt-line-number" data-nomor>{{ $loop->iteration }}</span>
                                <div>
                                    <strong>Barang diterima</strong>
                                    <p class="person-meta" data-ringkasan-jenis>{{ $barangTerpilih?->labelJenisBarang() ?: 'Pilih barang terlebih dahulu' }}</p>
                                </div>
                            </div>
                            <button type="button" class="button button-muted" data-hapus-rincian>Hapus</button>
                        </div>

                        <div class="receipt-line-grid">
                            <div class="field">
                                <label>Barang</label>
                                <select name="rincian[{{ $indeks }}][barang_id]" class="select" data-pilih-barang required>
                                    <option value="">Pilih barang</option>
                                    @foreach ($daftarBarang as $barang)
                                        <option value="{{ $barang->id }}" data-jenis="{{ $barang->jenis_barang }}" data-kode="{{ $barang->kode }}" data-satuan="{{ $barang->satuanBarang->nama }}" data-lokasi="{{ $barang->lokasi_penyimpanan_id }}" @selected((string) ($rincian['barang_id'] ?? '') === (string) $barang->id)>{{ $barang->nama }} - {{ $barang->kode }}</option>
                                    @endforeach
                                </select>
                            </div>

                            <div class="field">
                                <label>Lokasi penyimpanan</label>
                                <select name="rincian[{{ $indeks }}][lokasi_barang_id]" class="select" data-pilih-lokasi required>
                                    <option value="">Pilih lokasi</option>
                                    @foreach ($daftarLokasi as $lokasi)
                                        <option value="{{ $lokasi->id }}" @selected((string) ($rincian['lokasi_barang_id'] ?? '') === (string) $lokasi->id)>{{ $lokasi->nama }}</option>
                                    @endforeach
                                </select>
                            </div>

                            <div class="field">
                                <label>Jumlah <span data-label-satuan></span></label>
                                <input name="rincian[{{ $indeks }}][jumlah]" type="number" min="0.01" step="{{ $jenisTerpilih === 'tidak_habis_pakai' ? '1' : '0.01' }}" value="{{ $rincian['jumlah'] ?? 1 }}" class="input" data-jumlah required>
                            </div>

                            <div class="field">
                                <label>Harga per satuan</label>
                                <input name="rincian[{{ $indeks }}][harga_satuan]" type="number" min="0" step="0.01" value="{{ $rincian['harga_satuan'] ?? '' }}" placeholder="Opsional" class="input">
                            </div>

                            <div class="asset-fields" data-kolom-aset @if ($jenisTerpilih !== 'tidak_habis_pakai') hidden @endif>
                                <div class="field">
                                    <label>Merek</label>
                                    <input name="rincian[{{ $indeks }}][merek]" type="text" value="{{ $rincian['merek'] ?? '' }}" placeholder="Contoh: Epson" class="input" data-input-aset @disabled($jenisTerpilih !== 'tidak_habis_pakai')>
                                </div>
                                <div class="field">
                                    <label>Tipe/model</label>
                                    <input name="rincian[{{ $indeks }}][tipe]" type="text" value="{{ $rincian['tipe'] ?? '' }}" placeholder="Contoh: L3110" class="input" data-input-aset @disabled($jenisTerpilih !== 'tidak_habis_pakai')>
                                </div>
                                <div class="field">
                                    <label>Kondisi awal</label>
                                    <select name="rincian[{{ $indeks }}][kondisi]" class="select" data-input-aset @disabled($jenisTerpilih !== 'tidak_habis_pakai')>
                                        @foreach ($daftarKondisi as $nilai => $label)
                                            <option value="{{ $nilai }}" @selected(($rincian['kondisi'] ?? 'baik') === $nilai)>{{ $label }}</option>
                                        @endforeach
                                    </select>
                                </div>
                            </div>

                            <div class="field span-2">
                                <label>Keterangan barang</label>
                                <input name="rincian[{{ $indeks }}][keterangan]" type="text" value="{{ $rincian['keterangan'] ?? '' }}" placeholder="Opsional" class="input">
                            </div>
                        </div>
                    </div>
                @endforeach
            </div>
        </section>

        <div class="form-actions">
            <a href="{{ route('penerimaan-barang.index') }}" class="button button-muted">Batal</a>
            <button type="submit" class="button button-primary" data-simpan-penerimaan @disabled($daftarBarang->isEmpty() || $daftarLokasi->isEmpty() || $daftarSumberPerolehan->isEmpty())>Simpan barang datang</button>
        </div>
    </div>

    <aside class="panel panel-pad">
        <h2 class="panel-title">Proses otomatis</h2>
        <div class="receipt-guide-list">
            <div class="receipt-guide-item">
                <strong>Barang habis pakai</strong>
                <span>Jumlah langsung ditambahkan ke saldo lokasi dan tercatat sebagai stok masuk.</span>
            </div>
            <div class="receipt-guide-item">
                <strong>Barang tidak habis pakai</strong>
                <span>Setiap unit memperoleh ID internal, nomor aset resmi, sumber, tahun, dan lokasi.</span>
            </div>
            <div class="receipt-guide-item">
                <strong>Dokumen final</strong>
                <span>Setelah disimpan, penerimaan menjadi arsip dan tidak dapat diedit dari halaman ini.</span>
            </div>
        </div>
    </aside>
</div>

<template id="template-rincian">
    <div class="receipt-line" data-rincian>
        <div class="receipt-line-head">
            <div class="receipt-line-title">
                <span class="receipt-line-number" data-nomor></span>
                <div><strong>Barang diterima</strong><p class="person-meta" data-ringkasan-jenis>Pilih barang terlebih dahulu</p></div>
            </div>
            <button type="button" class="button button-muted" data-hapus-rincian>Hapus</button>
        </div>
        <div class="receipt-line-grid">
            <div class="field">
                <label>Barang</label>
                <select name="rincian[__INDEX__][barang_id]" class="select" data-pilih-barang required>
                    <option value="">Pilih barang</option>
                    @foreach ($daftarBarang as $barang)
                        <option value="{{ $barang->id }}" data-jenis="{{ $barang->jenis_barang }}" data-kode="{{ $barang->kode }}" data-satuan="{{ $barang->satuanBarang->nama }}" data-lokasi="{{ $barang->lokasi_penyimpanan_id }}">{{ $barang->nama }} - {{ $barang->kode }}</option>
                    @endforeach
                </select>
            </div>
            <div class="field">
                <label>Lokasi penyimpanan</label>
                <select name="rincian[__INDEX__][lokasi_barang_id]" class="select" data-pilih-lokasi required>
                    <option value="">Pilih lokasi</option>
                    @foreach ($daftarLokasi as $lokasi)<option value="{{ $lokasi->id }}">{{ $lokasi->nama }}</option>@endforeach
                </select>
            </div>
            <div class="field">
                <label>Jumlah <span data-label-satuan></span></label>
                <input name="rincian[__INDEX__][jumlah]" type="number" min="0.01" step="0.01" value="1" class="input" data-jumlah required>
            </div>
            <div class="field">
                <label>Harga per satuan</label>
                <input name="rincian[__INDEX__][harga_satuan]" type="number" min="0" step="0.01" placeholder="Opsional" class="input">
            </div>
            <div class="asset-fields" data-kolom-aset hidden>
                <div class="field"><label>Merek</label><input name="rincian[__INDEX__][merek]" type="text" placeholder="Contoh: Epson" class="input" data-input-aset disabled></div>
                <div class="field"><label>Tipe/model</label><input name="rincian[__INDEX__][tipe]" type="text" placeholder="Contoh: L3110" class="input" data-input-aset disabled></div>
                <div class="field">
                    <label>Kondisi awal</label>
                    <select name="rincian[__INDEX__][kondisi]" class="select" data-input-aset disabled>
                        @foreach ($daftarKondisi as $nilai => $label)<option value="{{ $nilai }}">{{ $label }}</option>@endforeach
                    </select>
                </div>
            </div>
            <div class="field span-2"><label>Keterangan barang</label><input name="rincian[__INDEX__][keterangan]" type="text" placeholder="Opsional" class="input"></div>
        </div>
    </div>
</template>

@push('scripts')
    <script>
        document.addEventListener('DOMContentLoaded', () => {
            const form = document.getElementById('form-penerimaan-barang');
            const tombolSimpan = form?.querySelector('[data-simpan-penerimaan]');
            const daftar = document.getElementById('daftar-rincian');
            const template = document.getElementById('template-rincian');
            let indeksBerikutnya = {{ count($barisRincian) }};
            let sedangMenyimpan = false;

            form?.addEventListener('submit', (event) => {
                if (sedangMenyimpan) {
                    event.preventDefault();

                    return;
                }

                sedangMenyimpan = true;
                form.setAttribute('aria-busy', 'true');

                if (tombolSimpan) {
                    tombolSimpan.disabled = true;
                    tombolSimpan.textContent = 'Sedang menyimpan...';
                }
            });

            const perbaruiNomor = () => {
                daftar.querySelectorAll('[data-rincian]').forEach((baris, indeks) => {
                    baris.querySelector('[data-nomor]').textContent = indeks + 1;
                });
            };

            const perbaruiBaris = (baris, isiLokasiBawaan = false) => {
                const pilihan = baris.querySelector('[data-pilih-barang]');
                const opsi = pilihan.options[pilihan.selectedIndex];
                const jenis = opsi?.dataset.jenis || '';
                const satuan = opsi?.dataset.satuan || '';
                const aset = jenis === 'tidak_habis_pakai';
                const kolomAset = baris.querySelector('[data-kolom-aset]');
                const jumlah = baris.querySelector('[data-jumlah]');

                baris.querySelector('[data-ringkasan-jenis]').textContent = aset
                    ? `Barang tidak habis pakai · unit aset dibuat otomatis`
                    : (jenis === 'habis_pakai' ? `Barang habis pakai · stok ditambah otomatis` : 'Pilih barang terlebih dahulu');
                baris.querySelector('[data-label-satuan]').textContent = satuan ? `(${satuan})` : '';
                kolomAset.hidden = !aset;
                kolomAset.querySelectorAll('[data-input-aset]').forEach((input) => input.disabled = !aset);
                jumlah.step = aset ? '1' : '0.01';
                jumlah.min = aset ? '1' : '0.01';

                if (isiLokasiBawaan && opsi?.dataset.lokasi) {
                    const lokasi = baris.querySelector('[data-pilih-lokasi]');
                    if (!lokasi.value) lokasi.value = opsi.dataset.lokasi;
                }
            };

            const pasangBaris = (baris) => {
                baris.querySelector('[data-pilih-barang]').addEventListener('change', () => perbaruiBaris(baris, true));
                baris.querySelector('[data-hapus-rincian]').addEventListener('click', () => {
                    if (daftar.querySelectorAll('[data-rincian]').length === 1) {
                        baris.querySelectorAll('input').forEach((input) => input.value = input.dataset.jumlah !== undefined ? '1' : '');
                        baris.querySelectorAll('select').forEach((select) => select.selectedIndex = 0);
                        perbaruiBaris(baris);
                        return;
                    }
                    baris.remove();
                    perbaruiNomor();
                });
                perbaruiBaris(baris);
            };

            daftar.querySelectorAll('[data-rincian]').forEach(pasangBaris);
            perbaruiNomor();

            document.getElementById('tambah-rincian').addEventListener('click', () => {
                const pembungkus = document.createElement('div');
                pembungkus.innerHTML = template.innerHTML.replaceAll('__INDEX__', indeksBerikutnya++).trim();
                const baris = pembungkus.firstElementChild;
                daftar.appendChild(baris);
                pasangBaris(baris);
                perbaruiNomor();
                baris.querySelector('[data-pilih-barang]').focus();
            });
        });
    </script>
@endpush
