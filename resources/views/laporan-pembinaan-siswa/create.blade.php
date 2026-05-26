@extends('layouts.app')

@section('title', 'Tambah Laporan Pembinaan - NUSA')

@section('content')
    <div class="page-header">
        <div>
            <p class="eyebrow">Pembinaan</p>
            <h1 class="page-title">Tambah laporan pembinaan</h1>
        </div>

        <a href="{{ route('laporan-pembinaan-siswa.index') }}" class="button button-muted">Kembali</a>
    </div>

    <form action="{{ route('laporan-pembinaan-siswa.store') }}" method="POST">
        @csrf
        @include('laporan-pembinaan-siswa.partials.form', ['tombol' => 'Simpan laporan'])
    </form>
@endsection
