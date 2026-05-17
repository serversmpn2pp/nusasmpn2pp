@extends('layouts.app')

@section('title', 'Detail Kelas - NUSA')

@section('content')
    @php
        $teks = fn (mixed $value) => filled($value) ? $value : '-';
    @endphp

    <div class="page-header">
        <div>
            <p class="eyebrow">Akademik</p>
            <h1 class="page-title">Detail kelas</h1>
        </div>

        <div class="actions">
            <a href="{{ route('kelas.index') }}" class="button button-muted">Kembali</a>
            <a href="{{ route('kelas.edit', $kelas) }}" class="button button-dark">Edit</a>
        </div>
    </div>

    @if (session('berhasil'))
        <div class="alert">{{ session('berhasil') }}</div>
    @endif

    <div class="detail-shell">
        <aside class="panel panel-pad">
            <div class="detail-profile">
                <div class="avatar avatar-lg">{{ mb_substr($kelas->nama, 0, 3) }}</div>
                <h2>{{ $kelas->nama }}</h2>
                <p>{{ $kelas->tahunPelajaran?->nama ?: 'Tahun pelajaran belum tersedia' }}</p>

                <div style="margin-top: 16px;">
                    @if ($kelas->aktif)
                        <span class="badge badge-active">Aktif</span>
                    @else
                        <span class="badge badge-inactive">Nonaktif</span>
                    @endif
                </div>
            </div>

            @if ($kelas->aktif)
                <form action="{{ route('kelas.destroy', $kelas) }}" method="POST" style="margin-top: 24px;" onsubmit="return confirm('Nonaktifkan kelas ini?')">
                    @csrf
                    @method('DELETE')
                    <button type="submit" class="button button-danger button-full">Nonaktifkan</button>
                </form>
            @endif
        </aside>

        <div class="section-stack">
            <section class="panel panel-pad">
                <h2 class="panel-title">Informasi Kelas</h2>
                <dl class="detail-grid">
                    <div class="detail-item">
                        <dt>Tahun pelajaran</dt>
                        <dd>{{ $kelas->tahunPelajaran?->nama ?: '-' }}</dd>
                    </div>
                    <div class="detail-item">
                        <dt>Wali kelas</dt>
                        <dd>{{ $kelas->waliKelas?->nama_lengkap ?: '-' }}</dd>
                    </div>
                    <div class="detail-item">
                        <dt>Tingkat</dt>
                        <dd>{{ $teks($kelas->tingkat) }}</dd>
                    </div>
                    <div class="detail-item">
                        <dt>Kapasitas</dt>
                        <dd>{{ $teks($kelas->kapasitas) }}</dd>
                    </div>
                    <div class="detail-item">
                        <dt>Anggota</dt>
                        <dd>{{ $kelas->anggota_kelas_count }} siswa</dd>
                    </div>
                    <div class="detail-item span-2">
                        <dt>Keterangan</dt>
                        <dd style="white-space: pre-line;">{{ $teks($kelas->keterangan) }}</dd>
                    </div>
                </dl>
            </section>

            <section class="panel">
                <div class="desktop-only table-wrap">
                    <table class="employee-table">
                        <thead>
                            <tr>
                                <th>No. absen</th>
                                <th>Siswa</th>
                                <th>Status</th>
                                <th>Tanggal masuk</th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse ($anggotaKelas as $item)
                                <tr>
                                    <td>{{ $item->nomor_absen ?: '-' }}</td>
                                    <td>
                                        <p class="person-name">{{ $item->siswa?->nama_lengkap ?: '-' }}</p>
                                        <p class="person-meta">NISN: {{ $item->siswa?->nisn ?: '-' }}</p>
                                    </td>
                                    <td>{{ $item->status_keanggotaan }}</td>
                                    <td>{{ $item->tanggal_masuk ? $item->tanggal_masuk->format('d-m-Y') : '-' }}</td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="4" class="empty-state">Belum ada siswa di kelas ini.</td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>

                <div class="mobile-only mobile-list">
                    @forelse ($anggotaKelas as $item)
                        <article class="mobile-card">
                            <div class="mobile-card-head">
                                <div>
                                    <p class="person-name">{{ $item->siswa?->nama_lengkap ?: '-' }}</p>
                                    <p class="person-meta">No. absen {{ $item->nomor_absen ?: '-' }}</p>
                                </div>
                                <span class="badge badge-active">{{ $item->status_keanggotaan }}</span>
                            </div>
                        </article>
                    @empty
                        <div class="empty-state">Belum ada siswa di kelas ini.</div>
                    @endforelse
                </div>
            </section>
        </div>
    </div>
@endsection
