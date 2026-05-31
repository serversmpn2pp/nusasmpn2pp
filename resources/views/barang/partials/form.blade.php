@php
    $barang = $barang ?? null;
    $nilai = fn (string $field, mixed $default = '') => old($field, $barang?->{$field} ?? $default);
    $inputClass = fn (string $field) => 'input' . ($errors->has($field) ? ' is-invalid' : '');
    $selectClass = fn (string $field) => 'select' . ($errors->has($field) ? ' is-invalid' : '');
    $textareaClass = fn (string $field) => 'textarea' . ($errors->has($field) ? ' is-invalid' : '');
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

@if ($daftarKategoriBarang->isEmpty() || $daftarSatuanBarang->isEmpty())
    <div class="alert alert-danger">
        Kategori dan satuan barang aktif perlu diisi terlebih dahulu sebelum inventaris barang dapat disimpan.
    </div>
@endif

<div class="form-shell">
    <aside class="panel panel-pad">
        <h2 class="panel-title">Status barang</h2>
        <p class="help-text">Barang aktif tersedia untuk pencatatan aset, stok, dan transaksi sarpras.</p>

        <label class="status-toggle">
            <span>
                <span class="form-label" style="margin-bottom:0">Barang aktif</span>
                <span class="help-text">Tersedia pada transaksi inventaris</span>
            </span>
            <input type="hidden" name="aktif" value="0">
            <input type="checkbox" name="aktif" value="1" @checked((bool) $nilai('aktif', true))>
        </label>

        <p class="help-text" style="margin-top: 16px;">Kode barang akan dipakai sebagai identitas internal. Pada tahap barcode, aset individual memperoleh kode unik untuk setiap unitnya.</p>
    </aside>

    <div class="section-stack">
        <section class="panel panel-pad">
            <h2 class="panel-title">Informasi Barang</h2>

            <div class="form-grid">
                <div class="field">
                    <label for="nama">Nama barang</label>
                    <input id="nama" name="nama" type="text" value="{{ $nilai('nama') }}" placeholder="Contoh: Laptop Chromebook" class="{{ $inputClass('nama') }}" required autofocus>
                    @error('nama')
                        <p class="error-text">{{ $message }}</p>
                    @enderror
                </div>

                <div class="field">
                    <label for="kode">Kode barang</label>
                    <input id="kode" name="kode" type="text" value="{{ $nilai('kode') }}" placeholder="Contoh: LPT_CHROMEBOOK" class="{{ $inputClass('kode') }}" required>
                    <p class="help-text">Kode akan dirapikan menjadi huruf besar tanpa spasi.</p>
                    @error('kode')
                        <p class="error-text">{{ $message }}</p>
                    @enderror
                </div>

                <div class="field">
                    <label for="kategori_barang_id">Kategori</label>
                    <select id="kategori_barang_id" name="kategori_barang_id" class="{{ $selectClass('kategori_barang_id') }}" required>
                        <option value="">Pilih kategori</option>
                        @foreach ($daftarKategoriBarang as $kategori)
                            <option value="{{ $kategori->id }}" @selected((string) $nilai('kategori_barang_id') === (string) $kategori->id)>{{ $kategori->nama }}</option>
                        @endforeach
                    </select>
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
                    @error('satuan_barang_id')
                        <p class="error-text">{{ $message }}</p>
                    @enderror
                </div>

                <div class="field">
                    <label for="tipe_pengelolaan">Tipe pengelolaan</label>
                    <select id="tipe_pengelolaan" name="tipe_pengelolaan" class="{{ $selectClass('tipe_pengelolaan') }}" required>
                        <option value="">Pilih tipe</option>
                        @foreach ($daftarTipePengelolaan as $nilaiTipe => $labelTipe)
                            <option value="{{ $nilaiTipe }}" @selected($nilai('tipe_pengelolaan') === $nilaiTipe)>{{ $labelTipe }}</option>
                        @endforeach
                    </select>
                    @error('tipe_pengelolaan')
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
                    @error('lokasi_penyimpanan_id')
                        <p class="error-text">{{ $message }}</p>
                    @enderror
                </div>

                <div class="field">
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
