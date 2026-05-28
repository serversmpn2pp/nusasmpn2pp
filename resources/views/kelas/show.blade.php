@extends('layouts.app')

@section('title', 'Detail Kelas - NUSA')

@section('content')
    @php
        $teks = fn (mixed $value) => filled($value) ? $value : '-';
        $sisaKursi = $kelas->kapasitas ? max($kelas->kapasitas - $kelas->anggota_kelas_count, 0) : null;
    @endphp

    <div class="page-header">
        <div>
            <p class="eyebrow">Akademik</p>
            <h1 class="page-title">Detail kelas</h1>
        </div>

        <div class="actions">
            <a href="{{ route('kelas.index') }}" class="button button-muted">Kembali</a>
            <a href="{{ route('penempatan-siswa.index', ['tahun_pelajaran_id' => $kelas->tahun_pelajaran_id, 'kelas_id' => $kelas->id]) }}" class="button button-primary">Penempatan siswa</a>
            @izin('kelas.kelola')
                <a href="{{ route('kelas.edit', $kelas) }}" class="button button-dark">Edit</a>
            @endizin
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

            @izin('kelas.kelola')
                @if ($kelas->aktif)
                    <form action="{{ route('kelas.destroy', $kelas) }}" method="POST" style="margin-top: 24px;" onsubmit="return confirm('Nonaktifkan kelas ini?')">
                        @csrf
                        @method('DELETE')
                        <button type="submit" class="button button-danger button-full">Nonaktifkan</button>
                    </form>
                @endif
            @endizin
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

            <section class="panel panel-pad">
                <h2 class="panel-title">Penempatan Siswa</h2>
                <p class="help-text" style="margin-top: 8px;">Anggota kelas sekarang dikelola di halaman khusus agar penempatan siswa lebih mudah dilakukan secara massal.</p>

                <div class="stats-grid" style="margin: 16px 0 0;">
                    <div class="panel stat">
                        <p class="stat-label">Anggota</p>
                        <p class="stat-value">{{ $kelas->anggota_kelas_count }}</p>
                    </div>
                    <div class="panel stat">
                        <p class="stat-label">Kapasitas</p>
                        <p class="stat-value">{{ $kelas->kapasitas ?: '-' }}</p>
                    </div>
                    <div class="panel stat active">
                        <p class="stat-label">Sisa kursi</p>
                        <p class="stat-value">{{ $sisaKursi === null ? '-' : $sisaKursi }}</p>
                    </div>
                </div>

                <div class="form-actions" style="margin-top: 20px;">
                    <a href="{{ route('penempatan-siswa.index', ['tahun_pelajaran_id' => $kelas->tahun_pelajaran_id, 'kelas_id' => $kelas->id]) }}" class="button button-primary">Kelola penempatan siswa</a>
                </div>
            </section>
        </div>
    </div>
@endsection
