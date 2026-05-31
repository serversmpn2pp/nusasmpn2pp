@extends('layouts.app')

@section('title', 'Profil Saya - NUSA')

@section('content')
    @php
        $teks = fn (mixed $value) => filled($value) ? $value : '-';
        $nilai = fn (string $field, mixed $default = '') => old($field, $pegawai?->{$field} ?? $default);
        $tanggal = function (string $field) use ($nilai) {
            $value = $nilai($field);

            return $value instanceof \Carbon\CarbonInterface ? $value->format('Y-m-d') : $value;
        };
        $inputClass = fn (string $field) => 'input' . ($errors->has($field) ? ' is-invalid' : '');
        $textareaClass = fn (string $field) => 'textarea' . ($errors->has($field) ? ' is-invalid' : '');
    @endphp

    <div class="page-header">
        <div>
            <p class="eyebrow">Akun Pegawai</p>
            <h1 class="page-title">Profil saya</h1>
        </div>

        <a href="{{ route('kata-sandi.edit') }}" class="button button-muted">Ganti password</a>
    </div>

    @if (session('berhasil'))
        <div class="alert">{{ session('berhasil') }}</div>
    @endif

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

    <form action="{{ route('profil-pegawai.update') }}" method="POST" enctype="multipart/form-data">
        @csrf
        @method('PUT')

        <div class="form-shell">
            <aside class="panel panel-pad">
                <div class="detail-profile">
                    <div class="avatar avatar-lg">
                        @if ($pegawai->foto)
                            <img src="{{ asset('storage/' . $pegawai->foto) }}" alt="Foto {{ $pegawai->nama_lengkap }}">
                        @else
                            {{ strtoupper(mb_substr($pegawai->nama_lengkap, 0, 1)) }}
                        @endif
                    </div>

                    <h2>{{ $pegawai->nama_lengkap }}</h2>
                    <p>{{ $teks($pegawai->jabatan_utama ?: $pegawai->jenis_pegawai) }}</p>

                    <div style="margin-top: 16px;">
                        @if ($pegawai->aktif)
                            <span class="badge badge-active">Aktif</span>
                        @else
                            <span class="badge badge-inactive">Nonaktif</span>
                        @endif
                    </div>
                </div>

                <div style="margin-top: 22px;">
                    <label for="foto" class="form-label">Foto profil</label>
                    <input id="foto" name="foto" type="file" accept="image/png,image/jpeg,image/webp" class="file-input">
                    @error('foto')
                        <p class="error-text">{{ $message }}</p>
                    @enderror
                    <p class="help-text">JPG, PNG, atau WebP. Maksimal 5 MB.</p>
                </div>

                <dl class="quick-facts" style="margin-top: 18px;">
                    <div>
                        <dt>NIP</dt>
                        <dd>{{ $teks($pegawai->nip) }}</dd>
                    </div>
                    <div>
                        <dt>Status</dt>
                        <dd>{{ $teks($pegawai->status_kepegawaian) }}</dd>
                    </div>
                    <div>
                        <dt>Jenis</dt>
                        <dd>{{ $teks($pegawai->jenis_pegawai) }}</dd>
                    </div>
                    <div>
                        <dt>Golongan</dt>
                        <dd>{{ $teks($pegawai->golongan) }}</dd>
                    </div>
                </dl>

                <p class="help-text" style="margin-top: 14px;">Data kepegawaian utama dikunci dan dapat diperbarui oleh admin/operator.</p>
            </aside>

            <div class="section-stack">
                <section class="panel panel-pad">
                    <h2 class="panel-title">Identitas</h2>
                    <div class="form-grid">
                        <div class="field span-2">
                            <label for="nama_lengkap">Nama lengkap</label>
                            <input id="nama_lengkap" name="nama_lengkap" type="text" value="{{ $nilai('nama_lengkap') }}" class="{{ $inputClass('nama_lengkap') }}" required autofocus>
                            @error('nama_lengkap')
                                <p class="error-text">{{ $message }}</p>
                            @enderror
                        </div>

                        <div class="field">
                            <label for="nuptk">NUPTK</label>
                            <input id="nuptk" name="nuptk" type="text" value="{{ $nilai('nuptk') }}" class="{{ $inputClass('nuptk') }}">
                            @error('nuptk')
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
                    <h2 class="panel-title">Data Pribadi & Kontak</h2>
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
                            <label for="email">Email</label>
                            <input id="email" name="email" type="email" value="{{ $nilai('email') }}" class="{{ $inputClass('email') }}">
                            @error('email')
                                <p class="error-text">{{ $message }}</p>
                            @enderror
                        </div>

                        <div class="field">
                            <label for="no_hp">No. HP</label>
                            <input id="no_hp" name="no_hp" type="text" value="{{ $nilai('no_hp') }}" class="{{ $inputClass('no_hp') }}">
                            @error('no_hp')
                                <p class="error-text">{{ $message }}</p>
                            @enderror
                        </div>

                        <div class="field span-2">
                            <label for="alamat">Alamat</label>
                            <textarea id="alamat" name="alamat" class="{{ $textareaClass('alamat') }}">{{ $nilai('alamat') }}</textarea>
                            @error('alamat')
                                <p class="error-text">{{ $message }}</p>
                            @enderror
                        </div>
                    </div>
                </section>

                <section class="panel panel-pad">
                    <h2 class="panel-title">Pendidikan & Catatan</h2>
                    <div class="form-grid">
                        <div class="field">
                            <label for="pendidikan_terakhir">Pendidikan terakhir</label>
                            <input id="pendidikan_terakhir" name="pendidikan_terakhir" type="text" value="{{ $nilai('pendidikan_terakhir') }}" class="{{ $inputClass('pendidikan_terakhir') }}">
                            @error('pendidikan_terakhir')
                                <p class="error-text">{{ $message }}</p>
                            @enderror
                        </div>

                        <div class="field">
                            <label for="jurusan_pendidikan">Jurusan pendidikan</label>
                            <input id="jurusan_pendidikan" name="jurusan_pendidikan" type="text" value="{{ $nilai('jurusan_pendidikan') }}" class="{{ $inputClass('jurusan_pendidikan') }}">
                            @error('jurusan_pendidikan')
                                <p class="error-text">{{ $message }}</p>
                            @enderror
                        </div>

                        <div class="field">
                            <label for="tahun_lulus">Tahun lulus</label>
                            <input id="tahun_lulus" name="tahun_lulus" type="number" min="1900" max="2100" value="{{ $nilai('tahun_lulus') }}" class="{{ $inputClass('tahun_lulus') }}">
                            @error('tahun_lulus')
                                <p class="error-text">{{ $message }}</p>
                            @enderror
                        </div>

                        <div class="field span-2">
                            <label for="keterangan">Keterangan tambahan</label>
                            <textarea id="keterangan" name="keterangan" class="{{ $textareaClass('keterangan') }}">{{ $nilai('keterangan') }}</textarea>
                            @error('keterangan')
                                <p class="error-text">{{ $message }}</p>
                            @enderror
                        </div>
                    </div>
                </section>

                <div class="form-actions">
                    <button type="submit" class="button button-primary">Simpan profil</button>
                </div>
            </div>
        </div>
    </form>
@endsection
