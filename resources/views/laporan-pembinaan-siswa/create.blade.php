@extends('layouts.app')

@section('title', ($laporanPembinaanSiswa->jenis_laporan === 'pembinaan' ? 'Tambah Pembinaan' : 'Tambah Laporan Kejadian').' - NUSA')

@section('content')
    <div class="page-header pembinaan-create-header">
        <div>
            <p class="eyebrow">Kesiswaan & BK</p>
            <h1 class="page-title pembinaan-page-title">{{ $laporanPembinaanSiswa->jenis_laporan === 'pembinaan' ? 'Tambah pembinaan' : 'Tambah laporan kejadian' }}</h1>
        </div>

        <a href="{{ route('laporan-pembinaan-siswa.index') }}" class="button button-muted">Kembali</a>
    </div>

    <form action="{{ route('laporan-pembinaan-siswa.store') }}" method="POST" enctype="multipart/form-data">
        @csrf
        @include('laporan-pembinaan-siswa.partials.form', ['tombol' => 'Kirim laporan'])
    </form>
@endsection
