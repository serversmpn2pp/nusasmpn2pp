@php
    $nilai = fn (string $field, mixed $default = '') => old($field, $default);
    $inputClass = fn (string $field) => 'input' . ($errors->has($field) ? ' is-invalid' : '');
    $selectClass = fn (string $field) => 'select' . ($errors->has($field) ? ' is-invalid' : '');
    $textareaClass = fn (string $field) => 'textarea' . ($errors->has($field) ? ' is-invalid' : '');
    $jenisAwal = old('jenis_mutasi', 'masuk');
@endphp

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

@if ($daftarBarang->isEmpty() || $daftarLokasi->isEmpty())
    <div class="alert alert-danger">Tambahkan barang berbasis stok dan lokasi aktif terlebih dahulu.</div>
@endif

<div class="form-shell">
    <aside class="panel panel-pad">
        <h2 class="panel-title">Panduan singkat</h2>
        <p class="help-text">Gunakan stok masuk untuk stok awal, pembelian, atau hibah. Gunakan stok keluar saat barang dipakai, rusak, atau hilang.</p>
        <p class="help-text" style="margin-top: 12px;">Penyesuaian stok digunakan setelah cek fisik. Nilai yang diisi adalah saldo terbaru yang benar, bukan selisihnya.</p>
    </aside>

    <div class="section-stack">
        <section class="panel panel-pad">
            <h2 class="panel-title">Informasi Mutasi</h2>

            <div class="form-grid">
                <div class="field">
                    <label for="barang_id">Barang</label>
                    <select id="barang_id" name="barang_id" class="{{ $selectClass('barang_id') }}" required>
                        <option value="">Pilih barang berbasis stok</option>
                        @foreach ($daftarBarang as $item)
                            <option value="{{ $item->id }}" @selected((string) old('barang_id', $barangTerpilihId ?? '') === (string) $item->id)>{{ $item->nama }} - {{ $item->kode }}</option>
                        @endforeach
                    </select>
                    @error('barang_id')
                        <p class="error-text">{{ $message }}</p>
                    @enderror
                </div>

                <div class="field">
                    <label for="lokasi_barang_id">Lokasi stok</label>
                    <select id="lokasi_barang_id" name="lokasi_barang_id" class="{{ $selectClass('lokasi_barang_id') }}" required>
                        <option value="">Pilih lokasi</option>
                        @foreach ($daftarLokasi as $item)
                            <option value="{{ $item->id }}" @selected((string) $nilai('lokasi_barang_id') === (string) $item->id)>{{ $item->nama }}</option>
                        @endforeach
                    </select>
                    @error('lokasi_barang_id')
                        <p class="error-text">{{ $message }}</p>
                    @enderror
                </div>

                <div class="field">
                    <label for="jenis_mutasi">Jenis mutasi</label>
                    <select id="jenis_mutasi" name="jenis_mutasi" class="{{ $selectClass('jenis_mutasi') }}" required>
                        @foreach ($daftarJenis as $nilaiJenis => $labelJenis)
                            <option value="{{ $nilaiJenis }}" @selected($jenisAwal === $nilaiJenis)>{{ $labelJenis }}</option>
                        @endforeach
                    </select>
                    @error('jenis_mutasi')
                        <p class="error-text">{{ $message }}</p>
                    @enderror
                </div>

                <div class="field">
                    <label for="kategori_mutasi">Kategori mutasi</label>
                    <select id="kategori_mutasi" name="kategori_mutasi" class="{{ $selectClass('kategori_mutasi') }}" required>
                        @foreach ($daftarKategori as $nilaiKategori => $labelKategori)
                            <option value="{{ $nilaiKategori }}" data-jenis="{{ implode(',', collect($kategoriPerJenis)->filter(fn ($daftar) => in_array($nilaiKategori, $daftar, true))->keys()->all()) }}" @selected(old('kategori_mutasi', 'stok_awal') === $nilaiKategori)>{{ $labelKategori }}</option>
                        @endforeach
                    </select>
                    @error('kategori_mutasi')
                        <p class="error-text">{{ $message }}</p>
                    @enderror
                </div>

                <div class="field">
                    <label id="label-jumlah" for="jumlah">Jumlah barang</label>
                    <input id="jumlah" name="jumlah" type="number" min="0" step="0.01" value="{{ $nilai('jumlah') }}" placeholder="Contoh: 25" class="{{ $inputClass('jumlah') }}" required>
                    <p id="bantuan-jumlah" class="help-text">Isi banyaknya barang yang masuk atau keluar.</p>
                    @error('jumlah')
                        <p class="error-text">{{ $message }}</p>
                    @enderror
                </div>

                <div class="field">
                    <label for="tanggal_mutasi">Tanggal mutasi</label>
                    <input id="tanggal_mutasi" name="tanggal_mutasi" type="date" value="{{ $nilai('tanggal_mutasi', now()->toDateString()) }}" class="{{ $inputClass('tanggal_mutasi') }}" required>
                    @error('tanggal_mutasi')
                        <p class="error-text">{{ $message }}</p>
                    @enderror
                </div>

                <div class="field">
                    <label for="referensi">Referensi</label>
                    <input id="referensi" name="referensi" type="text" value="{{ $nilai('referensi') }}" placeholder="Contoh: Nota pembelian atau berita acara" class="{{ $inputClass('referensi') }}">
                    @error('referensi')
                        <p class="error-text">{{ $message }}</p>
                    @enderror
                </div>

                <div class="field span-2">
                    <label for="keterangan">Keterangan</label>
                    <textarea id="keterangan" name="keterangan" class="{{ $textareaClass('keterangan') }}" placeholder="Tuliskan catatan tambahan jika diperlukan.">{{ $nilai('keterangan') }}</textarea>
                    @error('keterangan')
                        <p class="error-text">{{ $message }}</p>
                    @enderror
                </div>
            </div>
        </section>

        <div class="form-actions">
            <a href="{{ route('mutasi-stok-barang.index') }}" class="button button-muted">Batal</a>
            <button type="submit" class="button button-primary" @disabled($daftarBarang->isEmpty() || $daftarLokasi->isEmpty())>Simpan mutasi</button>
        </div>
    </div>
</div>

<script>
    (() => {
        const jenis = document.getElementById('jenis_mutasi');
        const kategori = document.getElementById('kategori_mutasi');
        const labelJumlah = document.getElementById('label-jumlah');
        const bantuanJumlah = document.getElementById('bantuan-jumlah');

        const perbaruiForm = () => {
            const jenisAktif = jenis.value;
            const opsi = Array.from(kategori.options);

            opsi.forEach((item) => {
                item.hidden = !item.dataset.jenis.split(',').includes(jenisAktif);
            });

            if (kategori.selectedOptions[0]?.hidden) {
                const opsiPertama = opsi.find((item) => !item.hidden);
                if (opsiPertama) {
                    kategori.value = opsiPertama.value;
                }
            }

            const penyesuaian = jenisAktif === 'penyesuaian';
            labelJumlah.textContent = penyesuaian ? 'Saldo fisik terbaru' : 'Jumlah barang';
            bantuanJumlah.textContent = penyesuaian
                ? 'Isi jumlah barang yang benar berdasarkan pemeriksaan fisik.'
                : 'Isi banyaknya barang yang masuk atau keluar.';
        };

        jenis.addEventListener('change', perbaruiForm);
        perbaruiForm();
    })();
</script>
