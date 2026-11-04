@extends('layouts.app')
@section('admin-module', 'akademik')
@section('title', 'Riwayat Setoran Tahfidz - NUSA')
@include('tahfidz._assets')

@section('content')
    <div class="tf-page">
        <div class="page-header">
            <div><p class="eyebrow">Tahfidz / Riwayat setoran</p><h1 class="page-title">{{ $anggota->siswa->nama_lengkap }}</h1><p class="page-subtitle">{{ $kelompok->nama }} &middot; {{ $kelompok->tahunPelajaran->nama }}</p></div>
            <div class="actions">
                <a href="{{ route('tahfidz.show', $kelompok) }}" class="button button-muted"><i data-lucide="arrow-left" aria-hidden="true"></i> Kelompok</a>
                <a href="{{ route('tahfidz.rekap.siswa', [$kelompok, $anggota]) }}" class="button button-muted"><i data-lucide="chart-no-axes-column" aria-hidden="true"></i> Rekap siswa</a>
            </div>
        </div>
        @include('tahfidz._setoran', ['riwayatSiswa' => true])
    </div>
@endsection
