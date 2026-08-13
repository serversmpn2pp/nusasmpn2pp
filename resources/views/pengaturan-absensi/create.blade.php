@extends('layouts.app')

@section('title', 'Tambah Pengaturan Presensi - NUSA')

@section('content')
    <div class="page-header">
        <div>
            <p class="eyebrow">Presensi</p>
            <h1 class="page-title">Tambah pengaturan presensi</h1>
        </div>

        <a href="{{ route('pengaturan-absensi.index') }}" class="button button-muted">Kembali</a>
    </div>

    <form action="{{ route('pengaturan-absensi.store') }}" method="POST">
        @csrf
        @include('pengaturan-absensi.partials.form', ['tombol' => 'Simpan pengaturan'])
    </form>
@endsection
