@php
    $pengaturanAbsensiPegawai = $pengaturanAbsensiPegawai ?? null;
    $nilai = fn (string $field, mixed $default = '') => old($field, $pengaturanAbsensiPegawai?->{$field} ?? $default);
    $jam = function (string $field, string $default = '') use ($nilai) {
        $value = $nilai($field, $default);

        return $value ? substr((string) $value, 0, 5) : '';
    };
    $inputClass = fn (string $field) => 'input' . ($errors->has($field) ? ' is-invalid' : '');
    $selectClass = fn (string $field) => 'select' . ($errors->has($field) ? ' is-invalid' : '');
    $textareaClass = fn (string $field) => 'textarea' . ($errors->has($field) ? ' is-invalid' : '');
    $jenisPegawaiOptions = $jenisPegawaiOptions ?? collect();
    $pegawaiOptions = $pegawaiOptions ?? collect();
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
        <p class="help-text">Jadwal aktif akan dipakai saat modul scan presensi pegawai dibuat.</p>

        <label class="status-toggle">
            <span>
                <span class="form-label" style="margin-bottom:0">Jadwal aktif</span>
                <span class="help-text">Bisa dinonaktifkan tanpa menghapus data</span>
            </span>
            <input type="hidden" name="aktif" value="0">
            <input type="checkbox" name="aktif" value="1" @checked((bool) $nilai('aktif', true))>
        </label>

        <div style="margin-top: 18px;">
            <p class="help-text">
                Prioritas jadwal nanti: pegawai tertentu, lalu jenis pegawai, lalu semua pegawai.
            </p>
        </div>
    </aside>

    <div class="section-stack">
        <section class="panel panel-pad">
            <h2 class="panel-title">Sasaran Jadwal</h2>
            <div class="form-grid">
                <div class="field span-2">
                    <label for="nama_jadwal">Nama jadwal</label>
                    <input id="nama_jadwal" name="nama_jadwal" type="text" value="{{ $nilai('nama_jadwal') }}" class="{{ $inputClass('nama_jadwal') }}" placeholder="Contoh: Jadwal guru Senin" required autofocus>
                    @error('nama_jadwal')
                        <p class="error-text">{{ $message }}</p>
                    @enderror
                </div>

                <div class="field">
                    <label for="cakupan">Cakupan</label>
                    <select id="cakupan" name="cakupan" class="{{ $selectClass('cakupan') }}" required>
                        @foreach (\App\Models\PengaturanAbsensiPegawai::DAFTAR_CAKUPAN as $key => $label)
                            <option value="{{ $key }}" @selected($nilai('cakupan', 'semua') === $key)>{{ $label }}</option>
                        @endforeach
                    </select>
                    @error('cakupan')
                        <p class="error-text">{{ $message }}</p>
                    @enderror
                </div>

                <div class="field">
                    <label for="hari">Hari presensi</label>
                    <select id="hari" name="hari" class="{{ $selectClass('hari') }}" required>
                        <option value="">Pilih hari</option>
                        @foreach (\App\Models\PengaturanAbsensiPegawai::DAFTAR_HARI as $key => $item)
                            <option value="{{ $key }}" @selected($nilai('hari') === $key)>{{ $item['label'] }}</option>
                        @endforeach
                    </select>
                    @error('hari')
                        <p class="error-text">{{ $message }}</p>
                    @enderror
                </div>

                <div class="field span-2" data-cakupan-field="jenis_pegawai">
                    <label for="jenis_pegawai">Jenis pegawai</label>
                    <input id="jenis_pegawai" name="jenis_pegawai" type="text" value="{{ $nilai('jenis_pegawai') }}" class="{{ $inputClass('jenis_pegawai') }}" list="jenis-pegawai-absensi-list" placeholder="Contoh: Guru">
                    <datalist id="jenis-pegawai-absensi-list">
                        @foreach (['Guru', 'Tenaga Kependidikan', 'Satpam', 'Petugas Kebersihan'] as $defaultJenisPegawai)
                            <option value="{{ $defaultJenisPegawai }}">
                        @endforeach
                        @foreach ($jenisPegawaiOptions as $jenisPegawai)
                            <option value="{{ $jenisPegawai }}">
                        @endforeach
                    </datalist>
                    @error('jenis_pegawai')
                        <p class="error-text">{{ $message }}</p>
                    @enderror
                </div>

                <div class="field span-2" data-cakupan-field="pegawai">
                    <label for="pegawai_id">Pegawai</label>
                    <select id="pegawai_id" name="pegawai_id" class="{{ $selectClass('pegawai_id') }}">
                        <option value="">Pilih pegawai</option>
                        @foreach ($pegawaiOptions as $pegawai)
                            <option value="{{ $pegawai->id }}" @selected((string) $nilai('pegawai_id') === (string) $pegawai->id)>
                                {{ $pegawai->nama_lengkap }}{{ $pegawai->nip ? ' - ' . $pegawai->nip : '' }}{{ $pegawai->jenis_pegawai ? ' (' . $pegawai->jenis_pegawai . ')' : '' }}
                            </option>
                        @endforeach
                    </select>
                    @error('pegawai_id')
                        <p class="error-text">{{ $message }}</p>
                    @enderror
                </div>
            </div>
        </section>

        <section class="panel panel-pad">
            <h2 class="panel-title">Jam Masuk</h2>
            <div class="form-grid">
                <div class="field">
                    <label for="jam_scan_masuk_mulai">Mulai scan masuk</label>
                    <input id="jam_scan_masuk_mulai" name="jam_scan_masuk_mulai" type="time" value="{{ $jam('jam_scan_masuk_mulai', '06:30') }}" class="{{ $inputClass('jam_scan_masuk_mulai') }}" required>
                    @error('jam_scan_masuk_mulai')
                        <p class="error-text">{{ $message }}</p>
                    @enderror
                </div>

                <div class="field">
                    <label for="jam_masuk">Jam masuk resmi</label>
                    <input id="jam_masuk" name="jam_masuk" type="time" value="{{ $jam('jam_masuk', '07:15') }}" class="{{ $inputClass('jam_masuk') }}" required>
                    @error('jam_masuk')
                        <p class="error-text">{{ $message }}</p>
                    @enderror
                </div>

                <div class="field">
                    <label for="jam_scan_masuk_selesai">Tutup scan masuk</label>
                    <input id="jam_scan_masuk_selesai" name="jam_scan_masuk_selesai" type="time" value="{{ $jam('jam_scan_masuk_selesai', '08:00') }}" class="{{ $inputClass('jam_scan_masuk_selesai') }}" required>
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
                    <input id="jam_pulang" name="jam_pulang" type="time" value="{{ $jam('jam_pulang', '14:15') }}" class="{{ $inputClass('jam_pulang') }}" required>
                    @error('jam_pulang')
                        <p class="error-text">{{ $message }}</p>
                    @enderror
                </div>

                <div class="field">
                    <label for="jam_scan_pulang_selesai">Tutup scan pulang</label>
                    <input id="jam_scan_pulang_selesai" name="jam_scan_pulang_selesai" type="time" value="{{ $jam('jam_scan_pulang_selesai', '16:00') }}" class="{{ $inputClass('jam_scan_pulang_selesai') }}" required>
                    @error('jam_scan_pulang_selesai')
                        <p class="error-text">{{ $message }}</p>
                    @enderror
                </div>

                <div class="field span-2">
                    <label for="keterangan">Keterangan</label>
                    <textarea id="keterangan" name="keterangan" class="{{ $textareaClass('keterangan') }}" placeholder="Contoh: Jadwal khusus guru piket">{{ $nilai('keterangan') }}</textarea>
                    @error('keterangan')
                        <p class="error-text">{{ $message }}</p>
                    @enderror
                </div>
            </div>
        </section>

        <div class="form-actions">
            <a href="{{ route('pengaturan-absensi-pegawai.index') }}" class="button button-muted">Batal</a>
            <button type="submit" class="button button-primary">{{ $tombol ?? 'Simpan' }}</button>
        </div>
    </div>
</div>

<script>
    document.addEventListener('DOMContentLoaded', function () {
        const cakupan = document.getElementById('cakupan');
        const fields = document.querySelectorAll('[data-cakupan-field]');

        function tampilkanFieldSesuaiCakupan() {
            fields.forEach(function (field) {
                field.style.display = field.dataset.cakupanField === cakupan.value ? '' : 'none';
            });
        }

        if (cakupan) {
            tampilkanFieldSesuaiCakupan();
            cakupan.addEventListener('change', tampilkanFieldSesuaiCakupan);
        }
    });
</script>
