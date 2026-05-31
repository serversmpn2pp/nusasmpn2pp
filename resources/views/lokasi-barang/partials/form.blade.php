@php
    $lokasiBarang = $lokasiBarang ?? null;
    $nilai = fn (string $field, mixed $default = '') => old($field, $lokasiBarang?->{$field} ?? $default);
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

<div class="form-shell">
    <aside class="panel panel-pad">
        <h2 class="panel-title">Status lokasi</h2>
        <p class="help-text">Lokasi aktif dapat dipilih sebagai tempat penyimpanan barang.</p>

        <label class="status-toggle">
            <span>
                <span class="form-label" style="margin-bottom:0">Lokasi aktif</span>
                <span class="help-text">Tersedia pada master inventaris</span>
            </span>
            <input type="hidden" name="aktif" value="0">
            <input type="checkbox" name="aktif" value="1" @checked((bool) $nilai('aktif', true))>
        </label>
    </aside>

    <div class="section-stack">
        <section class="panel panel-pad">
            <h2 class="panel-title">Informasi Lokasi</h2>

            <div class="form-grid">
                <div class="field">
                    <label for="nama">Nama lokasi</label>
                    <input id="nama" name="nama" type="text" value="{{ $nilai('nama') }}" placeholder="Contoh: Laboratorium Informatika" class="{{ $inputClass('nama') }}" required autofocus>
                    @error('nama')
                        <p class="error-text">{{ $message }}</p>
                    @enderror
                </div>

                <div class="field">
                    <label for="kode">Kode lokasi</label>
                    <input id="kode" name="kode" type="text" value="{{ $nilai('kode') }}" placeholder="Contoh: LAB_INF" class="{{ $inputClass('kode') }}" required>
                    <p class="help-text">Kode akan dirapikan menjadi huruf besar tanpa spasi.</p>
                    @error('kode')
                        <p class="error-text">{{ $message }}</p>
                    @enderror
                </div>

                <div class="field">
                    <label for="jenis">Jenis lokasi</label>
                    <select id="jenis" name="jenis" class="{{ $selectClass('jenis') }}" required>
                        <option value="">Pilih jenis lokasi</option>
                        @foreach ($daftarJenis as $nilaiJenis => $labelJenis)
                            <option value="{{ $nilaiJenis }}" @selected($nilai('jenis') === $nilaiJenis)>{{ $labelJenis }}</option>
                        @endforeach
                    </select>
                    @error('jenis')
                        <p class="error-text">{{ $message }}</p>
                    @enderror
                </div>

                <div class="field">
                    <label for="penanggung_jawab_pegawai_id">Penanggung jawab</label>
                    <select id="penanggung_jawab_pegawai_id" name="penanggung_jawab_pegawai_id" class="{{ $selectClass('penanggung_jawab_pegawai_id') }}">
                        <option value="">Belum ditentukan</option>
                        @foreach ($daftarPegawai as $pegawai)
                            <option value="{{ $pegawai->id }}" @selected((string) $nilai('penanggung_jawab_pegawai_id') === (string) $pegawai->id)>{{ $pegawai->nama_lengkap }}</option>
                        @endforeach
                    </select>
                    @error('penanggung_jawab_pegawai_id')
                        <p class="error-text">{{ $message }}</p>
                    @enderror
                </div>

                <div class="field span-2">
                    <label for="deskripsi">Deskripsi</label>
                    <textarea id="deskripsi" name="deskripsi" class="{{ $textareaClass('deskripsi') }}" placeholder="Tuliskan keterangan lokasi jika diperlukan.">{{ $nilai('deskripsi') }}</textarea>
                    @error('deskripsi')
                        <p class="error-text">{{ $message }}</p>
                    @enderror
                </div>
            </div>
        </section>

        <div class="form-actions">
            <a href="{{ route('lokasi-barang.index') }}" class="button button-muted">Batal</a>
            <button type="submit" class="button button-primary">{{ $tombol ?? 'Simpan' }}</button>
        </div>
    </div>
</div>
