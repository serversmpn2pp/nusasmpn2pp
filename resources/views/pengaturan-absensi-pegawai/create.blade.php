@extends('layouts.app')

@section('title', 'Tambah Jam Presensi Pegawai - NUSA')

@section('content')
    <div class="page-header">
        <div>
            <p class="eyebrow">Presensi Pegawai</p>
            <h1 class="page-title">Tambah jam presensi pegawai</h1>
        </div>

        <a href="{{ route('pengaturan-absensi-pegawai.index') }}" class="button button-muted">Kembali</a>
    </div>

    <form action="{{ route('pengaturan-absensi-pegawai.store') }}" method="POST">
        @csrf
        @include('pengaturan-absensi-pegawai.partials.form', ['tombol' => 'Simpan jadwal'])
    </form>
@endsection
