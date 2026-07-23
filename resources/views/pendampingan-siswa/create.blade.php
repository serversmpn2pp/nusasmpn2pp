@extends('layouts.app')

@section('title', 'Mulai Tindak Lanjut Siswa - NUSA')

@section('content')
    <div class="page-header">
        <div>
            <p class="eyebrow">Kesiswaan & BK</p>
            <h1 class="page-title">Mulai tindak lanjut</h1>
            <p class="page-subtitle">Catat tindakan awal tanpa menambah alur administrasi yang panjang.</p>
        </div>
    </div>

    @include('pendampingan-siswa.partials.form')
@endsection
