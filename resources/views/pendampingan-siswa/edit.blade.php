@extends('layouts.app')

@section('title', (($konteksGuruWali ?? false) ? 'Tindak Lanjut Siswa Wali' : 'Tindak Lanjut Siswa').' - NUSA')

@section('content')
    <div class="page-header">
        <div>
            <p class="eyebrow">{{ ($konteksGuruWali ?? false) ? 'Guru Wali' : 'Kesiswaan & BK' }}</p>
            <h1 class="page-title">{{ ($konteksGuruWali ?? false) ? 'Tindak Lanjut Siswa Wali' : 'Tindak lanjut siswa' }}</h1>
            <p class="page-subtitle">Perbarui catatan atau tandai selesai setelah penanganan dilakukan.</p>
        </div>
        <a class="button button-muted" href="{{ route(($konteksGuruWali ?? false) ? 'rekap-poin-siswa-wali.show' : 'rekap-poin-siswa.show', ['siswa' => $siswa, 'tahun_pelajaran_id' => $tahunPelajaran->id]) }}">
            Profil Siswa
        </a>
    </div>

    @if(session('berhasil'))
        <div class="alert">{{ session('berhasil') }}</div>
    @endif

    @include('pendampingan-siswa.partials.form')
@endsection
