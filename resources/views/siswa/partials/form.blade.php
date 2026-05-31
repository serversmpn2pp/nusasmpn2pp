@php
    $siswa = $siswa ?? null;
    $nilai = fn (string $field, mixed $default = '') => old($field, $siswa?->{$field} ?? $default);
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
        <div class="avatar-upload">
            <div class="avatar avatar-lg">
                @if ($siswa?->foto)
                    <img src="{{ asset('storage/' . $siswa->foto) }}" alt="Foto {{ $siswa->nama_lengkap }}">
                @else
                    {{ $siswa?->nama_lengkap ? strtoupper(mb_substr($siswa->nama_lengkap, 0, 1)) : 'S' }}
                @endif
            </div>

            <div>
                <label for="foto" class="form-label">Foto siswa</label>
                <input id="foto" name="foto" type="file" accept="image/png,image/jpeg,image/webp" class="file-input">
                @error('foto')
                    <p class="error-text">{{ $message }}</p>
                @enderror
                <p class="help-text">JPG, PNG, atau WebP. Maksimal 5 MB.</p>
            </div>
        </div>

        <label class="status-toggle">
            <span>
                <span class="form-label" style="margin-bottom:0">Status aktif</span>
                <span class="help-text">Tampil sebagai siswa aktif</span>
            </span>
            <input type="hidden" name="aktif" value="0">
            <input type="checkbox" name="aktif" value="1" @checked((bool) $nilai('aktif', true))>
        </label>
    </aside>

    <div class="section-stack">
        <section class="panel panel-pad">
            <h2 class="panel-title">Identitas Utama</h2>
            <div class="form-grid">
                <div class="field span-2">
                    <label for="nama_lengkap">Nama lengkap</label>
                    <input id="nama_lengkap" name="nama_lengkap" type="text" value="{{ $nilai('nama_lengkap') }}" class="{{ $inputClass('nama_lengkap') }}" required autofocus>
                    @error('nama_lengkap')
                        <p class="error-text">{{ $message }}</p>
                    @enderror
                </div>

                <div class="field">
                    <label for="nis">NIS</label>
                    <input id="nis" name="nis" type="text" value="{{ $nilai('nis') }}" class="{{ $inputClass('nis') }}">
                    @error('nis')
                        <p class="error-text">{{ $message }}</p>
                    @enderror
                </div>

                <div class="field">
                    <label for="nisn">NISN</label>
                    <input id="nisn" name="nisn" type="text" value="{{ $nilai('nisn') }}" class="{{ $inputClass('nisn') }}">
                    @error('nisn')
                        <p class="error-text">{{ $message }}</p>
                    @enderror
                </div>

                <div class="field">
                    <label for="nik">NIK</label>
                    <input id="nik" name="nik" type="text" value="{{ $nilai('nik') }}" class="{{ $inputClass('nik') }}">
                    @error('nik')
                        <p class="error-text">{{ $message }}</p>
                    @enderror
                </div>

                <div class="field">
                    <label for="jenis_kelamin">Jenis kelamin</label>
                    <select id="jenis_kelamin" name="jenis_kelamin" class="select @error('jenis_kelamin') is-invalid @enderror">
                        <option value="">Pilih</option>
                        <option value="L" @selected($nilai('jenis_kelamin') === 'L')>Laki-laki</option>
                        <option value="P" @selected($nilai('jenis_kelamin') === 'P')>Perempuan</option>
                    </select>
                    @error('jenis_kelamin')
                        <p class="error-text">{{ $message }}</p>
                    @enderror
                </div>
            </div>
        </section>

        <section class="panel panel-pad">
            <h2 class="panel-title">Data Pribadi</h2>
            <div class="form-grid">
                <div class="field">
                    <label for="tempat_lahir">Tempat lahir</label>
                    <input id="tempat_lahir" name="tempat_lahir" type="text" value="{{ $nilai('tempat_lahir') }}" class="{{ $inputClass('tempat_lahir') }}">
                    @error('tempat_lahir')
                        <p class="error-text">{{ $message }}</p>
                    @enderror
                </div>

                <div class="field">
                    <label for="tanggal_lahir">Tanggal lahir</label>
                    <input id="tanggal_lahir" name="tanggal_lahir" type="date" value="{{ $tanggal('tanggal_lahir') }}" class="{{ $inputClass('tanggal_lahir') }}">
                    @error('tanggal_lahir')
                        <p class="error-text">{{ $message }}</p>
                    @enderror
                </div>

                <div class="field">
                    <label for="agama">Agama</label>
                    <input id="agama" name="agama" type="text" value="{{ $nilai('agama') }}" class="{{ $inputClass('agama') }}" list="agama-list">
                    <datalist id="agama-list">
                        <option value="Islam">
                        <option value="Kristen">
                        <option value="Katolik">
                        <option value="Hindu">
                        <option value="Buddha">
                        <option value="Konghucu">
                    </datalist>
                    @error('agama')
                        <p class="error-text">{{ $message }}</p>
                    @enderror
                </div>

                <div class="field">
                    <label for="sekolah_asal">Sekolah asal</label>
                    <input id="sekolah_asal" name="sekolah_asal" type="text" value="{{ $nilai('sekolah_asal') }}" class="{{ $inputClass('sekolah_asal') }}">
                    @error('sekolah_asal')
                        <p class="error-text">{{ $message }}</p>
                    @enderror
                </div>
            </div>
        </section>

        <section class="panel panel-pad">
            <h2 class="panel-title">Data Keluarga</h2>
            <div class="form-grid">
                <div class="field">
                    <label for="status_dalam_keluarga">Status dalam keluarga</label>
                    <input id="status_dalam_keluarga" name="status_dalam_keluarga" type="text" value="{{ $nilai('status_dalam_keluarga') }}" class="{{ $inputClass('status_dalam_keluarga') }}" list="status-keluarga-list">
                    <datalist id="status-keluarga-list">
                        <option value="Anak Kandung">
                        <option value="Anak Angkat">
                        <option value="Anak Tiri">
                    </datalist>
                    @error('status_dalam_keluarga')
                        <p class="error-text">{{ $message }}</p>
                    @enderror
                </div>

                <div class="field">
                    <label for="anak_ke">Anak ke</label>
                    <input id="anak_ke" name="anak_ke" type="number" min="1" max="30" value="{{ $nilai('anak_ke') }}" class="{{ $inputClass('anak_ke') }}">
                    @error('anak_ke')
                        <p class="error-text">{{ $message }}</p>
                    @enderror
                </div>

                <div class="field">
                    <label for="nama_ayah">Nama ayah</label>
                    <input id="nama_ayah" name="nama_ayah" type="text" value="{{ $nilai('nama_ayah') }}" class="{{ $inputClass('nama_ayah') }}">
                    @error('nama_ayah')
                        <p class="error-text">{{ $message }}</p>
                    @enderror
                </div>

                <div class="field">
                    <label for="nama_ibu">Nama ibu</label>
                    <input id="nama_ibu" name="nama_ibu" type="text" value="{{ $nilai('nama_ibu') }}" class="{{ $inputClass('nama_ibu') }}">
                    @error('nama_ibu')
                        <p class="error-text">{{ $message }}</p>
                    @enderror
                </div>

                <div class="field">
                    <label for="nomor_wa_ayah">Nomor WA ayah</label>
                    <input id="nomor_wa_ayah" name="nomor_wa_ayah" type="text" value="{{ $nilai('nomor_wa_ayah') }}" class="{{ $inputClass('nomor_wa_ayah') }}" placeholder="08xxxxxxxxxx">
                    @error('nomor_wa_ayah')
                        <p class="error-text">{{ $message }}</p>
                    @enderror
                </div>

                <div class="field">
                    <label for="nomor_wa_ibu">Nomor WA ibu</label>
                    <input id="nomor_wa_ibu" name="nomor_wa_ibu" type="text" value="{{ $nilai('nomor_wa_ibu') }}" class="{{ $inputClass('nomor_wa_ibu') }}" placeholder="08xxxxxxxxxx">
                    @error('nomor_wa_ibu')
                        <p class="error-text">{{ $message }}</p>
                    @enderror
                </div>

                <div class="field">
                    <label for="pekerjaan_ayah">Pekerjaan ayah</label>
                    <input id="pekerjaan_ayah" name="pekerjaan_ayah" type="text" value="{{ $nilai('pekerjaan_ayah') }}" class="{{ $inputClass('pekerjaan_ayah') }}">
                    @error('pekerjaan_ayah')
                        <p class="error-text">{{ $message }}</p>
                    @enderror
                </div>

                <div class="field">
                    <label for="pekerjaan_ibu">Pekerjaan ibu</label>
                    <input id="pekerjaan_ibu" name="pekerjaan_ibu" type="text" value="{{ $nilai('pekerjaan_ibu') }}" class="{{ $inputClass('pekerjaan_ibu') }}">
                    @error('pekerjaan_ibu')
                        <p class="error-text">{{ $message }}</p>
                    @enderror
                </div>

                <div class="field">
                    <label for="nama_wali">Nama wali lain</label>
                    <input id="nama_wali" name="nama_wali" type="text" value="{{ $nilai('nama_wali') }}" class="{{ $inputClass('nama_wali') }}">
                    @error('nama_wali')
                        <p class="error-text">{{ $message }}</p>
                    @enderror
                </div>

                <div class="field">
                    <label for="hubungan_wali">Hubungan wali</label>
                    <input id="hubungan_wali" name="hubungan_wali" type="text" value="{{ $nilai('hubungan_wali') }}" class="{{ $inputClass('hubungan_wali') }}" placeholder="Contoh: paman, kakak, nenek">
                    @error('hubungan_wali')
                        <p class="error-text">{{ $message }}</p>
                    @enderror
                </div>

                <div class="field">
                    <label for="nomor_wa_wali">Nomor WA wali lain</label>
                    <input id="nomor_wa_wali" name="nomor_wa_wali" type="text" value="{{ $nilai('nomor_wa_wali') }}" class="{{ $inputClass('nomor_wa_wali') }}" placeholder="08xxxxxxxxxx">
                    @error('nomor_wa_wali')
                        <p class="error-text">{{ $message }}</p>
                    @enderror
                </div>

                <div class="field">
                    <label for="kontak_absensi_utama">Tujuan notifikasi absensi</label>
                    <select id="kontak_absensi_utama" name="kontak_absensi_utama" class="select @error('kontak_absensi_utama') is-invalid @enderror">
                        <option value="">Otomatis dari nomor yang tersedia</option>
                        <option value="ayah" @selected($nilai('kontak_absensi_utama') === 'ayah')>Ayah</option>
                        <option value="ibu" @selected($nilai('kontak_absensi_utama') === 'ibu')>Ibu</option>
                        <option value="wali" @selected($nilai('kontak_absensi_utama') === 'wali')>Wali lain</option>
                    </select>
                    @error('kontak_absensi_utama')
                        <p class="error-text">{{ $message }}</p>
                    @enderror
                    <p class="help-text">Jika kosong, sistem memilih nomor ayah, lalu ibu, lalu wali lain.</p>
                </div>
            </div>
        </section>

        <section class="panel panel-pad">
            <h2 class="panel-title">Alamat & Catatan</h2>
            <div class="form-grid">
                <div class="field span-2">
                    <label for="alamat">Alamat</label>
                    <textarea id="alamat" name="alamat" class="{{ $textareaClass('alamat') }}">{{ $nilai('alamat') }}</textarea>
                    @error('alamat')
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
            <a href="{{ route('siswa.index') }}" class="button button-muted">Batal</a>
            <button type="submit" class="button button-primary">{{ $tombol ?? 'Simpan' }}</button>
        </div>
    </div>
</div>
