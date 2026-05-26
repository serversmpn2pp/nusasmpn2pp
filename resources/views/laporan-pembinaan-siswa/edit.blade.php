@extends('layouts.app')

@section('title', 'Edit Laporan Pembinaan - NUSA')

@section('content')
    <div class="page-header">
        <div>
            <p class="eyebrow">Pembinaan</p>
            <h1 class="page-title">Edit laporan pembinaan</h1>
        </div>

        <div class="actions">
            <a href="{{ route('laporan-pembinaan-siswa.show', $laporanPembinaanSiswa) }}" class="button button-muted">Detail</a>
            <a href="{{ route('laporan-pembinaan-siswa.index') }}" class="button button-muted">Kembali</a>
        </div>
    </div>

    <form action="{{ route('laporan-pembinaan-siswa.update', $laporanPembinaanSiswa) }}" method="POST">
        @csrf
        @method('PUT')
        @include('laporan-pembinaan-siswa.partials.form', ['tombol' => 'Simpan perubahan'])
    </form>
@endsection
