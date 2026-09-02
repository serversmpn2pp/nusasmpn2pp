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
        grid-template-columns: minmax(0, 1fr) 320px;
        gap: 22px;
        align-items: start;
    }

    .receipt-flow {
        display: grid;
        grid-template-columns: repeat(3, minmax(0, 1fr));
        gap: 1px;
        overflow: hidden;
        margin-bottom: 22px;
        border: 1px solid var(--line);
        border-radius: 8px;
        background: var(--line);
    }

    .receipt-flow-step {
        display: grid;
        grid-template-columns: 34px minmax(0, 1fr);
        gap: 11px;
        align-items: center;
        min-width: 0;
        padding: 14px 16px;
        background: #fff;
    }

    .receipt-flow-number {
        display: grid;
        width: 34px;
        height: 34px;
        place-items: center;
        border-radius: 50%;
        background: #e5eef7;
        color: #15477a;
        font-weight: 800;
    }

    .receipt-flow-step strong,
    .receipt-flow-step span {
        display: block;
    }

    .receipt-flow-step span {
        margin-top: 2px;
        color: var(--muted);
        font-size: .8rem;
        line-height: 1.4;
    }

    .receipt-line {
        padding: 20px;
        border-top: 1px solid #d8e2ec;
        background: #fff;
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

    .receipt-unit-preview {
        grid-column: 1 / -1;
        display: flex;
        align-items: flex-start;
        justify-content: space-between;
        gap: 14px;
        padding: 11px 13px;
        border: 1px solid #bfd1e3;
        border-left: 4px solid #15477a;
        border-radius: 6px;
        background: #f4f8fc;
        color: #173b5f;
        font-size: .84rem;
        line-height: 1.45;
    }

    .receipt-unit-preview strong {
        overflow-wrap: anywhere;
    }

    .receipt-unit-preview span {
        flex: none;
        color: #607086;
        font-size: .78rem;
    }

    .receipt-unit-preview[hidden],
    .asset-fields[hidden] {
        display: none;
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

    .receipt-form-shell > aside {
        position: sticky;
        top: 86px;
    }

    @media (max-width: 1120px) {
        .receipt-form-shell {
            grid-template-columns: 1fr;
        }

        .receipt-form-shell aside {
            order: -1;
            position: static;
        }

        .receipt-line-grid {
            grid-template-columns: repeat(2, minmax(0, 1fr));
        }
    }

    @media (max-width: 640px) {
        .receipt-flow {
            grid-template-columns: 1fr;
        }

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

        .receipt-unit-preview {
            align-items: stretch;
            flex-direction: column;
        }

        .receipt-unit-preview span {
            flex: initial;
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

<div class="receipt-flow" aria-label="Urutan pencatatan barang datang">
    <div class="receipt-flow-step">
        <span class="receipt-flow-number">1</span>
        <div><strong>Isi dokumen</strong><span>Tanggal, sumber, dan bukti penerimaan.</span></div>
    </div>
    <div class="receipt-flow-step">
        <span class="receipt-flow-number">2</span>
        <div><strong>Masukkan barang</strong><span>Satu baris untuk satu jenis barang yang diterima.</span></div>
    </div>
    <div class="receipt-flow-step">
        <span class="receipt-flow-number">3</span>
        <div><strong>Periksa dan simpan</strong><span>Stok serta unit aset dibuat otomatis.</span></div>
    </div>
</div>

<div class="receipt-form-shell">
    <div class="section-stack">
        <section class="panel panel-pad">
            <p class="eyebrow">Langkah 1</p>
            <h2 class="panel-title">Dokumen barang datang</h2>
            <p class="help-text" style="margin-top: 5px;">Gunakan satu formulir untuk barang yang berasal dari dokumen dan sumber yang sama.</p>
            <div class="form-grid" style="margin-top: 16px;">
                <div class="field">
                    <label for="tanggal_penerimaan">Tanggal barang datang</label>
                    <input id="tanggal_penerimaan" name="tanggal_penerimaan" type="date" max="{{ now()->toDateString() }}" value="{{ old('tanggal_penerimaan', now()->toDateString()) }}" class="input @error('tanggal_penerimaan') is-invalid @enderror" required>
                    <p class="help-text">Isi sesuai tanggal barang benar-benar diterima sekolah.</p>
                </div>

                <div class="field">
                    <label for="sumber_perolehan_barang_id">Sumber barang</label>
                    <select id="sumber_perolehan_barang_id" name="sumber_perolehan_barang_id" class="select @error('sumber_perolehan_barang_id') is-invalid @enderror" required>
                        <option value="">Pilih sumber</option>
                        @foreach ($daftarSumberPerolehan as $sumber)
                            <option value="{{ $sumber->id }}" @selected((string) old('sumber_perolehan_barang_id') === (string) $sumber->id)>{{ $sumber->nama }}</option>
                        @endforeach
                    </select>
                    <p class="help-text">Contoh: BOS atau DAK. Tahun mengikuti tanggal barang datang.</p>
                </div>

                <div class="field">
                    <label for="cara_perolehan">Cara perolehan</label>
                    <select id="cara_perolehan" name="cara_perolehan" class="select @error('cara_perolehan') is-invalid @enderror" required>
                        @foreach ($daftarCaraPerolehan as $nilai => $label)
                            <option value="{{ $nilai }}" @selected(old('cara_perolehan', 'pembelian') === $nilai)>{{ $label }}</option>
                        @endforeach
                    </select>
                    <p class="help-text">Pilih pembelian jika barang dibayar, atau hibah jika diterima sebagai bantuan.</p>
                </div>

                <div class="field">
                    <label for="nomor_dokumen">Nomor dokumen</label>
                    <input id="nomor_dokumen" name="nomor_dokumen" type="text" value="{{ old('nomor_dokumen') }}" placeholder="Faktur, BAST, atau surat jalan" class="input @error('nomor_dokumen') is-invalid @enderror">
                    <p class="help-text">Disarankan diisi agar penerimaan mudah ditelusuri dan tidak tercatat dua kali.</p>
                </div>

                <div class="field span-2">
                    <label for="asal_barang">Asal barang/penyedia</label>
                    <input id="asal_barang" name="asal_barang" type="text" value="{{ old('asal_barang') }}" placeholder="Contoh: CV Maju Bersama" class="input @error('asal_barang') is-invalid @enderror">
                    <p class="help-text">Nama toko, perusahaan, instansi, atau pihak yang menyerahkan barang.</p>
                </div>

                <div class="field span-2">
                    <label for="catatan">Catatan umum</label>
                    <textarea id="catatan" name="catatan" class="textarea @error('catatan') is-invalid @enderror" placeholder="Opsional">{{ old('catatan') }}</textarea>
                    <p class="help-text">Isi hanya jika ada informasi yang berlaku untuk seluruh barang dalam dokumen ini.</p>
                </div>
            </div>
        </section>

        <section class="panel">
            <div class="panel-pad" style="display:flex; align-items:center; justify-content:space-between; gap:12px;">
                <div>
                    <p class="eyebrow">Langkah 2</p>
                    <h2 class="panel-title">Barang yang diterima</h2>
                    <p class="help-text">Pilih master barang, lalu isi jumlah dan kondisi saat diterima.</p>
                </div>
                <button id="tambah-rincian" type="button" class="button button-muted">Tambah barang</button>
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
                                        <option value="{{ $barang->id }}" data-jenis="{{ $barang->jenis_barang }}" data-kode="{{ $barang->kodeKlasifikasi() }}" data-satuan="{{ $barang->satuanBarang->nama }}" data-lokasi="{{ $barang->lokasi_penyimpanan_id }}" @selected((string) ($rincian['barang_id'] ?? '') === (string) $barang->id)>{{ $barang->nama }} - {{ $barang->kodeKlasifikasi() }}</option>
                                    @endforeach
                                </select>
                                <p class="help-text">Tidak menemukan barang? Tambahkan dahulu melalui menu Data Barang.</p>
                            </div>

                            <div class="field">
                                <label>Lokasi penyimpanan</label>
                                <select name="rincian[{{ $indeks }}][lokasi_barang_id]" class="select" data-pilih-lokasi required>
                                    <option value="">Pilih lokasi</option>
                                    @foreach ($daftarLokasi as $lokasi)
                                        <option value="{{ $lokasi->id }}" @selected((string) ($rincian['lokasi_barang_id'] ?? '') === (string) $lokasi->id)>{{ $lokasi->nama }}</option>
                                    @endforeach
                                </select>
                                <p class="help-text">Tempat unit atau stok pertama kali disimpan.</p>
                            </div>

                            <div class="field">
                                <label>Jumlah <span data-label-satuan></span></label>
                                <input name="rincian[{{ $indeks }}][jumlah]" type="number" min="0.01" step="{{ $jenisTerpilih === 'tidak_habis_pakai' ? '1' : '0.01' }}" value="{{ $rincian['jumlah'] ?? 1 }}" class="input" data-jumlah required>
                                <p class="help-text" data-bantuan-jumlah>{{ $jenisTerpilih === 'tidak_habis_pakai' ? 'Masukkan jumlah unit utuh.' : 'Jumlah akan menambah stok lokasi.' }}</p>
                            </div>

                            <div class="field">
                                <label>Harga per satuan</label>
                                <input name="rincian[{{ $indeks }}][harga_satuan]" type="number" min="0" step="0.01" value="{{ $rincian['harga_satuan'] ?? '' }}" placeholder="Opsional" class="input">
                                <p class="help-text">Isi harga satu unit, bukan jumlah keseluruhan.</p>
                            </div>

                            <div class="receipt-unit-preview" data-pratinjau-kode @if ($jenisTerpilih !== 'tidak_habis_pakai') hidden @endif>
                                <strong data-rentang-kode>{{ $barangTerpilih ? $barangTerpilih->kodeKlasifikasi().'.01' : 'Kode unit muncul setelah barang dipilih' }}</strong>
                                <span>Nomor unit pada catatan ini dimulai dari .01</span>
                            </div>

                            <div class="asset-fields" data-kolom-aset @if ($jenisTerpilih !== 'tidak_habis_pakai') hidden @endif>
                                <div class="field">
                                    <label>Merek</label>
                                    <input name="rincian[{{ $indeks }}][merek]" type="text" value="{{ $rincian['merek'] ?? '' }}" placeholder="Contoh: Epson" class="input" data-input-aset @disabled($jenisTerpilih !== 'tidak_habis_pakai')>
                                    <p class="help-text">Nama produsen yang tertulis pada barang.</p>
                                </div>
                                <div class="field">
                                    <label>Tipe/model</label>
                                    <input name="rincian[{{ $indeks }}][tipe]" type="text" value="{{ $rincian['tipe'] ?? '' }}" placeholder="Contoh: L3110" class="input" data-input-aset @disabled($jenisTerpilih !== 'tidak_habis_pakai')>
                                    <p class="help-text">Seri atau model barang jika tersedia.</p>
                                </div>
                                <div class="field">
                                    <label>Kondisi awal</label>
                                    <select name="rincian[{{ $indeks }}][kondisi]" class="select" data-input-aset @disabled($jenisTerpilih !== 'tidak_habis_pakai')>
                                        @foreach ($daftarKondisi as $nilai => $label)
                                            <option value="{{ $nilai }}" @selected(($rincian['kondisi'] ?? 'baik') === $nilai)>{{ $label }}</option>
                                        @endforeach
                                    </select>
                                    <p class="help-text">Kondisi fisik ketika pertama kali diterima.</p>
                                </div>
                            </div>

                            <div class="field span-2">
                                <label>Keterangan barang</label>
                                <input name="rincian[{{ $indeks }}][keterangan]" type="text" value="{{ $rincian['keterangan'] ?? '' }}" placeholder="Opsional" class="input">
                                <p class="help-text">Contoh: termasuk adaptor dan tas, atau kemasan rusak saat diterima.</p>
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
        <h2 class="panel-title">Yang dilakukan NUSA</h2>
        <p class="help-text" style="margin-top: 5px;">Petugas cukup mengisi keadaan barang yang benar-benar diterima.</p>
        <div class="receipt-guide-list">
            <div class="receipt-guide-item">
                <strong>Barang habis pakai</strong>
                <span>Jumlah langsung ditambahkan ke saldo lokasi dan tercatat sebagai stok masuk.</span>
            </div>
            <div class="receipt-guide-item">
                <strong>Barang tidak habis pakai</strong>
                <span>Setiap unit memperoleh label, QR unik, nomor aset resmi, sumber, tahun, dan lokasi.</span>
            </div>
            <div class="receipt-guide-item">
                <strong>Nomor unit setiap barang datang</strong>
                <span>Setiap jenis barang dimulai dari .01. Jika barang yang sama datang lagi pada dokumen berikutnya, nomornya kembali ke .01.</span>
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
                        <option value="{{ $barang->id }}" data-jenis="{{ $barang->jenis_barang }}" data-kode="{{ $barang->kodeKlasifikasi() }}" data-satuan="{{ $barang->satuanBarang->nama }}" data-lokasi="{{ $barang->lokasi_penyimpanan_id }}">{{ $barang->nama }} - {{ $barang->kodeKlasifikasi() }}</option>
                    @endforeach
                </select>
                <p class="help-text">Tidak menemukan barang? Tambahkan dahulu melalui menu Data Barang.</p>
            </div>
            <div class="field">
                <label>Lokasi penyimpanan</label>
                <select name="rincian[__INDEX__][lokasi_barang_id]" class="select" data-pilih-lokasi required>
                    <option value="">Pilih lokasi</option>
                    @foreach ($daftarLokasi as $lokasi)<option value="{{ $lokasi->id }}">{{ $lokasi->nama }}</option>@endforeach
                </select>
                <p class="help-text">Tempat unit atau stok pertama kali disimpan.</p>
            </div>
            <div class="field">
                <label>Jumlah <span data-label-satuan></span></label>
                <input name="rincian[__INDEX__][jumlah]" type="number" min="0.01" step="0.01" value="1" class="input" data-jumlah required>
                <p class="help-text" data-bantuan-jumlah>Jumlah akan menambah stok lokasi.</p>
            </div>
            <div class="field">
                <label>Harga per satuan</label>
                <input name="rincian[__INDEX__][harga_satuan]" type="number" min="0" step="0.01" placeholder="Opsional" class="input">
                <p class="help-text">Isi harga satu unit, bukan jumlah keseluruhan.</p>
            </div>
            <div class="receipt-unit-preview" data-pratinjau-kode hidden>
                <strong data-rentang-kode>Kode unit muncul setelah barang dipilih</strong>
                <span>Nomor unit pada catatan ini dimulai dari .01</span>
            </div>
            <div class="asset-fields" data-kolom-aset hidden>
                <div class="field"><label>Merek</label><input name="rincian[__INDEX__][merek]" type="text" placeholder="Contoh: Epson" class="input" data-input-aset disabled><p class="help-text">Nama produsen yang tertulis pada barang.</p></div>
                <div class="field"><label>Tipe/model</label><input name="rincian[__INDEX__][tipe]" type="text" placeholder="Contoh: L3110" class="input" data-input-aset disabled><p class="help-text">Seri atau model barang jika tersedia.</p></div>
                <div class="field">
                    <label>Kondisi awal</label>
                    <select name="rincian[__INDEX__][kondisi]" class="select" data-input-aset disabled>
                        @foreach ($daftarKondisi as $nilai => $label)<option value="{{ $nilai }}">{{ $label }}</option>@endforeach
                    </select>
                    <p class="help-text">Kondisi fisik ketika pertama kali diterima.</p>
                </div>
            </div>
            <div class="field span-2"><label>Keterangan barang</label><input name="rincian[__INDEX__][keterangan]" type="text" placeholder="Opsional" class="input"><p class="help-text">Isi perlengkapan yang ikut diterima atau catatan khusus barang.</p></div>
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
                const pratinjauKode = baris.querySelector('[data-pratinjau-kode]');
                const rentangKode = baris.querySelector('[data-rentang-kode]');
                const bantuanJumlah = baris.querySelector('[data-bantuan-jumlah]');

                baris.querySelector('[data-ringkasan-jenis]').textContent = aset
                    ? `Barang tidak habis pakai · unit aset dibuat otomatis`
                    : (jenis === 'habis_pakai' ? `Barang habis pakai · stok ditambah otomatis` : 'Pilih barang terlebih dahulu');
                baris.querySelector('[data-label-satuan]').textContent = satuan ? `(${satuan})` : '';
                kolomAset.hidden = !aset;
                pratinjauKode.hidden = !aset;
                kolomAset.querySelectorAll('[data-input-aset]').forEach((input) => input.disabled = !aset);
                jumlah.step = aset ? '1' : '0.01';
                jumlah.min = aset ? '1' : '0.01';
                bantuanJumlah.textContent = aset ? 'Masukkan jumlah unit utuh.' : 'Jumlah akan menambah stok lokasi.';

                if (aset) {
                    const kode = opsi?.dataset.kode || '';
                    const banyak = Math.max(1, Number.parseInt(jumlah.value || '1', 10) || 1);
                    const kodeAwal = `${kode}.${String(1).padStart(2, '0')}`;
                    const kodeAkhir = `${kode}.${String(banyak).padStart(2, '0')}`;
                    rentangKode.textContent = banyak > 1 ? `${kodeAwal} sampai ${kodeAkhir}` : kodeAwal;
                }

                if (isiLokasiBawaan && opsi?.dataset.lokasi) {
                    const lokasi = baris.querySelector('[data-pilih-lokasi]');
                    if (!lokasi.value) lokasi.value = opsi.dataset.lokasi;
                }
            };

            const pasangBaris = (baris) => {
                baris.querySelector('[data-pilih-barang]').addEventListener('change', () => perbaruiBaris(baris, true));
                baris.querySelector('[data-jumlah]').addEventListener('input', () => perbaruiBaris(baris));
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
