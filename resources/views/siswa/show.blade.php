    @extends('layouts.app')

@section('title', 'Detail Siswa - NUSA')

@section('content')
    @php
        $teks = fn (mixed $value) => filled($value) ? $value : '-';
        $tanggal = fn (mixed $value) => $value ? $value->format('d-m-Y') : '-';
    @endphp

    <div class="page-header">
        <div>
            <p class="eyebrow">Siswa</p>
            <h1 class="page-title">Detail siswa</h1>
        </div>

        <div class="actions">
            <a href="{{ route('siswa.index') }}" class="button button-muted">Kembali</a>
            @izin('siswa.kelola')
                <a href="{{ route('siswa.edit', $siswa) }}" class="button button-dark">Edit</a>
            @endizin
        </div>
    </div>

    @if (session('berhasil'))
        <div class="alert">{{ session('berhasil') }}</div>
    @endif

    <div class="detail-shell">
        <aside class="panel panel-pad">
            <div class="detail-profile">
                <div class="avatar avatar-lg">
                    @if ($siswa->foto)
                        <img src="{{ asset('storage/' . $siswa->foto) }}" alt="Foto {{ $siswa->nama_lengkap }}">
                    @else
                        {{ strtoupper(mb_substr($siswa->nama_lengkap, 0, 1)) }}
                    @endif
                </div>

                <h2>{{ $siswa->nama_lengkap }}</h2>
                <p>{{ $siswa->nis ? 'NIS ' . $siswa->nis : 'Siswa' }}</p>

                <div style="margin-top: 16px;">
                    @if ($siswa->aktif)
                        <span class="badge badge-active">Aktif</span>
                    @else
                        <span class="badge badge-inactive">Nonaktif</span>
                    @endif
                </div>
            </div>

            @izin('siswa.kelola')
                @if ($siswa->aktif)
                    <form action="{{ route('siswa.destroy', $siswa) }}" method="POST" style="margin-top: 24px;" onsubmit="return confirm('Nonaktifkan siswa ini?')">
                        @csrf
                        @method('DELETE')
                        <button type="submit" class="button button-danger button-full">Nonaktifkan</button>
                    </form>
                @endif
            @endizin
        </aside>

        <div class="section-stack">
            <section class="panel panel-pad">
                <h2 class="panel-title">Identitas Utama</h2>
                <dl class="detail-grid">
                    <div class="detail-item">
                        <dt>NIS</dt>
                        <dd>{{ $teks($siswa->nis) }}</dd>
                    </div>
                    <div class="detail-item">
                        <dt>NISN</dt>
                        <dd>{{ $teks($siswa->nisn) }}</dd>
                    </div>
                    <div class="detail-item">
                        <dt>NIK</dt>
                        <dd>{{ $teks($siswa->nik) }}</dd>
                    </div>
                    <div class="detail-item">
                        <dt>Jenis kelamin</dt>
                        <dd>{{ $siswa->jenis_kelamin === 'L' ? 'Laki-laki' : ($siswa->jenis_kelamin === 'P' ? 'Perempuan' : '-') }}</dd>
                    </div>
                </dl>
            </section>

            <section class="panel panel-pad">
                <h2 class="panel-title">Data Pribadi</h2>
                <dl class="detail-grid">
                    <div class="detail-item">
                        <dt>Tempat lahir</dt>
                        <dd>{{ $teks($siswa->tempat_lahir) }}</dd>
                    </div>
                    <div class="detail-item">
                        <dt>Tanggal lahir</dt>
                        <dd>{{ $tanggal($siswa->tanggal_lahir) }}</dd>
                    </div>
                    <div class="detail-item">
                        <dt>Agama</dt>
                        <dd>{{ $teks($siswa->agama) }}</dd>
                    </div>
                    <div class="detail-item">
                        <dt>Sekolah asal</dt>
                        <dd>{{ $teks($siswa->sekolah_asal) }}</dd>
                    </div>
                </dl>
            </section>

            <section class="panel panel-pad">
                <h2 class="panel-title">Data Keluarga</h2>
                <dl class="detail-grid">
                    <div class="detail-item">
                        <dt>Status dalam keluarga</dt>
                        <dd>{{ $teks($siswa->status_dalam_keluarga) }}</dd>
                    </div>
                    <div class="detail-item">
                        <dt>Anak ke</dt>
                        <dd>{{ $teks($siswa->anak_ke) }}</dd>
                    </div>
                    <div class="detail-item">
                        <dt>Nama ayah</dt>
                        <dd>{{ $teks($siswa->nama_ayah) }}</dd>
                    </div>
                    <div class="detail-item">
                        <dt>Nama ibu</dt>
                        <dd>{{ $teks($siswa->nama_ibu) }}</dd>
                    </div>
                    <div class="detail-item">
                        <dt>Nomor WA ayah</dt>
                        <dd>{{ $teks($siswa->nomor_wa_ayah) }}</dd>
                    </div>
                    <div class="detail-item">
                        <dt>Nomor WA ibu</dt>
                        <dd>{{ $teks($siswa->nomor_wa_ibu) }}</dd>
                    </div>
                    <div class="detail-item">
                        <dt>Pekerjaan ayah</dt>
                        <dd>{{ $teks($siswa->pekerjaan_ayah) }}</dd>
                    </div>
                    <div class="detail-item">
                        <dt>Pekerjaan ibu</dt>
                        <dd>{{ $teks($siswa->pekerjaan_ibu) }}</dd>
                    </div>
                    <div class="detail-item">
                        <dt>Nama wali lain</dt>
                        <dd>{{ $teks($siswa->nama_wali) }}</dd>
                    </div>
                    <div class="detail-item">
                        <dt>Hubungan wali</dt>
                        <dd>{{ $teks($siswa->hubungan_wali) }}</dd>
                    </div>
                    <div class="detail-item">
                        <dt>Nomor WA wali lain</dt>
                        <dd>{{ $teks($siswa->nomor_wa_wali) }}</dd>
                    </div>
                    <div class="detail-item">
                        <dt>Tujuan notifikasi absensi</dt>
                        <dd>{{ $teks($siswa->kontak_absensi_utama ? str($siswa->kontak_absensi_utama)->headline()->toString() : null) }}</dd>
                    </div>
                </dl>
            </section>

            <section class="panel panel-pad">
                <h2 class="panel-title">Alamat & Catatan</h2>
                <dl class="detail-grid">
                    <div class="detail-item span-2">
                        <dt>Alamat</dt>
                        <dd style="white-space: pre-line;">{{ $teks($siswa->alamat) }}</dd>
                    </div>
                    <div class="detail-item span-2">
                        <dt>Keterangan</dt>
                        <dd style="white-space: pre-line;">{{ $teks($siswa->keterangan) }}</dd>
                    </div>
                </dl>
            </section>
        </div>
    </div>
@endsection
