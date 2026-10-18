@php
    $pegawai = $pegawai ?? null;
    $nilai = fn (string $field, mixed $default = '') => old($field, $pegawai?->{$field} ?? $default);
    $tanggal = function (string $field) use ($nilai) {
        $value = $nilai($field);

        return $value instanceof \Carbon\CarbonInterface ? $value->format('Y-m-d') : $value;
    };
    $inputClass = fn (string $field) => 'input' . ($errors->has($field) ? ' is-invalid' : '');
    $textareaClass = fn (string $field) => 'textarea' . ($errors->has($field) ? ' is-invalid' : '');
@endphp

@if ($errors->any())
    <x-ui.alert type="danger" title="Ada data yang perlu diperbaiki.">
        <ul>
            @foreach ($errors->all() as $error)
                <li>{{ $error }}</li>
            @endforeach
        </ul>
    </x-ui.alert>
@endif

<div class="form-shell">
    <aside class="panel panel-pad">
        <x-input-foto-profil
            label="Foto pegawai"
            :foto-url="$pegawai?->foto ? asset('storage/' . $pegawai->foto) : null"
            :inisial="$pegawai?->nama_lengkap ? strtoupper(mb_substr($pegawai->nama_lengkap, 0, 1)) : 'P'"
            :alt="$pegawai?->nama_lengkap ? 'Foto ' . $pegawai->nama_lengkap : 'Pratinjau foto pegawai'"
            :upload-url="$pegawai ? route('pegawai.foto.update', $pegawai) : null"
        />

        <label class="status-toggle">
            <span>
                <span class="form-label" style="margin-bottom:0">Status aktif</span>
                <span class="help-text">Tampil sebagai pegawai aktif</span>
            </span>
            <input type="hidden" name="aktif" value="0">
            <input type="checkbox" name="aktif" value="1" @checked((bool) $nilai('aktif', true))>
        </label>
    </aside>

    <div class="section-stack">
        <section class="panel panel-pad school-data-section">
            <h2 class="panel-title">Identitas Utama</h2>
            <div class="form-grid">
                <div class="field span-2">
                    <label for="nama_lengkap">Nama lengkap</label>
                    <input id="nama_lengkap" name="nama_lengkap" type="text" value="{{ $nilai('nama_lengkap') }}" class="{{ $inputClass('nama_lengkap') }}" required autofocus @error('nama_lengkap') aria-invalid="true" aria-describedby="nama_lengkap-error" @enderror>
                    @error('nama_lengkap')
                        <p id="nama_lengkap-error" class="error-text">{{ $message }}</p>
                    @enderror
                </div>

                <div class="field">
                    <label for="nip">NIP</label>
                    <input id="nip" name="nip" type="text" value="{{ $nilai('nip') }}" class="{{ $inputClass('nip') }}" @error('nip') aria-invalid="true" aria-describedby="nip-error" @enderror>
                    @error('nip')
                        <p id="nip-error" class="error-text">{{ $message }}</p>
                    @enderror
                </div>

                <div class="field">
                    <label for="nuptk">NUPTK</label>
                    <input id="nuptk" name="nuptk" type="text" value="{{ $nilai('nuptk') }}" class="{{ $inputClass('nuptk') }}" @error('nuptk') aria-invalid="true" aria-describedby="nuptk-error" @enderror>
                    @error('nuptk')
                        <p id="nuptk-error" class="error-text">{{ $message }}</p>
                    @enderror
                </div>

                <div class="field">
                    <label for="nik">NIK</label>
                    <input id="nik" name="nik" type="text" value="{{ $nilai('nik') }}" class="{{ $inputClass('nik') }}" @error('nik') aria-invalid="true" aria-describedby="nik-error" @enderror>
                    @error('nik')
                        <p id="nik-error" class="error-text">{{ $message }}</p>
                    @enderror
                </div>

                <div class="field">
                    <label for="jenis_kelamin">Jenis kelamin</label>
                    <select id="jenis_kelamin" name="jenis_kelamin" class="select @error('jenis_kelamin') is-invalid @enderror" @error('jenis_kelamin') aria-invalid="true" aria-describedby="jenis_kelamin-error" @enderror>
                        <option value="">Pilih</option>
                        <option value="L" @selected($nilai('jenis_kelamin') === 'L')>Laki-laki</option>
                        <option value="P" @selected($nilai('jenis_kelamin') === 'P')>Perempuan</option>
                    </select>
                    @error('jenis_kelamin')
                        <p id="jenis_kelamin-error" class="error-text">{{ $message }}</p>
                    @enderror
                </div>
            </div>
        </section>

        <section class="panel panel-pad school-data-section">
            <h2 class="panel-title">Data Pribadi & Kontak</h2>
            <div class="form-grid">
                <div class="field">
                    <label for="tempat_lahir">Tempat lahir</label>
                    <input id="tempat_lahir" name="tempat_lahir" type="text" value="{{ $nilai('tempat_lahir') }}" class="{{ $inputClass('tempat_lahir') }}" @error('tempat_lahir') aria-invalid="true" aria-describedby="tempat_lahir-error" @enderror>
                    @error('tempat_lahir')
                        <p id="tempat_lahir-error" class="error-text">{{ $message }}</p>
                    @enderror
                </div>

                <div class="field">
                    <label for="tanggal_lahir">Tanggal lahir</label>
                    <input id="tanggal_lahir" name="tanggal_lahir" type="date" value="{{ $tanggal('tanggal_lahir') }}" class="{{ $inputClass('tanggal_lahir') }}" @error('tanggal_lahir') aria-invalid="true" aria-describedby="tanggal_lahir-error" @enderror>
                    @error('tanggal_lahir')
                        <p id="tanggal_lahir-error" class="error-text">{{ $message }}</p>
                    @enderror
                </div>

                <div class="field">
                    <label for="email">Email</label>
                    <input id="email" name="email" type="email" value="{{ $nilai('email') }}" class="{{ $inputClass('email') }}" @error('email') aria-invalid="true" aria-describedby="email-error" @enderror>
                    @error('email')
                        <p id="email-error" class="error-text">{{ $message }}</p>
                    @enderror
                </div>

                <div class="field">
                    <label for="no_hp">No. HP</label>
                    <input id="no_hp" name="no_hp" type="text" value="{{ $nilai('no_hp') }}" class="{{ $inputClass('no_hp') }}" @error('no_hp') aria-invalid="true" aria-describedby="no_hp-error" @enderror>
                    @error('no_hp')
                        <p id="no_hp-error" class="error-text">{{ $message }}</p>
                    @enderror
                </div>

                <div class="field span-2">
                    <label for="alamat">Alamat</label>
                    <textarea id="alamat" name="alamat" class="{{ $textareaClass('alamat') }}" @error('alamat') aria-invalid="true" aria-describedby="alamat-error" @enderror>{{ $nilai('alamat') }}</textarea>
                    @error('alamat')
                        <p id="alamat-error" class="error-text">{{ $message }}</p>
                    @enderror
                </div>
            </div>
        </section>

        <section class="panel panel-pad school-data-section">
            <h2 class="panel-title">Kepegawaian</h2>
            <div class="form-grid">
                <div class="field">
                    <label for="jenis_pegawai">Jenis pegawai</label>
                    <input id="jenis_pegawai" name="jenis_pegawai" type="text" value="{{ $nilai('jenis_pegawai') }}" class="{{ $inputClass('jenis_pegawai') }}" list="jenis-pegawai-list" @error('jenis_pegawai') aria-invalid="true" aria-describedby="jenis_pegawai-error" @enderror>
                    <datalist id="jenis-pegawai-list">
                        <option value="Guru">
                        <option value="Tenaga Kependidikan">
                    </datalist>
                    @error('jenis_pegawai')
                        <p id="jenis_pegawai-error" class="error-text">{{ $message }}</p>
                    @enderror
                </div>

                <div class="field">
                    <label for="status_kepegawaian">Status kepegawaian</label>
                    <input id="status_kepegawaian" name="status_kepegawaian" type="text" value="{{ $nilai('status_kepegawaian') }}" class="{{ $inputClass('status_kepegawaian') }}" list="status-kepegawaian-list" @error('status_kepegawaian') aria-invalid="true" aria-describedby="status_kepegawaian-error" @enderror>
                    <datalist id="status-kepegawaian-list">
                        <option value="PNS">
                        <option value="PPPK">
                        <option value="Honor">
                    </datalist>
                    @error('status_kepegawaian')
                        <p id="status_kepegawaian-error" class="error-text">{{ $message }}</p>
                    @enderror
                </div>

                <div class="field">
                    <label for="jabatan_utama">Jabatan utama</label>
                    <input id="jabatan_utama" name="jabatan_utama" type="text" value="{{ $nilai('jabatan_utama') }}" class="{{ $inputClass('jabatan_utama') }}" @error('jabatan_utama') aria-invalid="true" aria-describedby="jabatan_utama-error" @enderror>
                    @error('jabatan_utama')
                        <p id="jabatan_utama-error" class="error-text">{{ $message }}</p>
                    @enderror
                </div>

                <div class="field">
                    <label for="golongan">Golongan</label>
                    <input id="golongan" name="golongan" type="text" value="{{ $nilai('golongan') }}" class="{{ $inputClass('golongan') }}" @error('golongan') aria-invalid="true" aria-describedby="golongan-error" @enderror>
                    @error('golongan')
                        <p id="golongan-error" class="error-text">{{ $message }}</p>
                    @enderror
                </div>

                <div class="field">
                    <label for="tanggal_mulai_kerja">Tanggal mulai kerja</label>
                    <input id="tanggal_mulai_kerja" name="tanggal_mulai_kerja" type="date" value="{{ $tanggal('tanggal_mulai_kerja') }}" class="{{ $inputClass('tanggal_mulai_kerja') }}" @error('tanggal_mulai_kerja') aria-invalid="true" aria-describedby="tanggal_mulai_kerja-error" @enderror>
                    @error('tanggal_mulai_kerja')
                        <p id="tanggal_mulai_kerja-error" class="error-text">{{ $message }}</p>
                    @enderror
                </div>

                <div class="field">
                    <label for="tanggal_mulai_bertugas">Tanggal mulai bertugas</label>
                    <input id="tanggal_mulai_bertugas" name="tanggal_mulai_bertugas" type="date" value="{{ $tanggal('tanggal_mulai_bertugas') }}" class="{{ $inputClass('tanggal_mulai_bertugas') }}" @error('tanggal_mulai_bertugas') aria-invalid="true" aria-describedby="tanggal_mulai_bertugas-error" @enderror>
                    @error('tanggal_mulai_bertugas')
                        <p id="tanggal_mulai_bertugas-error" class="error-text">{{ $message }}</p>
                    @enderror
                </div>

                <div class="field">
                    <label for="sumber_gaji">Sumber gaji</label>
                    <input id="sumber_gaji" name="sumber_gaji" type="text" value="{{ $nilai('sumber_gaji') }}" class="{{ $inputClass('sumber_gaji') }}" @error('sumber_gaji') aria-invalid="true" aria-describedby="sumber_gaji-error" @enderror>
                    @error('sumber_gaji')
                        <p id="sumber_gaji-error" class="error-text">{{ $message }}</p>
                    @enderror
                </div>
            </div>
        </section>

        <section class="panel panel-pad school-data-section">
            <h2 class="panel-title">Pendidikan & Catatan</h2>
            <div class="form-grid">
                <div class="field">
                    <label for="pendidikan_terakhir">Pendidikan terakhir</label>
                    <input id="pendidikan_terakhir" name="pendidikan_terakhir" type="text" value="{{ $nilai('pendidikan_terakhir') }}" class="{{ $inputClass('pendidikan_terakhir') }}" @error('pendidikan_terakhir') aria-invalid="true" aria-describedby="pendidikan_terakhir-error" @enderror>
                    @error('pendidikan_terakhir')
                        <p id="pendidikan_terakhir-error" class="error-text">{{ $message }}</p>
                    @enderror
                </div>

                <div class="field">
                    <label for="jurusan_pendidikan">Jurusan pendidikan</label>
                    <input id="jurusan_pendidikan" name="jurusan_pendidikan" type="text" value="{{ $nilai('jurusan_pendidikan') }}" class="{{ $inputClass('jurusan_pendidikan') }}" @error('jurusan_pendidikan') aria-invalid="true" aria-describedby="jurusan_pendidikan-error" @enderror>
                    @error('jurusan_pendidikan')
                        <p id="jurusan_pendidikan-error" class="error-text">{{ $message }}</p>
                    @enderror
                </div>

                <div class="field">
                    <label for="tahun_lulus">Tahun lulus</label>
                    <input id="tahun_lulus" name="tahun_lulus" type="number" min="1900" max="2100" value="{{ $nilai('tahun_lulus') }}" class="{{ $inputClass('tahun_lulus') }}" @error('tahun_lulus') aria-invalid="true" aria-describedby="tahun_lulus-error" @enderror>
                    @error('tahun_lulus')
                        <p id="tahun_lulus-error" class="error-text">{{ $message }}</p>
                    @enderror
                </div>

                <div class="field span-2">
                    <label for="keterangan">Keterangan</label>
                    <textarea id="keterangan" name="keterangan" class="{{ $textareaClass('keterangan') }}" @error('keterangan') aria-invalid="true" aria-describedby="keterangan-error" @enderror>{{ $nilai('keterangan') }}</textarea>
                    @error('keterangan')
                        <p id="keterangan-error" class="error-text">{{ $message }}</p>
                    @enderror
                </div>
            </div>
        </section>

        <div class="form-actions">
            <a href="{{ route('pegawai.index') }}" class="button button-muted">Batal</a>
            <button type="submit" class="button button-primary">{{ $tombol ?? 'Simpan' }}</button>
        </div>
    </div>
</div>
