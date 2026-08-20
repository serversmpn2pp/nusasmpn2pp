@extends('layouts.app')

@section('title', 'Dashboard Orang Tua - NUSA')

@section('content')
    <style>
        .parent-welcome {
            display: grid;
            grid-template-columns: minmax(0, 1fr) minmax(220px, .42fr);
            overflow: hidden;
            border: 1px solid rgba(21, 71, 122, .18);
            border-radius: 8px;
            background: var(--primary);
            color: #fff;
            box-shadow: var(--shadow);
        }

        .parent-welcome-main,
        .parent-welcome-side {
            padding: 26px;
        }

        .parent-welcome-side {
            border-left: 1px solid rgba(255, 255, 255, .18);
            background: rgba(255, 255, 255, .08);
        }

        .parent-welcome h1 {
            margin: 4px 0 8px;
            color: #fff;
            font-size: clamp(1.55rem, 4vw, 2.15rem);
            line-height: 1.15;
        }

        .parent-welcome p {
            margin: 0;
            color: rgba(255, 255, 255, .82);
        }

        .parent-welcome .eyebrow {
            color: var(--accent);
        }

        .parent-children {
            display: grid;
            grid-template-columns: repeat(2, minmax(0, 1fr));
            gap: 12px;
            margin-top: 20px;
        }

        .parent-child {
            border-left: 4px solid var(--secondary);
            padding: 18px;
        }

        .parent-child strong,
        .parent-child span {
            display: block;
        }

        .parent-child strong {
            color: var(--primary-dark);
            font-size: 1rem;
        }

        .parent-child span {
            margin-top: 4px;
            color: var(--muted);
            font-size: .84rem;
        }

        @media (max-width: 720px) {
            .parent-welcome,
            .parent-children {
                grid-template-columns: 1fr;
            }

            .parent-welcome-side {
                border-top: 1px solid rgba(255, 255, 255, .18);
                border-left: 0;
            }
        }
    </style>

    <section class="parent-welcome">
        <div class="parent-welcome-main">
            <p class="eyebrow">Dashboard Orang Tua</p>
            <h1>Selamat datang di NUSA</h1>
            <p>Akun Anda sudah terhubung dengan data anak. Informasi sekolah untuk orang tua akan disiapkan pada tahap dashboard berikutnya.</p>
        </div>
        <div class="parent-welcome-side">
            <p>Tahun pelajaran</p>
            <h2>{{ $tahunPelajaranAktif?->nama ?: 'Belum ditentukan' }}</h2>
            <p>{{ $daftarAnak->count() }} anak terhubung</p>
        </div>
    </section>

    <div class="parent-children">
        @forelse ($daftarAnak as $siswa)
            <article class="panel parent-child">
                <strong>{{ $siswa->nama_lengkap }}</strong>
                <span>NISN {{ $siswa->nisn ?: '-' }}</span>
            </article>
        @empty
            <div class="alert alert-danger">Akun ini belum terhubung dengan siswa. Silakan hubungi administrator sekolah.</div>
        @endforelse
    </div>
@endsection
