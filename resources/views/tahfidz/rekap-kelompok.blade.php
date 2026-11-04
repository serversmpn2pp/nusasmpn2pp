@extends('layouts.app')
@section('admin-module', 'akademik')
@section('title', 'Rekap Kelompok Tahfidz - NUSA')
@include('tahfidz._assets')

@section('content')
    <div class="tf-page">
        <div class="page-header">
            <div><p class="eyebrow">Tahfidz / Rekap kelompok</p><h1 class="page-title">{{ $kelompok->nama }}</h1><p class="page-subtitle">{{ $kelompok->tahunPelajaran->nama }} &middot; {{ $kelompok->pembimbing->nama_lengkap }}</p></div>
            <div class="actions"><a href="{{ route('tahfidz.show', $kelompok) }}" class="button button-muted"><i data-lucide="arrow-left" aria-hidden="true"></i> Kelompok</a></div>
        </div>
        @include('tahfidz._pesan')

        <dl class="tf-facts tf-rekap-metrics">
            <div><dt>Anggota kelompok</dt><dd>{{ $jumlahSiswa }}</dd></div>
            <div><dt>Sudah mulai setor</dt><dd>{{ $sudahMulai }}</dd></div>
            <div><dt>Belum mulai setor</dt><dd>{{ $jumlahSiswa - $sudahMulai }}</dd></div>
            <div><dt>Sudah setor semua {{ $target }} surah</dt><dd>{{ $lengkap }}</dd></div>
        </dl>

        @if ($jumlahSiswa > 0)
            <form action="{{ route('tahfidz.rekap.kelompok', $kelompok) }}" method="GET" class="feature-filter tf-filters">
                <div class="field"><label for="tf-rekap-cari">Cari siswa</label><input id="tf-rekap-cari" name="cari" class="input" type="search" maxlength="120" value="{{ $cari }}" placeholder="Nama siswa"></div>
                <div class="field"><label for="tf-rekap-status">Perkembangan</label>
                    <select id="tf-rekap-status" name="status" class="select">
                        <option value="">Semua status</option>
                        <option value="belum" @selected($status === 'belum')>Belum mulai</option>
                        <option value="berjalan" @selected($status === 'berjalan')>Sedang berjalan</option>
                        <option value="lengkap" @selected($status === 'lengkap')>Semua surah disetor</option>
                    </select>
                </div>
                <button type="submit" class="button button-muted"><i data-lucide="search" aria-hidden="true"></i> Cari</button>
            </form>
        @endif

        <section class="tf-surface" aria-labelledby="tf-rekap-anggota">
            <header class="tf-band"><h2 id="tf-rekap-anggota">Perkembangan siswa</h2><span>{{ $baris->count() }} siswa</span></header>
            <ul class="tf-list">
                @forelse ($baris as $item)
                    @php($anggota = $item['anggota'])
                    <li class="tf-rekap-row">
                        <div class="tf-rekap-nama"><p class="person-name">{{ $anggota->siswa->nama_lengkap }}</p><p class="person-meta">{{ $anggota->siswa->anggotaKelas->first()?->kelas?->nama ?? 'Belum ada kelas' }}</p></div>
                        <div class="tf-progress">
                            <span><strong>{{ $item['sudah'] }}/{{ $target }}</strong> surah disetor</span>
                            <progress max="{{ max(1, $target) }}" value="{{ $item['sudah'] }}" aria-label="Surah disetor oleh {{ $anggota->siswa->nama_lengkap }}"></progress>
                        </div>
                        <div><p class="person-name">{{ $item['setoran'] }} setoran</p><p class="person-meta">{{ $item['terakhir'] ? 'Terakhir '.\Illuminate\Support\Carbon::parse($item['terakhir'])->locale('id')->translatedFormat('j M Y') : 'Belum ada setoran' }}</p></div>
                        <a href="{{ route('tahfidz.rekap.siswa', [$kelompok, $anggota]) }}" class="button button-muted" aria-label="Rekap {{ $anggota->siswa->nama_lengkap }}">Rekap <i data-lucide="chevron-right" aria-hidden="true"></i></a>
                    </li>
                @empty
                    <li class="empty-state">{{ $jumlahSiswa === 0 ? 'Belum ada anggota pada kelompok ini.' : 'Tidak ada siswa yang sesuai dengan filter.' }}</li>
                @endforelse
            </ul>
        </section>
    </div>
@endsection
