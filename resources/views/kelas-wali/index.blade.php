@extends('layouts.app')

@section('title', 'Kelas Wali - NUSA')

@section('content')
    @php
        $bolehLihatSiswa = auth()->user()?->memilikiIzin(['siswa.lihat', 'siswa.kelola']) ?? false;
    @endphp

    <style>
        .compact-member-table {
            min-width: 0;
        }

        .compact-member-table th:first-child,
        .compact-member-table td:first-child {
            width: 84px;
        }

        .member-name-link {
            color: var(--primary);
            text-decoration: none;
        }

        .member-name-link:hover {
            text-decoration: underline;
        }
    </style>

    <div class="page-header">
        <div>
            <p class="eyebrow">Wali Kelas</p>
            <h1 class="page-title">Kelas Wali</h1>
        </div>
    </div>

    <div class="stats-grid">
        <div class="panel stat">
            <p class="stat-label">Kelas diwali</p>
            <p class="stat-value">{{ $ringkasan['kelas'] }}</p>
        </div>
        <div class="panel stat active">
            <p class="stat-label">Siswa aktif</p>
            <p class="stat-value">{{ $ringkasan['siswa'] }}</p>
        </div>
        <div class="panel stat inactive">
            <p class="stat-label">L / P</p>
            <p class="stat-value">{{ $ringkasan['laki_laki'] }} / {{ $ringkasan['perempuan'] }}</p>
        </div>
    </div>

    @forelse ($kelasWali as $kelas)
        <section class="panel panel-pad" style="margin-bottom: 24px;">
            <div class="page-header" style="align-items: center; margin-bottom: 18px;">
                <div>
                    <p class="eyebrow">{{ $kelas->tahunPelajaran?->nama ?: 'Tahun pelajaran belum tersedia' }}</p>
                    <h2 class="page-title" style="font-size: 1.35rem;">{{ $kelas->nama }}</h2>
                    <p class="help-text" style="margin-top: 6px;">
                        Wali kelas: {{ $kelas->waliKelas?->nama_lengkap ?: '-' }}.
                        {{ $kelas->jumlah_siswa }} siswa aktif.
                    </p>
                </div>

                <div class="actions">
                    <a href="{{ route('rekap-absensi-harian.index', ['tahun_pelajaran_id' => $kelas->tahun_pelajaran_id, 'kelas_id' => $kelas->id]) }}" class="button button-muted">Rekap presensi</a>
                    <a href="{{ route('laporan-absensi.index', ['tahun_pelajaran_id' => $kelas->tahun_pelajaran_id, 'kelas_id' => $kelas->id]) }}" class="button button-muted">Laporan presensi</a>
                    <a href="{{ route('laporan-pembinaan-siswa.index', ['tahun_pelajaran_id' => $kelas->tahun_pelajaran_id, 'kelas_id' => $kelas->id]) }}" class="button button-primary">Pembinaan</a>
                </div>
            </div>

            <div class="desktop-only table-wrap">
                <table class="employee-table compact-member-table">
                    <thead>
                        <tr>
                            <th>No</th>
                            <th>Nama</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse ($kelas->anggotaKelas as $anggota)
                            @php($siswa = $anggota->siswa)
                            <tr>
                                <td>{{ $anggota->nomor_absen ?: '-' }}</td>
                                <td>
                                    <div class="person">
                                        <div class="avatar avatar-sm">
                                            @if ($siswa?->foto)
                                                <img src="{{ asset('storage/' . $siswa->foto) }}" alt="Foto {{ $siswa->nama_lengkap }}">
                                            @else
                                                {{ strtoupper(mb_substr($siswa?->nama_lengkap ?: '-', 0, 1)) }}
                                            @endif
                                        </div>
                                        <div>
                                            @if ($bolehLihatSiswa && $siswa)
                                                <a href="{{ route('siswa.show', $siswa) }}" class="person-name member-name-link">{{ $siswa->nama_lengkap }}</a>
                                            @else
                                                <p class="person-name">{{ $siswa?->nama_lengkap ?: '-' }}</p>
                                            @endif
                                        </div>
                                    </div>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="2" class="empty-state">Belum ada siswa aktif di kelas ini.</td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>

            <div class="mobile-only mobile-list">
                @forelse ($kelas->anggotaKelas as $anggota)
                    @php($siswa = $anggota->siswa)
                    <article class="mobile-card">
                        <div class="mobile-card-main">
                            <div class="avatar avatar-md">
                                @if ($siswa?->foto)
                                    <img src="{{ asset('storage/' . $siswa->foto) }}" alt="Foto {{ $siswa->nama_lengkap }}">
                                @else
                                    {{ strtoupper(mb_substr($siswa?->nama_lengkap ?: '-', 0, 1)) }}
                                @endif
                            </div>
                            <div style="min-width: 0;">
                                @if ($bolehLihatSiswa && $siswa)
                                    <a href="{{ route('siswa.show', $siswa) }}" class="person-name member-name-link">
                                        {{ $anggota->nomor_absen ? $anggota->nomor_absen . '. ' : '' }}{{ $siswa->nama_lengkap }}
                                    </a>
                                @else
                                    <p class="person-name">{{ $anggota->nomor_absen ? $anggota->nomor_absen . '. ' : '' }}{{ $siswa?->nama_lengkap ?: '-' }}</p>
                                @endif
                            </div>
                        </div>
                    </article>
                @empty
                    <div class="empty-state">Belum ada siswa aktif di kelas ini.</div>
                @endforelse
            </div>
        </section>
    @empty
        <section class="panel">
            <div class="empty-state">Belum ada kelas yang terhubung dengan akun wali kelas ini.</div>
        </section>
    @endforelse
@endsection
