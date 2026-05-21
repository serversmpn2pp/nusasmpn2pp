@extends('layouts.app')

@section('title', 'Edit Jam Absensi Pegawai - NUSA')

@section('content')
    <div class="page-header">
        <div>
            <p class="eyebrow">Absensi Pegawai</p>
            <h1 class="page-title">Edit jam absensi pegawai</h1>
        </div>

        <div class="actions">
            <a href="{{ route('pengaturan-absensi-pegawai.show', $pengaturanAbsensiPegawai) }}" class="button button-muted">Detail</a>
            <a href="{{ route('pengaturan-absensi-pegawai.index') }}" class="button button-muted">Kembali</a>
        </div>
    </div>

    <form action="{{ route('pengaturan-absensi-pegawai.update', $pengaturanAbsensiPegawai) }}" method="POST">
        @csrf
        @method('PUT')
        @include('pengaturan-absensi-pegawai.partials.form', ['tombol' => 'Simpan perubahan'])
    </form>
@endsection
