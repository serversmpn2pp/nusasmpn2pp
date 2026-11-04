@extends('layouts.app')
@section('admin-module', 'akademik')
@section('title', 'Rekap Siswa Tahfidz - NUSA')
@include('tahfidz._assets')

@section('content')
    <div class="tf-page">
        <div class="page-header">
            <div><p class="eyebrow">Tahfidz / Rekap siswa</p><h1 class="page-title">{{ $anggota->siswa->nama_lengkap }}</h1><p class="page-subtitle">{{ $kelompok->nama }} &middot; {{ $kelompok->tahunPelajaran->nama }}</p></div>
            <div class="actions">
                <a href="{{ route('tahfidz.rekap.kelompok', $kelompok) }}" class="button button-muted"><i data-lucide="arrow-left" aria-hidden="true"></i> Rekap kelompok</a>
                <a href="{{ route('tahfidz.anggota.riwayat', [$kelompok, $anggota]) }}" class="button button-muted"><i data-lucide="history" aria-hidden="true"></i> Riwayat setoran</a>
                @if ($bolehDokumen)
                    <a href="{{ route('tahfidz.rapor.cetak', [$kelompok, $anggota]) }}" class="button button-primary"><i data-lucide="printer" aria-hidden="true"></i> Rapor Tahfidz</a>
                @endif
            </div>
        </div>
        @include('tahfidz._pesan')

        <dl class="tf-facts tf-rekap-metrics tf-rekap-siswa-metrics">
            <div><dt>Surah sudah disetor</dt><dd>{{ $sudah }}/{{ $target }}</dd></div>
            <div><dt>Surah belum disetor</dt><dd>{{ $target - $sudah }}</dd></div>
            <div><dt>Total setoran</dt><dd>{{ $jumlahSetoran }}</dd></div>
        </dl>

        @if ($bolehWisuda)
            <section class="tf-surface tf-section tf-document-status" aria-labelledby="tf-lulus">
                <header class="tf-band"><h2 id="tf-lulus">Kelulusan Juz 30</h2><span>{{ $capaianJuz }}/37 surah</span></header>
                <div class="tf-student-row">
                    <div><p class="person-name">{{ $kelulusan?->diterbitkan_pada ? 'Sertifikat terbit' : ($kelulusan ? 'Kelulusan diverifikasi' : ($capaianJuz === 37 ? 'Menunggu verifikasi pembimbing' : 'Setoran belum lengkap')) }}</p>
                        @if ($kelulusan)<p class="person-meta">{{ $kelulusan->nama_pembimbing }} &middot; {{ $kelulusan->diverifikasi_pada->format('d/m/Y') }}</p>@endif
                    </div>
                    @if ($kelulusan?->diterbitkan_pada)
                        <a href="{{ route('tahfidz.anggota.sertifikat', [$kelompok, $anggota]) }}" class="button button-muted"><i data-lucide="award" aria-hidden="true"></i> Sertifikat</a>
                    @elseif (! $kelulusan && $bolehVerifikasi && $capaianJuz === 37)
                        <form method="POST" action="{{ route('tahfidz.wisuda.verifikasi', [$kelompok, $anggota]) }}">
                            @csrf
                            <button type="submit" class="button button-primary"><i data-lucide="badge-check" aria-hidden="true"></i> Verifikasi kelulusan</button>
                        </form>
                    @endif
                </div>
            </section>
        @endif

        <nav class="tf-tabs" aria-label="Filter status surah">
            @foreach (['' => 'Semua surah', 'sudah' => 'Sudah disetor', 'belum' => 'Belum disetor'] as $kode => $label)
                <a href="{{ route('tahfidz.rekap.siswa', ['kelompok' => $kelompok, 'anggota' => $anggota, ...($kode ? ['status' => $kode] : [])]) }}" @if($status === $kode) aria-current="page" @endif>{{ $label }}</a>
            @endforeach
        </nav>

        @include('tahfidz._perkembangan-surah')
    </div>
@endsection
