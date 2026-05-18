@php
    $mataPelajaran = $mataPelajaran ?? null;
    $nilai = fn (string $field, mixed $default = '') => old($field, $mataPelajaran?->{$field} ?? $default);
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
        <h2 class="panel-title">Status mapel</h2>
        <p class="help-text">Mata pelajaran aktif dapat dipakai saat penugasan guru mapel dan pengolahan nilai.</p>

        <label class="status-toggle">
            <span>
                <span class="form-label" style="margin-bottom:0">Mata pelajaran aktif</span>
                <span class="help-text">Tampil dalam proses akademik</span>
            </span>
            <input type="hidden" name="aktif" value="0">
            <input type="checkbox" name="aktif" value="1" @checked((bool) $nilai('aktif', true))>
        </label>
    </aside>

    <div class="section-stack">
        <section class="panel panel-pad">
            <h2 class="panel-title">Informasi Mata Pelajaran</h2>
            <div class="form-grid">
                <div class="field">
                    <label for="kode">Kode mapel</label>
                    <input id="kode" name="kode" type="text" value="{{ $nilai('kode') }}" placeholder="Contoh: MTK" class="{{ $inputClass('kode') }}" autofocus>
                    @error('kode')
                        <p class="error-text">{{ $message }}</p>
                    @enderror
                </div>

                <div class="field">
                    <label for="nama">Nama mata pelajaran</label>
                    <input id="nama" name="nama" type="text" value="{{ $nilai('nama') }}" placeholder="Contoh: Matematika" class="{{ $inputClass('nama') }}" required>
                    @error('nama')
                        <p class="error-text">{{ $message }}</p>
                    @enderror
                </div>

                <div class="field">
                    <label for="kelompok">Kelompok</label>
                    <select id="kelompok" name="kelompok" class="{{ $selectClass('kelompok') }}">
                        <option value="">Belum dipilih</option>
                        @foreach (['Umum', 'Agama dan Budi Pekerti', 'Muatan Lokal', 'Pilihan', 'Pengembangan Diri'] as $kelompok)
                            <option value="{{ $kelompok }}" @selected($nilai('kelompok') === $kelompok)>{{ $kelompok }}</option>
                        @endforeach
                    </select>
                    @error('kelompok')
                        <p class="error-text">{{ $message }}</p>
                    @enderror
                </div>

                <div class="field">
                    <label for="tingkat">Tingkat khusus</label>
                    <select id="tingkat" name="tingkat" class="{{ $selectClass('tingkat') }}">
                        <option value="">Semua tingkat</option>
                        <option value="7" @selected((string) $nilai('tingkat') === '7')>VII</option>
                        <option value="8" @selected((string) $nilai('tingkat') === '8')>VIII</option>
                        <option value="9" @selected((string) $nilai('tingkat') === '9')>IX</option>
                    </select>
                    @error('tingkat')
                        <p class="error-text">{{ $message }}</p>
                    @enderror
                </div>

                <div class="field">
                    <label for="kkm">KKM/KKTP</label>
                    <input id="kkm" name="kkm" type="number" min="0" max="100" value="{{ $nilai('kkm') }}" placeholder="Contoh: 75" class="{{ $inputClass('kkm') }}">
                    @error('kkm')
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
            <a href="{{ route('mata-pelajaran.index') }}" class="button button-muted">Batal</a>
            <button type="submit" class="button button-primary">{{ $tombol ?? 'Simpan' }}</button>
        </div>
    </div>
</div>
