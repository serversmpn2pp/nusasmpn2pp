@extends('layouts.app')

@section('title', 'Tindak Lanjut Siswa - NUSA')

@section('content')
    <div class="page-header">
        <div>
            <p class="eyebrow">Kesiswaan & BK</p>
            <h1 class="page-title">Tindak lanjut siswa</h1>
            <p class="page-subtitle">Perbarui catatan atau tandai selesai setelah penanganan dilakukan.</p>
        </div>
        <a class="button button-muted" href="{{ route('rekap-poin-siswa.show', ['siswa' => $siswa, 'tahun_pelajaran_id' => $tahunPelajaran->id]) }}">
            Profil Siswa
        </a>
    </div>

    @if(session('berhasil'))
        <div class="alert">{{ session('berhasil') }}</div>
    @endif

    @include('pendampingan-siswa.partials.form')
@endsection
