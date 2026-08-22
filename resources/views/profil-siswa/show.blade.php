@extends('layouts.app')

@section('title', 'Profil & Akun - NUSA')

@push('styles')
    <style>
        .student-profile-facts {
            grid-template-columns: 1fr;
            margin-top: 20px;
            padding-top: 8px;
            border-top: 1px solid var(--line);
        }

        .student-profile-facts > div {
            min-width: 0;
            padding-top: 10px;
        }

        .student-profile-facts dd {
            overflow-wrap: anywhere;
        }

        .student-profile-shortcuts {
            display: grid;
            grid-template-columns: repeat(3, minmax(0, 1fr));
            gap: 10px;
            margin-top: 16px;
        }

        .student-profile-shortcuts .button {
            width: 100%;
            border-color: #bfd4e8;
            background: #f4f8fc;
            color: var(--primary-dark);
        }

        .student-profile-shortcuts .button:hover {
            border-color: var(--primary);
            background: var(--primary-soft);
        }

        .student-profile-note {
            margin: 16px 0 0;
            border-left: 3px solid var(--accent);
            padding: 10px 12px;
            background: var(--accent-soft);
            color: var(--accent-text);
            font-size: .88rem;
            line-height: 1.55;
        }

        @media (max-width: 560px) {
            .student-profile-shortcuts {
                grid-template-columns: 1fr;
            }

            .page-header .button {
                width: 100%;
            }
        }
    </style>
@endpush

@section('content')
    @php
        $teks = fn (mixed $nilai) => filled($nilai) ? $nilai : '-';
        $jenisKelamin = match ($siswa->jenis_kelamin) {
            'L' => 'Laki-laki',
            'P' => 'Perempuan',
            default => '-',
        };
        $tempatTanggalLahir = collect([
            $siswa->tempat_lahir,
            $siswa->tanggal_lahir?->locale('id')->translatedFormat('d F Y'),
        ])->filter()->implode(', ');
    @endphp

    <div class="page-header">
        <div>
            <p class="eyebrow">Akun Siswa</p>
            <h1 class="page-title">Profil & Akun</h1>
        </div>
        <a href="{{ route('kata-sandi.edit') }}" class="button button-muted">Ganti kata sandi</a>
    </div>

    <div class="detail-shell">
        <aside class="panel panel-pad">
            <div class="detail-profile">
                <div class="avatar avatar-lg">
                    <img
                        src="{{ $siswa->foto ? asset('storage/'.$siswa->foto) : asset('images/kartu-pelajar/default-user.png') }}"
                        alt="Foto {{ $siswa->nama_lengkap }}"
                    >
                </div>
                <h2>{{ $siswa->nama_lengkap }}</h2>
                <p>NISN {{ $teks($siswa->nisn) }}</p>
                <div style="margin-top: 14px;">
                    <span class="badge {{ $pengguna->aktif ? 'badge-active' : 'badge-inactive' }}">
                        {{ $pengguna->aktif ? 'Akun aktif' : 'Akun nonaktif' }}
                    </span>
                </div>
            </div>

            <dl class="quick-facts student-profile-facts">
                <div>
                    <dt>Username</dt>
                    <dd>{{ $pengguna->username }}</dd>
                </div>
                <div>
                    <dt>Terakhir masuk</dt>
                    <dd>{{ $pengguna->terakhir_login_pada?->locale('id')->translatedFormat('d F Y, H:i') ?: '-' }}</dd>
                </div>
            </dl>
        </aside>

        <div class="section-stack">
            <section class="panel panel-pad">
                <h2 class="panel-title">Data sekolah</h2>
                <dl class="detail-grid">
                    <div class="detail-item">
                        <dt>Kelas</dt>
                        <dd>{{ $anggotaKelas?->kelas?->nama ?: '-' }}</dd>
                    </div>
                    <div class="detail-item">
                        <dt>Nomor absen</dt>
                        <dd>{{ $anggotaKelas?->nomor_absen ?: '-' }}</dd>
                    </div>
                    <div class="detail-item">
                        <dt>Tahun pelajaran</dt>
                        <dd>{{ $tahunPelajaran?->nama ?: $anggotaKelas?->tahunPelajaran?->nama ?: '-' }}</dd>
                    </div>
                    <div class="detail-item">
                        <dt>Wali kelas</dt>
                        <dd>{{ $anggotaKelas?->kelas?->waliKelas?->nama_lengkap ?: 'Belum ditentukan' }}</dd>
                    </div>
                </dl>
            </section>

            <section class="panel panel-pad">
                <h2 class="panel-title">Identitas siswa</h2>
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
                        <dt>Jenis kelamin</dt>
                        <dd>{{ $jenisKelamin }}</dd>
                    </div>
                    <div class="detail-item">
                        <dt>Tempat, tanggal lahir</dt>
                        <dd>{{ $teks($tempatTanggalLahir) }}</dd>
                    </div>
                    <div class="detail-item">
                        <dt>Agama</dt>
                        <dd>{{ $teks($siswa->agama) }}</dd>
                    </div>
                    <div class="detail-item span-2">
                        <dt>Alamat</dt>
                        <dd>{{ $teks($siswa->alamat) }}</dd>
                    </div>
                </dl>

                <p class="student-profile-note">Jika ada data yang kurang tepat, sampaikan kepada wali kelas atau administrator NUSA.</p>
            </section>

            <section class="panel panel-pad">
                <h2 class="panel-title">Akses cepat</h2>
                <div class="student-profile-shortcuts">
                    <a href="{{ route('ujian-saya.index') }}" class="button">Ujian saya</a>
                    <a href="{{ route('nilai-saya.index') }}" class="button">Nilai saya</a>
                    <a href="{{ route('progress-kasus-siswa.index') }}" class="button">Progress kasus</a>
                </div>
            </section>
        </div>
    </div>
@endsection
