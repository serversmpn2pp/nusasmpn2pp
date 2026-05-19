@php
    $komponenNilai = $komponenNilai ?? null;
    $nilai = fn (string $field, mixed $default = '') => old($field, $komponenNilai?->{$field} ?? $default);
    $tanggal = function (string $field) use ($nilai) {
        $value = $nilai($field);

        return $value instanceof \Carbon\CarbonInterface ? $value->format('Y-m-d') : $value;
    };
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
        <h2 class="panel-title">Status komponen</h2>
        <p class="help-text">Komponen aktif akan muncul saat guru melakukan input nilai siswa.</p>

        <label class="status-toggle">
            <span>
                <span class="form-label" style="margin-bottom:0">Komponen aktif</span>
                <span class="help-text">Dipakai untuk input nilai</span>
            </span>
            <input type="hidden" name="aktif" value="0">
            <input type="checkbox" name="aktif" value="1" @checked((bool) $nilai('aktif', true))>
        </label>
    </aside>

    <div class="section-stack">
        <section class="panel panel-pad">
            <h2 class="panel-title">Penugasan Guru</h2>
            <div class="form-grid">
                <div class="field span-2">
                    <label for="guru_mata_pelajaran_id">Guru mata pelajaran</label>
                    <select id="guru_mata_pelajaran_id" name="guru_mata_pelajaran_id" class="{{ $selectClass('guru_mata_pelajaran_id') }}" required autofocus>
                        <option value="">Pilih guru mata pelajaran</option>
                        @foreach ($guruMataPelajaran as $item)
                            <option value="{{ $item->id }}" @selected((string) $nilai('guru_mata_pelajaran_id', $guruMataPelajaranDipilih ?? '') === (string) $item->id)>
                                {{ $item->tahunPelajaran?->nama ?: '-' }} - {{ $item->kelas?->nama ?: '-' }} - {{ $item->mataPelajaran?->nama ?: '-' }} - {{ $item->pegawai?->nama_lengkap ?: '-' }}
                            </option>
                        @endforeach
                    </select>
                    @error('guru_mata_pelajaran_id')
                        <p class="error-text">{{ $message }}</p>
                    @enderror
                    <p class="help-text">Komponen nilai dibuat berdasarkan kelas dan mata pelajaran yang diampu.</p>
                </div>
            </div>
        </section>

        <section class="panel panel-pad">
            <h2 class="panel-title">Informasi Komponen</h2>
            <div class="form-grid">
                <div class="field">
                    <label for="semester">Semester</label>
                    <select id="semester" name="semester" class="{{ $selectClass('semester') }}" required>
                        <option value="">Pilih semester</option>
                        <option value="ganjil" @selected($nilai('semester') === 'ganjil')>Ganjil</option>
                        <option value="genap" @selected($nilai('semester') === 'genap')>Genap</option>
                    </select>
                    @error('semester')
                        <p class="error-text">{{ $message }}</p>
                    @enderror
                </div>

                <div class="field">
                    <label for="jenis_komponen">Jenis komponen</label>
                    <select id="jenis_komponen" name="jenis_komponen" class="{{ $selectClass('jenis_komponen') }}" required>
                        <option value="">Pilih jenis</option>
                        <option value="formatif" @selected($nilai('jenis_komponen') === 'formatif')>Formatif</option>
                        <option value="sumatif" @selected($nilai('jenis_komponen') === 'sumatif')>Sumatif</option>
                        <option value="sts" @selected($nilai('jenis_komponen') === 'sts')>STS</option>
                        <option value="sas_saj" @selected($nilai('jenis_komponen') === 'sas_saj')>SAS/SAJ</option>
                    </select>
                    @error('jenis_komponen')
                        <p class="error-text">{{ $message }}</p>
                    @enderror
                </div>

                <div class="field">
                    <label for="nama">Nama komponen</label>
                    <input id="nama" name="nama" type="text" value="{{ $nilai('nama') }}" placeholder="Contoh: Formatif 1 - Tugas Bab 1" class="{{ $inputClass('nama') }}" required>
                    @error('nama')
                        <p class="error-text">{{ $message }}</p>
                    @enderror
                </div>

                <div class="field">
                    <label for="tanggal_penilaian">Tanggal penilaian</label>
                    <input id="tanggal_penilaian" name="tanggal_penilaian" type="date" value="{{ $tanggal('tanggal_penilaian') }}" class="{{ $inputClass('tanggal_penilaian') }}">
                    @error('tanggal_penilaian')
                        <p class="error-text">{{ $message }}</p>
                    @enderror
                </div>

                <div class="field">
                    <label for="urutan">Urutan tampil</label>
                    <input id="urutan" name="urutan" type="number" min="0" max="999" value="{{ $nilai('urutan') }}" placeholder="Contoh: 1" class="{{ $inputClass('urutan') }}">
                    @error('urutan')
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
            <a href="{{ route('komponen-nilai.index') }}" class="button button-muted">Batal</a>
            <button type="submit" class="button button-primary">{{ $tombol ?? 'Simpan' }}</button>
        </div>
    </div>
</div>
