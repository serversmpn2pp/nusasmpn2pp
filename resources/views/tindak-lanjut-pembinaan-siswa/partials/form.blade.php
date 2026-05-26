@php
    $tindakLanjutPembinaanSiswa = $tindakLanjutPembinaanSiswa ?? null;
    $nilai = fn (string $field, mixed $default = '') => old($field, $tindakLanjutPembinaanSiswa?->{$field} ?? $default);
    $inputClass = fn (string $field) => 'input' . ($errors->has($field) ? ' is-invalid' : '');
    $selectClass = fn (string $field) => 'select' . ($errors->has($field) ? ' is-invalid' : '');
    $textareaClass = fn (string $field) => 'textarea' . ($errors->has($field) ? ' is-invalid' : '');

    $tanggalValue = old(
        'tanggal_tindak_lanjut',
        filled($tindakLanjutPembinaanSiswa?->tanggal_tindak_lanjut)
            ? \Illuminate\Support\Carbon::parse($tindakLanjutPembinaanSiswa->tanggal_tindak_lanjut)->format('Y-m-d')
            : now()->toDateString(),
    );
    $waktuValue = old('waktu_tindak_lanjut', $tindakLanjutPembinaanSiswa?->waktuTindakLanjutRingkas());
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
        <h2 class="panel-title">Status akhir</h2>
        <p class="help-text">Status laporan akan mengikuti pilihan setelah tindak lanjut ini disimpan.</p>

        <div style="margin-top: 18px; border: 1px solid var(--line); border-radius: 8px; padding: 12px;">
            <p class="person-name">{{ $laporanPembinaanSiswa->siswa?->nama_lengkap ?: '-' }}</p>
            <p class="person-meta">{{ $laporanPembinaanSiswa->nomor_laporan }}</p>
            <p class="person-meta">{{ $laporanPembinaanSiswa->kelas?->nama ?: 'Kelas belum tercatat' }}</p>
        </div>

        <div class="field" style="margin-top: 18px;">
            <label for="status_laporan">Status laporan</label>
            <select id="status_laporan" name="status_laporan" class="{{ $selectClass('status_laporan') }}" required>
                @foreach ($daftarStatusLaporan as $kode => $label)
                    <option value="{{ $kode }}" @selected((string) $nilai('status_laporan', 'diproses') === (string) $kode)>{{ $label }}</option>
                @endforeach
            </select>
            @error('status_laporan')
                <p class="error-text">{{ $message }}</p>
            @enderror
        </div>
    </aside>

    <div class="section-stack">
        <section class="panel panel-pad">
            <h2 class="panel-title">Data tindak lanjut</h2>

            <div class="form-grid">
                <div class="field">
                    <label for="tanggal_tindak_lanjut">Tanggal</label>
                    <input id="tanggal_tindak_lanjut" name="tanggal_tindak_lanjut" type="date" value="{{ $tanggalValue }}" class="{{ $inputClass('tanggal_tindak_lanjut') }}" required>
                    @error('tanggal_tindak_lanjut')
                        <p class="error-text">{{ $message }}</p>
                    @enderror
                </div>

                <div class="field">
                    <label for="waktu_tindak_lanjut">Waktu</label>
                    <input id="waktu_tindak_lanjut" name="waktu_tindak_lanjut" type="time" value="{{ $waktuValue }}" class="{{ $inputClass('waktu_tindak_lanjut') }}">
                    @error('waktu_tindak_lanjut')
                        <p class="error-text">{{ $message }}</p>
                    @enderror
                </div>

                <div class="field">
                    <label for="jenis_tindak_lanjut">Jenis tindak lanjut</label>
                    <select id="jenis_tindak_lanjut" name="jenis_tindak_lanjut" class="{{ $selectClass('jenis_tindak_lanjut') }}" required>
                        @foreach ($daftarJenisTindakLanjut as $kode => $label)
                            <option value="{{ $kode }}" @selected((string) $nilai('jenis_tindak_lanjut', 'konseling_siswa') === (string) $kode)>{{ $label }}</option>
                        @endforeach
                    </select>
                    @error('jenis_tindak_lanjut')
                        <p class="error-text">{{ $message }}</p>
                    @enderror
                </div>

                <div class="field">
                    <label for="petugas_pegawai_id">Petugas</label>
                    <select id="petugas_pegawai_id" name="petugas_pegawai_id" class="{{ $selectClass('petugas_pegawai_id') }}">
                        <option value="">Belum ditentukan</option>
                        @foreach ($daftarPegawai as $pegawai)
                            <option value="{{ $pegawai->id }}" @selected((string) $nilai('petugas_pegawai_id') === (string) $pegawai->id)>
                                {{ $pegawai->nama_lengkap }}{{ $pegawai->nip ? ' - NIP ' . $pegawai->nip : '' }}
                            </option>
                        @endforeach
                    </select>
                    @error('petugas_pegawai_id')
                        <p class="error-text">{{ $message }}</p>
                    @enderror
                </div>

                <div class="field span-2">
                    <label for="pihak_terlibat">Pihak terlibat</label>
                    <input id="pihak_terlibat" name="pihak_terlibat" type="text" value="{{ $nilai('pihak_terlibat') }}" placeholder="Contoh: siswa, orang tua, wali kelas, guru mapel" class="{{ $inputClass('pihak_terlibat') }}">
                    @error('pihak_terlibat')
                        <p class="error-text">{{ $message }}</p>
                    @enderror
                </div>
            </div>
        </section>

        <section class="panel panel-pad">
            <h2 class="panel-title">Catatan penanganan</h2>

            <div class="form-grid">
                <div class="field span-2">
                    <label for="ringkasan">Ringkasan tindakan</label>
                    <textarea id="ringkasan" name="ringkasan" class="{{ $textareaClass('ringkasan') }}" placeholder="Tuliskan proses penanganan yang dilakukan." required>{{ $nilai('ringkasan') }}</textarea>
                    @error('ringkasan')
                        <p class="error-text">{{ $message }}</p>
                    @enderror
                </div>

                <div class="field span-2">
                    <label for="hasil">Hasil / kesepakatan</label>
                    <textarea id="hasil" name="hasil" class="{{ $textareaClass('hasil') }}" placeholder="Contoh: siswa memahami kesalahan, orang tua menyetujui pendampingan, mediasi selesai.">{{ $nilai('hasil') }}</textarea>
                    @error('hasil')
                        <p class="error-text">{{ $message }}</p>
                    @enderror
                </div>

                <div class="field span-2">
                    <label for="rencana_lanjutan">Rencana lanjutan</label>
                    <textarea id="rencana_lanjutan" name="rencana_lanjutan" class="{{ $textareaClass('rencana_lanjutan') }}" placeholder="Tuliskan pemantauan atau tindak lanjut berikutnya bila ada.">{{ $nilai('rencana_lanjutan') }}</textarea>
                    @error('rencana_lanjutan')
                        <p class="error-text">{{ $message }}</p>
                    @enderror
                </div>

                <div class="field span-2">
                    <label for="catatan_rahasia">Catatan rahasia</label>
                    <textarea id="catatan_rahasia" name="catatan_rahasia" class="{{ $textareaClass('catatan_rahasia') }}" placeholder="Catatan internal BK/pimpinan. Boleh dikosongkan.">{{ $nilai('catatan_rahasia') }}</textarea>
                    @error('catatan_rahasia')
                        <p class="error-text">{{ $message }}</p>
                    @enderror
                </div>
            </div>
        </section>

        <div class="form-actions">
            <a href="{{ route('laporan-pembinaan-siswa.show', $laporanPembinaanSiswa) }}" class="button button-muted">Batal</a>
            <button type="submit" class="button button-primary">{{ $tombol ?? 'Simpan' }}</button>
        </div>
    </div>
</div>
