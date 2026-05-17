@php
    $tahunPelajaran = $tahunPelajaran ?? null;
    $nilai = fn (string $field, mixed $default = '') => old($field, $tahunPelajaran?->{$field} ?? $default);
    $tanggal = function (string $field) use ($nilai) {
        $value = $nilai($field);

        return $value instanceof \Carbon\CarbonInterface ? $value->format('Y-m-d') : $value;
    };
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
        <h2 class="panel-title">Status tahun pelajaran</h2>
        <p class="help-text">Jika status aktif dipilih, tahun pelajaran lain otomatis menjadi nonaktif.</p>

        <label class="status-toggle">
            <span>
                <span class="form-label" style="margin-bottom:0">Tahun pelajaran aktif</span>
                <span class="help-text">Dipakai sebagai acuan kelas berjalan</span>
            </span>
            <input type="hidden" name="aktif" value="0">
            <input type="checkbox" name="aktif" value="1" @checked((bool) $nilai('aktif', false))>
        </label>
    </aside>

    <div class="section-stack">
        <section class="panel panel-pad">
            <h2 class="panel-title">Informasi Tahun Pelajaran</h2>
            <div class="form-grid">
                <div class="field span-2">
                    <label for="nama">Nama tahun pelajaran</label>
                    <input id="nama" name="nama" type="text" value="{{ $nilai('nama') }}" placeholder="Contoh: 2026/2027" class="{{ $inputClass('nama') }}" required autofocus>
                    @error('nama')
                        <p class="error-text">{{ $message }}</p>
                    @enderror
                </div>

                <div class="field">
                    <label for="tanggal_mulai">Tanggal mulai</label>
                    <input id="tanggal_mulai" name="tanggal_mulai" type="date" value="{{ $tanggal('tanggal_mulai') }}" class="{{ $inputClass('tanggal_mulai') }}">
                    @error('tanggal_mulai')
                        <p class="error-text">{{ $message }}</p>
                    @enderror
                </div>

                <div class="field">
                    <label for="tanggal_selesai">Tanggal selesai</label>
                    <input id="tanggal_selesai" name="tanggal_selesai" type="date" value="{{ $tanggal('tanggal_selesai') }}" class="{{ $inputClass('tanggal_selesai') }}">
                    @error('tanggal_selesai')
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
            <a href="{{ route('tahun-pelajaran.index') }}" class="button button-muted">Batal</a>
            <button type="submit" class="button button-primary">{{ $tombol ?? 'Simpan' }}</button>
        </div>
    </div>
</div>
