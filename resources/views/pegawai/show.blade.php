@extends('layouts.app')

@section('title', 'Detail Pegawai - NUSA')

@section('content')
    @php
        $teks = fn (mixed $value) => filled($value) ? $value : '-';
        $tanggal = fn (mixed $value) => $value ? $value->format('d-m-Y') : '-';
    @endphp

    <div class="page-header">
        <div>
            <p class="eyebrow">Pegawai</p>
            <h1 class="page-title">Detail pegawai</h1>
        </div>

        <div class="actions">
            <a href="{{ route('pegawai.index') }}" class="button button-muted">Kembali</a>
            @izin('pegawai.kelola')
                <a href="{{ route('pegawai.edit', $pegawai) }}" class="button button-dark">Edit</a>
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

            @izin('pegawai.kelola')
                @if ($pegawai->aktif)
                    <form action="{{ route('pegawai.destroy', $pegawai) }}" method="POST" style="margin-top: 24px;" onsubmit="return confirm('Nonaktifkan pegawai ini?')">
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
                        <dt>NIP</dt>
                        <dd>{{ $teks($pegawai->nip) }}</dd>
                    </div>
                    <div class="detail-item">
                        <dt>NUPTK</dt>
                        <dd>{{ $teks($pegawai->nuptk) }}</dd>
                    </div>
                    <div class="detail-item">
                        <dt>NIK</dt>
                        <dd>{{ $teks($pegawai->nik) }}</dd>
                    </div>
                    <div class="detail-item">
                        <dt>Jenis kelamin</dt>
                        <dd>{{ $pegawai->jenis_kelamin === 'L' ? 'Laki-laki' : ($pegawai->jenis_kelamin === 'P' ? 'Perempuan' : '-') }}</dd>
                    </div>
                </dl>
            </section>

            <section class="panel panel-pad">
                <h2 class="panel-title">Data Pribadi & Kontak</h2>
                <dl class="detail-grid">
                    <div class="detail-item">
                        <dt>Tempat lahir</dt>
                        <dd>{{ $teks($pegawai->tempat_lahir) }}</dd>
                    </div>
                    <div class="detail-item">
                        <dt>Tanggal lahir</dt>
                        <dd>{{ $tanggal($pegawai->tanggal_lahir) }}</dd>
                    </div>
                    <div class="detail-item">
                        <dt>Email</dt>
                        <dd>{{ $teks($pegawai->email) }}</dd>
                    </div>
                    <div class="detail-item">
                        <dt>No. HP</dt>
                        <dd>{{ $teks($pegawai->no_hp) }}</dd>
                    </div>
                    <div class="detail-item span-2">
                        <dt>Alamat</dt>
                        <dd style="white-space: pre-line;">{{ $teks($pegawai->alamat) }}</dd>
                    </div>
                </dl>
            </section>

            <section class="panel panel-pad">
                <h2 class="panel-title">Kepegawaian</h2>
                <dl class="detail-grid">
                    <div class="detail-item">
                        <dt>Jenis pegawai</dt>
                        <dd>{{ $teks($pegawai->jenis_pegawai) }}</dd>
                    </div>
                    <div class="detail-item">
                        <dt>Status kepegawaian</dt>
                        <dd>{{ $teks($pegawai->status_kepegawaian) }}</dd>
                    </div>
                    <div class="detail-item">
                        <dt>Jabatan utama</dt>
                        <dd>{{ $teks($pegawai->jabatan_utama) }}</dd>
                    </div>
                    <div class="detail-item">
                        <dt>Golongan</dt>
                        <dd>{{ $teks($pegawai->golongan) }}</dd>
                    </div>
                    <div class="detail-item">
                        <dt>Tanggal mulai kerja</dt>
                        <dd>{{ $tanggal($pegawai->tanggal_mulai_kerja) }}</dd>
                    </div>
                    <div class="detail-item">
                        <dt>Tanggal mulai bertugas</dt>
                        <dd>{{ $tanggal($pegawai->tanggal_mulai_bertugas) }}</dd>
                    </div>
                    <div class="detail-item">
                        <dt>Sumber gaji</dt>
                        <dd>{{ $teks($pegawai->sumber_gaji) }}</dd>
                    </div>
                </dl>
            </section>

            <section class="panel panel-pad">
                <h2 class="panel-title">Pendidikan & Catatan</h2>
                <dl class="detail-grid">
                    <div class="detail-item">
                        <dt>Pendidikan terakhir</dt>
                        <dd>{{ $teks($pegawai->pendidikan_terakhir) }}</dd>
                    </div>
                    <div class="detail-item">
                        <dt>Jurusan pendidikan</dt>
                        <dd>{{ $teks($pegawai->jurusan_pendidikan) }}</dd>
                    </div>
                    <div class="detail-item">
                        <dt>Tahun lulus</dt>
                        <dd>{{ $teks($pegawai->tahun_lulus) }}</dd>
                    </div>
                    <div class="detail-item span-2">
                        <dt>Keterangan</dt>
                        <dd style="white-space: pre-line;">{{ $teks($pegawai->keterangan) }}</dd>
                    </div>
                </dl>
            </section>
        </div>
    </div>
@endsection
