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
                    <label for="hari">Hari presensi</label>
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
                    <label for="jam_scan_pulang_mulai" id="labelMulaiPulang">Mulai scan pulang</label>
                    <input id="jam_scan_pulang_mulai" name="jam_scan_pulang_mulai" type="time" value="{{ $jam('jam_scan_pulang_mulai', '14:00') }}" class="{{ $inputClass('jam_scan_pulang_mulai') }}" required>
                    @error('jam_scan_pulang_mulai')
                        <p class="error-text">{{ $message }}</p>
                    @enderror
                </div>

                <div class="field">
                    <label for="jam_pulang" id="labelJamPulang">Jam pulang resmi</label>
                    <input id="jam_pulang" name="jam_pulang" type="time" value="{{ $jam('jam_pulang', '14:10') }}" class="{{ $inputClass('jam_pulang') }}" required>
                    @error('jam_pulang')
                        <p class="error-text">{{ $message }}</p>
                    @enderror
                </div>

                <div class="field">
                    <label for="jam_scan_pulang_selesai" id="labelTutupPulang">Tutup scan pulang</label>
                    <input id="jam_scan_pulang_selesai" name="jam_scan_pulang_selesai" type="time" value="{{ $jam('jam_scan_pulang_selesai', '15:00') }}" class="{{ $inputClass('jam_scan_pulang_selesai') }}" required>
                    @error('jam_scan_pulang_selesai')
                        <p class="error-text">{{ $message }}</p>
                    @enderror
                </div>

                <div class="field span-2" id="opsiPulangJumat" hidden>
                    <label class="status-toggle friday-toggle">
                        <span>
                            <span class="form-label" style="margin-bottom:0">Bedakan jam pulang Jumat</span>
                            <span class="help-text">Siswi dapat scan lebih awal. Siswa laki-laki menunggu sampai jadwal setelah salat Jumat.</span>
                        </span>
                        <input type="hidden" name="pulang_jumat_dibedakan" value="0">
                        <input id="pulang_jumat_dibedakan" type="checkbox" name="pulang_jumat_dibedakan" value="1" @checked((bool) $nilai('pulang_jumat_dibedakan', false))>
                    </label>
                </div>

                <div class="field span-2 friday-schedule" id="jadwalPulangPerempuan" hidden>
                    <div class="friday-schedule-head">
                        <div>
                            <p class="form-label">Jadwal pulang siswi</p>
                            <p class="help-text">Berlaku khusus hari Jumat. Data jenis kelamin yang belum lengkap mengikuti jadwal siswa laki-laki.</p>
                        </div>
                        <span class="badge badge-active">Khusus Jumat</span>
                    </div>

                    <div class="form-grid friday-schedule-grid">
                        <div class="field">
                            <label for="jam_scan_pulang_perempuan_mulai">Mulai scan siswi</label>
                            <input id="jam_scan_pulang_perempuan_mulai" name="jam_scan_pulang_perempuan_mulai" type="time" value="{{ $jam('jam_scan_pulang_perempuan_mulai', '11:50') }}" class="{{ $inputClass('jam_scan_pulang_perempuan_mulai') }}">
                            @error('jam_scan_pulang_perempuan_mulai')
                                <p class="error-text">{{ $message }}</p>
                            @enderror
                        </div>

                        <div class="field">
                            <label for="jam_pulang_perempuan">Jam pulang resmi siswi</label>
                            <input id="jam_pulang_perempuan" name="jam_pulang_perempuan" type="time" value="{{ $jam('jam_pulang_perempuan', '11:50') }}" class="{{ $inputClass('jam_pulang_perempuan') }}">
                            @error('jam_pulang_perempuan')
                                <p class="error-text">{{ $message }}</p>
                            @enderror
                        </div>

                        <div class="field">
                            <label for="jam_scan_pulang_perempuan_selesai">Tutup scan siswi</label>
                            <input id="jam_scan_pulang_perempuan_selesai" name="jam_scan_pulang_perempuan_selesai" type="time" value="{{ $jam('jam_scan_pulang_perempuan_selesai', '14:00') }}" class="{{ $inputClass('jam_scan_pulang_perempuan_selesai') }}">
                            @error('jam_scan_pulang_perempuan_selesai')
                                <p class="error-text">{{ $message }}</p>
                            @enderror
                        </div>
                    </div>
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

<style>
    .friday-toggle {
        border: 1px solid #cbd9e8;
        background: #f7fafc;
    }

    .friday-schedule {
        border-top: 1px solid #d8e2ec;
        padding-top: 18px;
    }

    .friday-schedule-head {
        display: flex;
        align-items: flex-start;
        justify-content: space-between;
        gap: 16px;
        margin-bottom: 16px;
    }

    .friday-schedule-head p {
        margin: 0;
    }

    .friday-schedule-grid {
        grid-template-columns: repeat(3, minmax(0, 1fr));
    }

    @media (max-width: 760px) {
        .friday-schedule-head {
            align-items: stretch;
            flex-direction: column;
        }

        .friday-schedule-head .badge {
            align-self: flex-start;
        }

        .friday-schedule-grid {
            grid-template-columns: 1fr;
        }
    }
</style>

<script>
    (() => {
        const hari = document.getElementById('hari');
        const opsi = document.getElementById('opsiPulangJumat');
        const pembeda = document.getElementById('pulang_jumat_dibedakan');
        const jadwalPerempuan = document.getElementById('jadwalPulangPerempuan');
        const inputPerempuan = jadwalPerempuan.querySelectorAll('input[type="time"]');
        const labelMulai = document.getElementById('labelMulaiPulang');
        const labelResmi = document.getElementById('labelJamPulang');
        const labelTutup = document.getElementById('labelTutupPulang');

        function sinkronkanJadwalJumat() {
            const hariJumat = hari.value === 'jumat';
            const dibedakan = hariJumat && pembeda.checked;

            opsi.hidden = ! hariJumat;
            jadwalPerempuan.hidden = ! dibedakan;
            inputPerempuan.forEach((input) => {
                input.disabled = ! dibedakan;
                input.required = dibedakan;
            });

            labelMulai.textContent = dibedakan ? 'Mulai scan siswa laki-laki' : 'Mulai scan pulang';
            labelResmi.textContent = dibedakan ? 'Jam pulang resmi siswa laki-laki' : 'Jam pulang resmi';
            labelTutup.textContent = dibedakan ? 'Tutup scan siswa laki-laki' : 'Tutup scan pulang';
        }

        hari.addEventListener('change', sinkronkanJadwalJumat);
        pembeda.addEventListener('change', sinkronkanJadwalJumat);
        sinkronkanJadwalJumat();
    })();
</script>
