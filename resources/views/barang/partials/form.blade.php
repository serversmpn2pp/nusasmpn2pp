@php
    $barang = $barang ?? null;
    $nilai = fn (string $field, mixed $default = '') => old($field, $barang?->{$field} ?? $default);
    $inputClass = fn (string $field) => 'input' . ($errors->has($field) ? ' is-invalid' : '');
    $selectClass = fn (string $field) => 'select' . ($errors->has($field) ? ' is-invalid' : '');
    $textareaClass = fn (string $field) => 'textarea' . ($errors->has($field) ? ' is-invalid' : '');
@endphp

<style>
    .inventory-form-guide {
        display: grid;
        gap: 14px;
    }

    .inventory-guide-list {
        display: grid;
        gap: 0;
        margin: 14px 0 0;
    }

    .inventory-guide-row {
        display: grid;
        grid-template-columns: 28px minmax(0, 1fr);
        gap: 10px;
        padding: 12px 0;
        border-top: 1px solid var(--line);
    }

    .inventory-guide-row:first-child {
        border-top: 0;
        padding-top: 0;
    }

    .inventory-guide-number {
        display: grid;
        width: 28px;
        height: 28px;
        place-items: center;
        border-radius: 50%;
        background: #e7f0f8;
        color: #15477a;
        font-size: .78rem;
        font-weight: 800;
    }

    .inventory-guide-row strong,
    .inventory-guide-row span {
        display: block;
    }

    .inventory-guide-row span {
        margin-top: 3px;
        color: var(--muted);
        font-size: .82rem;
        line-height: 1.45;
    }

    .inventory-code-note {
        grid-column: 1 / -1;
        display: grid;
        grid-template-columns: repeat(2, minmax(0, 1fr));
        gap: 1px;
        overflow: hidden;
        border: 1px solid #cddbea;
        border-radius: 7px;
        background: #cddbea;
    }

    .inventory-code-note > div {
        min-width: 0;
        padding: 12px 14px;
        background: #f7fafd;
    }

    .inventory-code-note span,
    .inventory-code-note strong {
        display: block;
        overflow-wrap: anywhere;
    }

    .inventory-code-note span {
        color: var(--muted);
        font-size: .77rem;
        font-weight: 700;
    }

    .inventory-code-note strong {
        margin-top: 4px;
        color: #123f6d;
    }

    .inventory-code-note[hidden] {
        display: none;
    }

    @media (max-width: 640px) {
        .inventory-code-note {
            grid-template-columns: 1fr;
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

@if ($daftarKategoriBarang->isEmpty() || $daftarSatuanBarang->isEmpty())
    <div class="alert alert-danger">
        Kategori dan satuan barang aktif perlu diisi terlebih dahulu sebelum inventaris barang dapat disimpan.
    </div>
@endif

<div class="form-shell">
    <aside class="panel panel-pad inventory-form-guide">
        <div>
            <h2 class="panel-title">Panduan pengisian</h2>
            <p class="help-text">Data ini menjadi master yang dipilih kembali setiap kali barang datang.</p>

            <div class="inventory-guide-list">
                <div class="inventory-guide-row">
                    <span class="inventory-guide-number">1</span>
                    <div><strong>Pilih jenis barang</strong><span>Bedakan barang yang habis digunakan dan aset yang dicatat per unit.</span></div>
                </div>
                <div class="inventory-guide-row">
                    <span class="inventory-guide-number">2</span>
                    <div><strong>Isi identitas master</strong><span>Nama dan kode tidak perlu memuat merek, tipe, sumber, atau nomor unit.</span></div>
                </div>
                <div class="inventory-guide-row">
                    <span class="inventory-guide-number">3</span>
                    <div><strong>Catat barang datang</strong><span>Jumlah, merek, kondisi, sumber, dan nomor unit dicatat saat barang diterima.</span></div>
                </div>
            </div>
        </div>

        <div style="padding-top: 14px; border-top: 1px solid var(--line);">
            <h3 class="form-label" style="margin:0 0 4px;">Status data</h3>
            <p class="help-text">Nonaktifkan hanya jika barang tidak boleh dipakai pada transaksi baru.</p>
        </div>

        <label class="status-toggle">
            <span>
                <span class="form-label" style="margin-bottom:0">Barang aktif</span>
                <span class="help-text">Tersedia pada transaksi inventaris</span>
            </span>
            <input type="hidden" name="aktif" value="0">
            <input type="checkbox" name="aktif" value="1" @checked((bool) $nilai('aktif', true))>
        </label>
    </aside>

    <div class="section-stack">
        <section class="panel panel-pad">
            <h2 class="panel-title">Identitas barang</h2>
            <p class="help-text" style="margin-top: 5px;">Buat satu master untuk satu jenis barang. Merek dan tipe boleh berbeda pada setiap barang datang.</p>

            <div class="form-grid">
                <div class="field">
                    <label for="nama">Nama barang</label>
                    <input id="nama" name="nama" type="text" value="{{ $nilai('nama') }}" placeholder="Contoh: Laptop Chromebook" class="{{ $inputClass('nama') }}" required autofocus>
                    <p class="help-text">Gunakan nama umum barang, tanpa nomor unit atau sumber anggaran.</p>
                    @error('nama')
                        <p class="error-text">{{ $message }}</p>
                    @enderror
                </div>

                <div class="field">
                    <label for="jenis_barang">Jenis barang</label>
                    <select id="jenis_barang" name="jenis_barang" class="{{ $selectClass('jenis_barang') }}" required>
                        @foreach ($daftarJenisBarang as $nilaiJenis => $labelJenis)
                            <option value="{{ $nilaiJenis }}" @selected($nilai('jenis_barang', 'tidak_habis_pakai') === $nilaiJenis)>{{ $labelJenis }}</option>
                        @endforeach
                    </select>
                    <p class="help-text">Pilih tidak habis pakai untuk barang yang setiap unitnya diberi label aset.</p>
                    @error('jenis_barang')
                        <p class="error-text">{{ $message }}</p>
                    @enderror
                </div>

                <div class="field">
                    <label for="kode">Kode barang</label>
                    <input id="kode" name="kode" type="text" value="{{ old('kode', $barang?->kodeKlasifikasi() ?? '') }}" placeholder="Contoh: 02.06.01.05.40" class="{{ $inputClass('kode') }}" inputmode="numeric" autocomplete="off" maxlength="14">
                    <p id="kode-help" class="help-text">Ketik sepuluh angka. Titik ditambahkan otomatis dan nomor unit dibuat saat barang datang.</p>
                    @error('kode')
                        <p class="error-text">{{ $message }}</p>
                    @enderror
                </div>

                <div id="kode-unit-note" class="inventory-code-note">
                    <div><span>Disimpan sebagai kode barang</span><strong data-contoh-kode-master>02.06.01.05.40</strong></div>
                    <div><span>Contoh unit pertama saat barang datang</span><strong data-contoh-kode-unit>02.06.01.05.40.01</strong></div>
                </div>

                <div class="field">
                    <label for="kategori_barang_id">Kategori</label>
                    <select id="kategori_barang_id" name="kategori_barang_id" class="{{ $selectClass('kategori_barang_id') }}" required>
                        <option value="">Pilih kategori</option>
                        @foreach ($daftarKategoriBarang as $kategori)
                            <option value="{{ $kategori->id }}" @selected((string) $nilai('kategori_barang_id') === (string) $kategori->id)>{{ $kategori->nama }}</option>
                        @endforeach
                    </select>
                    <p class="help-text">Kelompokkan barang agar pencarian dan laporan lebih mudah.</p>
                    @error('kategori_barang_id')
                        <p class="error-text">{{ $message }}</p>
                    @enderror
                </div>

                <div class="field">
                    <label for="satuan_barang_id">Satuan</label>
                    <select id="satuan_barang_id" name="satuan_barang_id" class="{{ $selectClass('satuan_barang_id') }}" required>
                        <option value="">Pilih satuan</option>
                        @foreach ($daftarSatuanBarang as $satuan)
                            <option value="{{ $satuan->id }}" @selected((string) $nilai('satuan_barang_id') === (string) $satuan->id)>{{ $satuan->nama }}</option>
                        @endforeach
                    </select>
                    <p class="help-text">Contoh: unit, buah, rim, kotak, atau liter.</p>
                    @error('satuan_barang_id')
                        <p class="error-text">{{ $message }}</p>
                    @enderror
                </div>

                <div class="field">
                    <label for="lokasi_penyimpanan_id">Lokasi penyimpanan awal</label>
                    <select id="lokasi_penyimpanan_id" name="lokasi_penyimpanan_id" class="{{ $selectClass('lokasi_penyimpanan_id') }}">
                        <option value="">Belum ditentukan</option>
                        @foreach ($daftarLokasiBarang as $lokasi)
                            <option value="{{ $lokasi->id }}" @selected((string) $nilai('lokasi_penyimpanan_id') === (string) $lokasi->id)>{{ $lokasi->nama }}</option>
                        @endforeach
                    </select>
                    <p class="help-text">Lokasi ini otomatis dipilih saat mencatat barang datang dan masih dapat diubah.</p>
                    @error('lokasi_penyimpanan_id')
                        <p class="error-text">{{ $message }}</p>
                    @enderror
                </div>

                <div id="stok-minimum-field" class="field">
                    <label for="stok_minimum">Stok minimum</label>
                    <input id="stok_minimum" name="stok_minimum" type="number" min="0" step="0.01" value="{{ $nilai('stok_minimum', 0) }}" placeholder="Contoh: 10" class="{{ $inputClass('stok_minimum') }}">
                    <p class="help-text">Digunakan sebagai pengingat ketika saldo stok mulai menipis.</p>
                    @error('stok_minimum')
                        <p class="error-text">{{ $message }}</p>
                    @enderror
                </div>

                <div class="field span-2">
                    <label for="deskripsi">Deskripsi</label>
                    <textarea id="deskripsi" name="deskripsi" class="{{ $textareaClass('deskripsi') }}" placeholder="Tuliskan spesifikasi atau keterangan penting barang.">{{ $nilai('deskripsi') }}</textarea>
                    @error('deskripsi')
                        <p class="error-text">{{ $message }}</p>
                    @enderror
                </div>
            </div>
        </section>

        <div class="form-actions">
            <a href="{{ route('barang.index') }}" class="button button-muted">Batal</a>
            <button type="submit" class="button button-primary" @disabled($daftarKategoriBarang->isEmpty() || $daftarSatuanBarang->isEmpty())>{{ $tombol ?? 'Simpan' }}</button>
        </div>
    </div>
</div>

@push('scripts')
    <script>
        document.addEventListener('DOMContentLoaded', () => {
            const jenis = document.getElementById('jenis_barang');
            const kode = document.getElementById('kode');
            const bantuanKode = document.getElementById('kode-help');
            const stokMinimum = document.getElementById('stok-minimum-field');

            const sesuaikanForm = () => {
                const habisPakai = jenis.value === 'habis_pakai';
                kode.readOnly = habisPakai;
                kode.required = !habisPakai;
                kode.placeholder = habisPakai ? 'Dibuat otomatis oleh NUSA' : 'Contoh: 02.06.01.05.40';
                bantuanKode.textContent = habisPakai
                    ? 'Tidak perlu diisi. NUSA membuat kode seperti BHP-000001.'
                    : 'Ketik sepuluh angka. Titik ditambahkan otomatis dan nomor unit dibuat saat barang datang.';
                stokMinimum.hidden = !habisPakai;
                document.getElementById('kode-unit-note').hidden = habisPakai;
            };

            const perbaruiContohKode = () => {
                const contoh = kode.value.trim() || '02.06.01.05.40';
                document.querySelector('[data-contoh-kode-master]').textContent = contoh;
                document.querySelector('[data-contoh-kode-unit]').textContent = `${contoh}.01`;
            };

            jenis.addEventListener('change', sesuaikanForm);
            kode.addEventListener('input', () => {
                if (jenis.value !== 'habis_pakai') {
                    const angka = kode.value.replace(/\D/g, '').slice(0, 10);
                    kode.value = angka.match(/.{1,2}/g)?.join('.') || '';
                }

                perbaruiContohKode();
            });
            sesuaikanForm();
            perbaruiContohKode();
        });
    </script>
@endpush
