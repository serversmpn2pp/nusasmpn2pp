@php
    $jamPelajaran = $jamPelajaran ?? null;
    $nilai = fn (string $field, mixed $default = '') => old($field, $jamPelajaran?->{$field} ?? $default);
    $inputClass = fn (string $field) => 'input' . ($errors->has($field) ? ' is-invalid' : '');
    $selectClass = fn (string $field) => 'select' . ($errors->has($field) ? ' is-invalid' : '');
    $textareaClass = fn (string $field) => 'textarea' . ($errors->has($field) ? ' is-invalid' : '');
    $formatJam = function (string $field) use ($nilai) {
        $value = $nilai($field);

        return $value ? substr((string) $value, 0, 5) : '';
    };
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
        <h2 class="panel-title">Status jam</h2>
        <p class="help-text">Jam aktif bisa dipakai saat menyusun jadwal pelajaran.</p>

        <label class="status-toggle">
            <span>
                <span class="form-label" style="margin-bottom:0">Jam aktif</span>
                <span class="help-text">Tersedia untuk jadwal</span>
            </span>
            <input type="hidden" name="aktif" value="0">
            <input type="checkbox" name="aktif" value="1" @checked((bool) $nilai('aktif', true))>
        </label>
    </aside>

    <div class="section-stack">
        <section class="panel panel-pad">
            <h2 class="panel-title">Informasi Jam Pelajaran</h2>
            <div class="form-grid">
                <div class="field">
                    <label for="hari">Hari</label>
                    <select id="hari" name="hari" class="{{ $selectClass('hari') }}" required autofocus>
                        <option value="">Pilih hari</option>
                        @foreach ($daftarHari as $kode => $label)
                            <option value="{{ $kode }}" @selected($nilai('hari') === $kode)>{{ $label }}</option>
                        @endforeach
                    </select>
                    @error('hari')
                        <p class="error-text">{{ $message }}</p>
                    @enderror
                </div>

                <div class="field">
                    <label for="nomor_jam">Nomor jam</label>
                    <input id="nomor_jam" name="nomor_jam" type="number" min="1" max="20" value="{{ $nilai('nomor_jam') }}" class="{{ $inputClass('nomor_jam') }}" required>
                    @error('nomor_jam')
                        <p class="error-text">{{ $message }}</p>
                    @enderror
                </div>

                <div class="field">
                    <label for="label">Label</label>
                    <input id="label" name="label" type="text" value="{{ $nilai('label') }}" class="{{ $inputClass('label') }}" placeholder="Contoh: Jam 1">
                    @error('label')
                        <p class="error-text">{{ $message }}</p>
                    @enderror
                </div>

                <div class="field">
                    <label for="jenis">Jenis slot</label>
                    <select id="jenis" name="jenis" class="{{ $selectClass('jenis') }}" required>
                        @foreach ($daftarJenis as $kode => $label)
                            <option value="{{ $kode }}" @selected($nilai('jenis', 'pelajaran') === $kode)>{{ $label }}</option>
                        @endforeach
                    </select>
                    @error('jenis')
                        <p class="error-text">{{ $message }}</p>
                    @enderror
                </div>

                <div class="field">
                    <label for="jam_mulai">Jam mulai</label>
                    <input id="jam_mulai" name="jam_mulai" type="time" value="{{ $formatJam('jam_mulai') }}" class="{{ $inputClass('jam_mulai') }}" required>
                    @error('jam_mulai')
                        <p class="error-text">{{ $message }}</p>
                    @enderror
                </div>

                <div class="field">
                    <label for="jam_selesai">Jam selesai</label>
                    <input id="jam_selesai" name="jam_selesai" type="time" value="{{ $formatJam('jam_selesai') }}" class="{{ $inputClass('jam_selesai') }}" required>
                    @error('jam_selesai')
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
            <a href="{{ route('jam-pelajaran.index') }}" class="button button-muted">Batal</a>
            <button type="submit" class="button button-primary">{{ $tombol ?? 'Simpan' }}</button>
        </div>
    </div>
</div>
