@php
    $pengaturanAbsensi = $pengaturanAbsensi ?? null;
    $nilai = fn (string $field, mixed $default = '') => old($field, $pengaturanAbsensi?->{$field} ?? $default);
    $jam = function (string $field, string $default = '') use ($nilai) {
        $value = $nilai($field, $default);

        return $value ? substr((string) $value, 0, 5) : '';
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
        <h2 class="panel-title">Status jadwal</h2>
        <p class="help-text">Pengaturan aktif akan dipakai saat sistem menentukan status hadir, terlambat, pulang normal, atau pulang cepat.</p>

        <label class="status-toggle">
            <span>
                <span class="form-label" style="margin-bottom:0">Jadwal aktif</span>
                <span class="help-text">Dipakai untuk hari terpilih</span>
            </span>
            <input type="hidden" name="aktif" value="0">
            <input type="checkbox" name="aktif" value="1" @checked((bool) $nilai('aktif', true))>
        </label>
    </aside>

    <div class="section-stack">
        <section class="panel panel-pad">
            <h2 class="panel-title">Hari</h2>
            <div class="form-grid">
                <div class="field span-2">
                    <label for="hari">Hari absensi</label>
                    <select id="hari" name="hari" class="{{ $selectClass('hari') }}" required autofocus>
                        <option value="">Pilih hari</option>
                        @foreach (\App\Models\PengaturanAbsensi::DAFTAR_HARI as $key => $item)
                            <option value="{{ $key }}" @selected($nilai('hari') === $key)>{{ $item['label'] }}</option>
                        @endforeach
                    </select>
                    @error('hari')
                        <p class="error-text">{{ $message }}</p>
                    @enderror
                    <p class="help-text">Satu hari hanya memiliki satu pengaturan. Jika jam berubah, edit pengaturan hari tersebut.</p>
                </div>
            </div>
        </section>

        <section class="panel panel-pad">
            <h2 class="panel-title">Jam Masuk</h2>
            <div class="form-grid">
                <div class="field">
                    <label for="jam_scan_masuk_mulai">Mulai scan masuk</label>
                    <input id="jam_scan_masuk_mulai" name="jam_scan_masuk_mulai" type="time" value="{{ $jam('jam_scan_masuk_mulai', '06:00') }}" class="{{ $inputClass('jam_scan_masuk_mulai') }}" required>
                    @error('jam_scan_masuk_mulai')
                        <p class="error-text">{{ $message }}</p>
                    @enderror
                </div>

                <div class="field">
                    <label for="jam_masuk">Jam masuk resmi</label>
                    <input id="jam_masuk" name="jam_masuk" type="time" value="{{ $jam('jam_masuk', '07:00') }}" class="{{ $inputClass('jam_masuk') }}" required>
                    @error('jam_masuk')
                        <p class="error-text">{{ $message }}</p>
                    @enderror
                </div>

                <div class="field">
                    <label for="jam_scan_masuk_selesai">Tutup scan masuk</label>
                    <input id="jam_scan_masuk_selesai" name="jam_scan_masuk_selesai" type="time" value="{{ $jam('jam_scan_masuk_selesai', '07:30') }}" class="{{ $inputClass('jam_scan_masuk_selesai') }}" required>
                    @error('jam_scan_masuk_selesai')
                        <p class="error-text">{{ $message }}</p>
                    @enderror
                </div>
            </div>
        </section>

        <section class="panel panel-pad">
            <h2 class="panel-title">Jam Pulang</h2>
            <div class="form-grid">
                <div class="field">
                    <label for="jam_scan_pulang_mulai">Mulai scan pulang</label>
                    <input id="jam_scan_pulang_mulai" name="jam_scan_pulang_mulai" type="time" value="{{ $jam('jam_scan_pulang_mulai', '14:00') }}" class="{{ $inputClass('jam_scan_pulang_mulai') }}" required>
                    @error('jam_scan_pulang_mulai')
                        <p class="error-text">{{ $message }}</p>
                    @enderror
                </div>

                <div class="field">
                    <label for="jam_pulang">Jam pulang resmi</label>
                    <input id="jam_pulang" name="jam_pulang" type="time" value="{{ $jam('jam_pulang', '14:10') }}" class="{{ $inputClass('jam_pulang') }}" required>
                    @error('jam_pulang')
                        <p class="error-text">{{ $message }}</p>
                    @enderror
                </div>

                <div class="field">
                    <label for="jam_scan_pulang_selesai">Tutup scan pulang</label>
                    <input id="jam_scan_pulang_selesai" name="jam_scan_pulang_selesai" type="time" value="{{ $jam('jam_scan_pulang_selesai', '15:00') }}" class="{{ $inputClass('jam_scan_pulang_selesai') }}" required>
                    @error('jam_scan_pulang_selesai')
                        <p class="error-text">{{ $message }}</p>
                    @enderror
                </div>

                <div class="field span-2">
                    <label for="keterangan">Keterangan</label>
                    <textarea id="keterangan" name="keterangan" class="{{ $textareaClass('keterangan') }}" placeholder="Contoh: Jadwal Jumat pulang lebih awal">{{ $nilai('keterangan') }}</textarea>
                    @error('keterangan')
                        <p class="error-text">{{ $message }}</p>
                    @enderror
                </div>
            </div>
        </section>

        <div class="form-actions">
            <a href="{{ route('pengaturan-absensi.index') }}" class="button button-muted">Batal</a>
            <button type="submit" class="button button-primary">{{ $tombol ?? 'Simpan' }}</button>
        </div>
    </div>
</div>
