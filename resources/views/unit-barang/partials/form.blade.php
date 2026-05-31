@php
    $unitBarang = $unitBarang ?? null;
    $modeTambah = $modeTambah ?? false;
    $nilai = fn (string $field, mixed $default = '') => old($field, $unitBarang?->{$field} ?? $default);
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

@if ($modeTambah && $daftarBarang->isEmpty())
    <div class="alert alert-danger">Tambahkan barang dengan tipe aset individual terlebih dahulu.</div>
@endif

<div class="form-shell">
    <aside class="panel panel-pad">
        <h2 class="panel-title">Status unit</h2>
        <p class="help-text">Kode inventaris dan barcode dibuat otomatis oleh NUSA saat unit disimpan.</p>

        <label class="status-toggle">
            <span>
                <span class="form-label" style="margin-bottom:0">Unit aktif</span>
                <span class="help-text">Tersedia untuk pengelolaan inventaris</span>
            </span>
            <input type="hidden" name="aktif" value="0">
            <input type="checkbox" name="aktif" value="1" @checked((bool) $nilai('aktif', true))>
        </label>

        @if (! $modeTambah)
            <p class="help-text" style="margin-top: 16px;">Kode: <strong>{{ $unitBarang->kode_inventaris }}</strong></p>
        @endif
    </aside>

    <div class="section-stack">
        <section class="panel panel-pad">
            <h2 class="panel-title">Identitas Aset</h2>

            <div class="form-grid">
                @if ($modeTambah)
                    <div class="field">
                        <label for="barang_id">Barang</label>
                        <select id="barang_id" name="barang_id" class="{{ $selectClass('barang_id') }}" required>
                            <option value="">Pilih aset individual</option>
                            @foreach ($daftarBarang as $item)
                                <option value="{{ $item->id }}" @selected((string) old('barang_id', $barangTerpilihId ?? '') === (string) $item->id)>{{ $item->nama }} - {{ $item->kode }}</option>
                            @endforeach
                        </select>
                        @error('barang_id')
                            <p class="error-text">{{ $message }}</p>
                        @enderror
                    </div>

                    <div class="field">
                        <label for="jumlah_unit">Jumlah unit yang dibuat</label>
                        <input id="jumlah_unit" name="jumlah_unit" type="number" min="1" max="100" value="{{ old('jumlah_unit', 1) }}" class="{{ $inputClass('jumlah_unit') }}" required>
                        <p class="help-text">Maksimal 100 unit dalam sekali simpan.</p>
                        @error('jumlah_unit')
                            <p class="error-text">{{ $message }}</p>
                        @enderror
                    </div>
                @else
                    <div class="field">
                        <label>Barang</label>
                        <input type="text" value="{{ $unitBarang->barang->nama }}" class="input" disabled>
                    </div>
                @endif

                <div class="field">
                    <label for="lokasi_barang_id">Lokasi saat ini</label>
                    <select id="lokasi_barang_id" name="lokasi_barang_id" class="{{ $selectClass('lokasi_barang_id') }}">
                        <option value="">Ikuti lokasi awal barang</option>
                        @foreach ($daftarLokasi as $item)
                            <option value="{{ $item->id }}" @selected((string) $nilai('lokasi_barang_id') === (string) $item->id)>{{ $item->nama }}</option>
                        @endforeach
                    </select>
                    @error('lokasi_barang_id')
                        <p class="error-text">{{ $message }}</p>
                    @enderror
                </div>

                <div class="field">
                    <label for="nomor_seri">Nomor seri</label>
                    <input id="nomor_seri" name="nomor_seri" type="text" value="{{ $nilai('nomor_seri') }}" placeholder="Opsional" class="{{ $inputClass('nomor_seri') }}">
                    @if ($modeTambah)
                        <p class="help-text">Isi langsung hanya jika membuat satu unit.</p>
                    @endif
                    @error('nomor_seri')
                        <p class="error-text">{{ $message }}</p>
                    @enderror
                </div>

                <div class="field">
                    <label for="kondisi">Kondisi</label>
                    <select id="kondisi" name="kondisi" class="{{ $selectClass('kondisi') }}" required>
                        @foreach ($daftarKondisi as $nilaiKondisi => $labelKondisi)
                            <option value="{{ $nilaiKondisi }}" @selected($nilai('kondisi', 'baik') === $nilaiKondisi)>{{ $labelKondisi }}</option>
                        @endforeach
                    </select>
                    @error('kondisi')
                        <p class="error-text">{{ $message }}</p>
                    @enderror
                </div>

                <div class="field">
                    <label for="status_unit">Status unit</label>
                    <select id="status_unit" name="status_unit" class="{{ $selectClass('status_unit') }}" required>
                        @foreach ($daftarStatusUnit as $nilaiStatus => $labelStatus)
                            <option value="{{ $nilaiStatus }}" @selected($nilai('status_unit', 'tersedia') === $nilaiStatus)>{{ $labelStatus }}</option>
                        @endforeach
                    </select>
                    @error('status_unit')
                        <p class="error-text">{{ $message }}</p>
                    @enderror
                </div>
            </div>
        </section>

        <section class="panel panel-pad">
            <h2 class="panel-title">Perolehan dan Catatan</h2>

            <div class="form-grid">
                <div class="field">
                    <label for="tanggal_perolehan">Tanggal perolehan</label>
                    <input id="tanggal_perolehan" name="tanggal_perolehan" type="date" value="{{ $nilai('tanggal_perolehan') instanceof \Carbon\CarbonInterface ? $nilai('tanggal_perolehan')->format('Y-m-d') : $nilai('tanggal_perolehan') }}" class="{{ $inputClass('tanggal_perolehan') }}">
                    @error('tanggal_perolehan')
                        <p class="error-text">{{ $message }}</p>
                    @enderror
                </div>

                <div class="field">
                    <label for="sumber_perolehan">Sumber perolehan</label>
                    <input id="sumber_perolehan" name="sumber_perolehan" type="text" value="{{ $nilai('sumber_perolehan') }}" placeholder="Contoh: Dana BOS atau hibah" class="{{ $inputClass('sumber_perolehan') }}">
                    @error('sumber_perolehan')
                        <p class="error-text">{{ $message }}</p>
                    @enderror
                </div>

                <div class="field">
                    <label for="harga_perolehan">Harga perolehan</label>
                    <input id="harga_perolehan" name="harga_perolehan" type="number" min="0" step="0.01" value="{{ $nilai('harga_perolehan') }}" placeholder="Contoh: 4500000" class="{{ $inputClass('harga_perolehan') }}">
                    @error('harga_perolehan')
                        <p class="error-text">{{ $message }}</p>
                    @enderror
                </div>

                <div class="field span-2">
                    <label for="keterangan">Keterangan</label>
                    <textarea id="keterangan" name="keterangan" class="{{ $textareaClass('keterangan') }}" placeholder="Tuliskan catatan penting mengenai aset ini.">{{ $nilai('keterangan') }}</textarea>
                    @error('keterangan')
                        <p class="error-text">{{ $message }}</p>
                    @enderror
                </div>
            </div>
        </section>

        <div class="form-actions">
            <a href="{{ route('unit-barang.index') }}" class="button button-muted">Batal</a>
            <button type="submit" class="button button-primary" @disabled($modeTambah && $daftarBarang->isEmpty())>{{ $tombol ?? 'Simpan' }}</button>
        </div>
    </div>
</div>
