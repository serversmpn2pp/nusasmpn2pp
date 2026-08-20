@extends('layouts.app')

@section('title', 'Profil & Akun - NUSA')

@push('styles')
    <style>
        .parent-profile-layout {
            display: grid;
            grid-template-columns: minmax(250px, .78fr) minmax(0, 1.45fr);
            gap: 18px;
            align-items: start;
        }

        .parent-profile-card,
        .parent-account-form,
        .parent-linked-child {
            border: 1px solid var(--border);
            background: #fff;
            border-radius: 8px;
            box-shadow: var(--shadow);
        }

        .parent-profile-card,
        .parent-account-form {
            padding: 22px;
        }

        .parent-profile-avatar {
            width: 72px;
            height: 72px;
            display: grid;
            place-items: center;
            border-radius: 50%;
            background: var(--primary);
            color: #fff;
            font-size: 25px;
            font-weight: 800;
            margin-bottom: 16px;
        }

        .parent-profile-card h2,
        .parent-child-copy h2 {
            margin: 0;
            font-size: 19px;
            line-height: 1.35;
        }

        .parent-profile-card > p,
        .parent-child-copy > p {
            color: var(--muted);
            margin: 5px 0 0;
        }

        .parent-account-facts {
            display: grid;
            gap: 0;
            margin: 20px 0 0;
        }

        .parent-account-fact {
            display: grid;
            gap: 3px;
            padding: 12px 0;
            border-top: 1px solid var(--border);
        }

        .parent-account-fact span,
        .parent-child-fact span {
            color: var(--muted);
            font-size: 12px;
        }

        .parent-account-fact strong,
        .parent-child-fact strong {
            overflow-wrap: anywhere;
        }

        .parent-account-form h2 {
            margin: 0 0 18px;
            font-size: 18px;
        }

        .parent-form-grid {
            display: grid;
            grid-template-columns: repeat(2, minmax(0, 1fr));
            gap: 16px;
        }

        .parent-form-actions,
        .parent-child-actions {
            display: flex;
            flex-wrap: wrap;
            gap: 10px;
            align-items: center;
        }

        .parent-form-actions {
            justify-content: flex-end;
            padding-top: 18px;
            margin-top: 18px;
            border-top: 1px solid var(--border);
        }

        .parent-linked-child {
            grid-column: 1 / -1;
            padding: 22px;
        }

        .parent-child-main {
            display: grid;
            grid-template-columns: 82px minmax(0, 1fr);
            gap: 18px;
            align-items: center;
        }

        .parent-child-photo {
            width: 82px;
            height: 98px;
            border-radius: 7px;
            border: 2px solid #fff;
            outline: 1px solid var(--border);
            background: #edf2f7;
            object-fit: cover;
        }

        .parent-child-facts {
            display: grid;
            grid-template-columns: repeat(4, minmax(0, 1fr));
            gap: 12px;
            margin-top: 16px;
        }

        .parent-child-fact {
            display: grid;
            gap: 4px;
            min-width: 0;
            padding: 12px;
            background: #f6f8fb;
            border: 1px solid var(--border);
            border-radius: 7px;
        }

        .parent-child-actions {
            margin-top: 16px;
        }

        @media (max-width: 820px) {
            .parent-profile-layout,
            .parent-form-grid {
                grid-template-columns: 1fr;
            }

            .parent-child-facts {
                grid-template-columns: repeat(2, minmax(0, 1fr));
            }
        }

        @media (max-width: 520px) {
            .parent-profile-card,
            .parent-account-form,
            .parent-linked-child {
                padding: 16px;
            }

            .parent-child-main {
                grid-template-columns: 64px minmax(0, 1fr);
            }

            .parent-child-photo {
                width: 64px;
                height: 78px;
            }

            .parent-child-facts {
                grid-template-columns: 1fr;
            }

            .parent-form-actions .button,
            .parent-child-actions .button {
                flex: 1 1 100%;
            }
        }
    </style>
@endpush

@section('content')
    @php
        $inisial = str(
            str($orangTua->nama_lengkap)
                ->trim()
                ->explode(' ')
                ->filter()
                ->take(2)
                ->map(fn ($bagian) => str($bagian)->substr(0, 1))
                ->implode('')
        )->upper();
        $hubungan = ucfirst($siswa?->pivot?->hubungan ?: 'wali');
    @endphp

    <div class="page-header">
        <div>
            <span class="eyebrow">Akun Orang Tua</span>
            <h1>Profil & Akun</h1>
        </div>
        <a href="{{ route('kata-sandi.edit') }}" class="button button-muted">Ganti kata sandi</a>
    </div>

    @if (session('berhasil'))
        <div class="alert alert-success">{{ session('berhasil') }}</div>
    @endif

    <div class="parent-profile-layout">
        <section class="parent-profile-card" aria-label="Ringkasan akun">
            <div class="parent-profile-avatar" aria-hidden="true">{{ $inisial ?: 'OT' }}</div>
            <h2>{{ $orangTua->nama_lengkap }}</h2>
            <p>{{ $hubungan }} dari {{ $siswa?->nama_lengkap ?: 'siswa' }}</p>

            <div class="parent-account-facts">
                <div class="parent-account-fact">
                    <span>Username</span>
                    <strong>{{ auth()->user()->username }}</strong>
                </div>
                <div class="parent-account-fact">
                    <span>Status akun</span>
                    <strong>{{ auth()->user()->aktif ? 'Aktif' : 'Nonaktif' }}</strong>
                </div>
                <div class="parent-account-fact">
                    <span>Terakhir masuk</span>
                    <strong>{{ auth()->user()->terakhir_login_pada?->locale('id')->translatedFormat('d F Y, H:i') ?: '-' }}</strong>
                </div>
            </div>
        </section>

        <form action="{{ route('profil-orang-tua.update') }}" method="POST" class="parent-account-form">
            @csrf
            @method('PUT')
            <h2>Data orang tua/wali</h2>

            <div class="parent-form-grid">
                <div class="field">
                    <label for="nama_lengkap">Nama lengkap</label>
                    <input id="nama_lengkap" name="nama_lengkap" class="input" value="{{ old('nama_lengkap', $orangTua->nama_lengkap) }}" maxlength="255" required autocomplete="name">
                    @error('nama_lengkap')<span class="field-error">{{ $message }}</span>@enderror
                </div>
                <div class="field">
                    <label for="nomor_wa">Nomor WhatsApp</label>
                    <input id="nomor_wa" name="nomor_wa" class="input" value="{{ old('nomor_wa', $orangTua->nomor_wa) }}" maxlength="30" inputmode="tel" autocomplete="tel" placeholder="Contoh: 081234567890">
                    @error('nomor_wa')<span class="field-error">{{ $message }}</span>@enderror
                </div>
            </div>

            <div class="parent-form-actions">
                <button type="submit" class="button button-primary">Simpan profil</button>
            </div>
        </form>

        <section class="parent-linked-child" aria-label="Anak yang terhubung">
            @if ($siswa)
                <div class="parent-child-main">
                    <img
                        class="parent-child-photo"
                        src="{{ $siswa->foto ? asset('storage/'.$siswa->foto) : asset('images/kartu-pelajar/default-user.png') }}"
                        alt="Foto {{ $siswa->nama_lengkap }}"
                    >
                    <div class="parent-child-copy">
                        <span class="eyebrow">Anak Terhubung</span>
                        <h2>{{ $siswa->nama_lengkap }}</h2>
                        <p>NISN {{ $siswa->nisn ?: '-' }}</p>
                    </div>
                </div>

                <div class="parent-child-facts">
                    <div class="parent-child-fact"><span>Kelas</span><strong>{{ $anggotaKelas?->kelas?->nama ?: '-' }}</strong></div>
                    <div class="parent-child-fact"><span>Nomor absen</span><strong>{{ $anggotaKelas?->nomor_absen ?: '-' }}</strong></div>
                    <div class="parent-child-fact"><span>Tahun pelajaran</span><strong>{{ $tahunPelajaran?->nama ?: '-' }}</strong></div>
                    <div class="parent-child-fact"><span>Wali kelas</span><strong>{{ $anggotaKelas?->kelas?->waliKelas?->nama_lengkap ?: 'Belum ditentukan' }}</strong></div>
                </div>

                <div class="parent-child-actions">
                    <a href="{{ route('presensi-anak.index') }}" class="button button-muted">Presensi</a>
                    <a href="{{ route('akademik-anak.index') }}" class="button button-muted">Akademik</a>
                    <a href="{{ route('pembinaan-poin-anak.index') }}" class="button button-muted">Pembinaan & poin</a>
                </div>
            @else
                <div class="empty-state">Belum ada siswa yang terhubung dengan akun ini.</div>
            @endif
        </section>
    </div>
@endsection
