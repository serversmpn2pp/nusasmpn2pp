@php
    $kelas = $kelas ?? null;
    $nilai = fn (string $field, mixed $default = '') => old($field, $kelas?->{$field} ?? $default);
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
        <h2 class="panel-title">Status kelas</h2>
        <p class="help-text">Kelas aktif dapat dipakai untuk penempatan siswa dan proses akademik berjalan.</p>

        <label class="status-toggle">
            <span>
                <span class="form-label" style="margin-bottom:0">Kelas aktif</span>
                <span class="help-text">Tampil sebagai kelas yang digunakan</span>
            </span>
            <input type="hidden" name="aktif" value="0">
            <input type="checkbox" name="aktif" value="1" @checked((bool) $nilai('aktif', true))>
        </label>
    </aside>

    <div class="section-stack">
        <section class="panel panel-pad">
            <h2 class="panel-title">Informasi Kelas</h2>
            <div class="form-grid">
                <div class="field">
                    <label for="tahun_pelajaran_id">Tahun pelajaran</label>
                    <select id="tahun_pelajaran_id" name="tahun_pelajaran_id" class="{{ $selectClass('tahun_pelajaran_id') }}" required>
                        <option value="">Pilih tahun pelajaran</option>
                        @foreach ($tahunPelajaran as $item)
                            <option value="{{ $item->id }}" @selected((string) $nilai('tahun_pelajaran_id', $tahunPelajaranDipilih) === (string) $item->id)>
                                {{ $item->nama }}{{ $item->aktif ? ' - aktif' : '' }}
                            </option>
                        @endforeach
                    </select>
                    @error('tahun_pelajaran_id')
                        <p class="error-text">{{ $message }}</p>
                    @enderror
                </div>

                <div class="field">
                    <label for="nama">Nama kelas</label>
                    <input id="nama" name="nama" type="text" value="{{ $nilai('nama') }}" placeholder="Contoh: VII.A" class="{{ $inputClass('nama') }}" required autofocus>
                    @error('nama')
                        <p class="error-text">{{ $message }}</p>
                    @enderror
                </div>

                <div class="field">
                    <label for="tingkat">Tingkat</label>
                    <select id="tingkat" name="tingkat" class="{{ $selectClass('tingkat') }}">
                        <option value="">Pilih</option>
                        <option value="7" @selected((string) $nilai('tingkat') === '7')>VII</option>
                        <option value="8" @selected((string) $nilai('tingkat') === '8')>VIII</option>
                        <option value="9" @selected((string) $nilai('tingkat') === '9')>IX</option>
                    </select>
                    @error('tingkat')
                        <p class="error-text">{{ $message }}</p>
                    @enderror
                </div>

                <div class="field">
                    <label for="kapasitas">Kapasitas</label>
                    <input id="kapasitas" name="kapasitas" type="number" min="1" max="500" value="{{ $nilai('kapasitas') }}" class="{{ $inputClass('kapasitas') }}">
                    @error('kapasitas')
                        <p class="error-text">{{ $message }}</p>
                    @enderror
                </div>

                <div class="field span-2">
                    <label for="wali_kelas_id">Wali kelas</label>
                    <select id="wali_kelas_id" name="wali_kelas_id" class="{{ $selectClass('wali_kelas_id') }}">
                        <option value="">Belum dipilih</option>
                        @foreach ($pegawai as $item)
                            <option value="{{ $item->id }}" @selected((string) $nilai('wali_kelas_id') === (string) $item->id)>
                                {{ $item->nama_lengkap }}{{ $item->jabatan_utama ? ' - ' . $item->jabatan_utama : '' }}
                            </option>
                        @endforeach
                    </select>
                    @error('wali_kelas_id')
                        <p class="error-text">{{ $message }}</p>
                    @enderror
                </div>

                <div class="field span-2">
                    <label for="keterangan">Keterangan</label>
                    <textarea id="keterangan" name="keterangan" class="{{ $textareaClass('keterangan') }}">{{ $nilai('keterangan') }}</textarea>
                    @error('keterangan')
                        <p class="error-text">{{ $message }}</p>
                    @enderror
                </div>
            </div>
        </section>

        <div class="form-actions">
            <a href="{{ route('kelas.index') }}" class="button button-muted">Batal</a>
            <button type="submit" class="button button-primary">{{ $tombol ?? 'Simpan' }}</button>
        </div>
    </div>
</div>
