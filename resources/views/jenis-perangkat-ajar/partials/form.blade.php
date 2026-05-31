@php
    $jenisPerangkatAjar = $jenisPerangkatAjar ?? null;
    $nilai = fn (string $field, mixed $default = '') => old($field, $jenisPerangkatAjar?->{$field} ?? $default);
    $inputClass = fn (string $field) => 'input' . ($errors->has($field) ? ' is-invalid' : '');
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
        <h2 class="panel-title">Penggunaan dokumen</h2>
        <p class="help-text">Jenis yang aktif akan tersedia dalam daftar unggahan perangkat ajar guru.</p>

        <label class="status-toggle">
            <span>
                <span class="form-label" style="margin-bottom:0">Jenis perangkat aktif</span>
                <span class="help-text">Tampil pada daftar unggahan guru</span>
            </span>
            <input type="hidden" name="aktif" value="0">
            <input type="checkbox" name="aktif" value="1" @checked((bool) $nilai('aktif', true))>
        </label>

        <label class="status-toggle" style="margin-top: 12px;">
            <span>
                <span class="form-label" style="margin-bottom:0">Wajib diunggah</span>
                <span class="help-text">Dihitung dalam progres kelengkapan guru</span>
            </span>
            <input type="hidden" name="wajib" value="0">
            <input type="checkbox" name="wajib" value="1" @checked((bool) $nilai('wajib', true))>
        </label>
    </aside>

    <div class="section-stack">
        <section class="panel panel-pad">
            <h2 class="panel-title">Informasi Jenis Perangkat</h2>

            <div class="form-grid">
                <div class="field">
                    <label for="nama">Nama dokumen</label>
                    <input id="nama" name="nama" type="text" value="{{ $nilai('nama') }}" placeholder="Contoh: Modul Ajar" class="{{ $inputClass('nama') }}" required autofocus>
                    @error('nama')
                        <p class="error-text">{{ $message }}</p>
                    @enderror
                </div>

                <div class="field">
                    <label for="kode">Kode dokumen</label>
                    <input id="kode" name="kode" type="text" value="{{ $nilai('kode') }}" placeholder="Contoh: MODUL_AJAR" class="{{ $inputClass('kode') }}" required>
                    <p class="help-text">Kode akan dirapikan menjadi huruf besar tanpa spasi.</p>
                    @error('kode')
                        <p class="error-text">{{ $message }}</p>
                    @enderror
                </div>

                <div class="field">
                    <label for="urutan">Urutan tampil</label>
                    <input id="urutan" name="urutan" type="number" min="0" max="999" value="{{ $nilai('urutan', 0) }}" placeholder="Contoh: 1" class="{{ $inputClass('urutan') }}">
                    @error('urutan')
                        <p class="error-text">{{ $message }}</p>
                    @enderror
                </div>

                <div class="field span-2">
                    <label for="deskripsi">Deskripsi</label>
                    <textarea id="deskripsi" name="deskripsi" class="{{ $textareaClass('deskripsi') }}" placeholder="Tuliskan penjelasan singkat agar guru memahami dokumen yang perlu diunggah.">{{ $nilai('deskripsi') }}</textarea>
                    @error('deskripsi')
                        <p class="error-text">{{ $message }}</p>
                    @enderror
                </div>
            </div>
        </section>

        <div class="form-actions">
            <a href="{{ route('jenis-perangkat-ajar.index') }}" class="button button-muted">Batal</a>
            <button type="submit" class="button button-primary">{{ $tombol ?? 'Simpan' }}</button>
        </div>
    </div>
</div>
