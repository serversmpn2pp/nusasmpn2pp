@extends('layouts.app')

@section('title', 'Progress Kasus Saya - NUSA')

@section('content')
    @include('progress-kasus-siswa._styles')

    <div class="case-page">
        <section class="case-hero">
            <div>
                <h1>Progress Kasus Saya</h1>
                <p>Lihat perkembangan pemeriksaan, keputusan sekolah, dan tindak lanjut dari laporan yang berkaitan dengan Anda.</p>
            </div>
            <div class="case-hero-meta">
                {{ $tahunPelajaranAktif?->nama ?: 'Semua tahun pelajaran' }}
            </div>
        </section>

        @if (! $siswa)
            <section class="case-empty">
                <strong>Akun belum terhubung ke data siswa</strong>
                Hubungi administrator sekolah agar akun ini dihubungkan dengan data siswa yang benar.
            </section>
        @else
            <section class="case-summary" aria-label="Ringkasan kasus siswa">
                <article class="case-stat">
                    <span>Semua laporan</span>
                    <strong>{{ $ringkasan['semua'] }}</strong>
                </article>
                <article class="case-stat is-yellow">
                    <span>Sedang diproses</span>
                    <strong>{{ $ringkasan['diproses'] }}</strong>
                </article>
                <article class="case-stat is-green">
                    <span>Diputuskan pembinaan</span>
                    <strong>{{ $ringkasan['pembinaan'] }}</strong>
                </article>
                <article class="case-stat is-red">
                    <span>Total poin resmi</span>
                    <strong>{{ $ringkasan['poin_resmi'] }}</strong>
                </article>
            </section>

            <section class="case-list" aria-label="Daftar progress kasus">
                @forelse ($laporan as $item)
                    @php($status = $presentasiStatus[$item->id])
                    <article class="case-card">
                        <div class="case-card-main">
                            <div class="case-card-top">
                                <span class="case-number">{{ $item->nomor_laporan }}</span>
                                <span class="case-status {{ $status['warna'] }}">{{ $status['label'] }}</span>
                            </div>
                            <h2>
                                {{ $item->berasalDariAbsensi() ? 'Catatan keterlambatan dari absensi' : 'Laporan kejadian siswa' }}
                            </h2>
                            <p>{{ $status['deskripsi'] }}</p>
                            <div class="case-facts">
                                <span>{{ $item->tanggal_kejadian?->locale('id')->translatedFormat('d F Y') }}</span>
                                <span>{{ $item->tempat_kejadian ?: 'Tempat tidak dicantumkan' }}</span>
                                <span>{{ $item->kelas?->nama ?: 'Kelas tidak dicantumkan' }}</span>
                                <span>{{ $item->tahunPelajaran?->nama ?: 'Tahun pelajaran tidak dicantumkan' }}</span>
                            </div>
                        </div>
                        <a class="case-open" href="{{ route('progress-kasus-siswa.show', $item) }}">Lihat Detail</a>
                    </article>
                @empty
                    <div class="case-empty">
                        <strong>Tidak ada kasus yang sedang atau pernah dicatat</strong>
                        Data akan tampil di sini apabila terdapat laporan yang berkaitan dengan Anda.
                    </div>
                @endforelse
            </section>

            @if ($laporan->hasPages())
                <div>{{ $laporan->links() }}</div>
            @endif
        @endif
    </div>
@endsection
