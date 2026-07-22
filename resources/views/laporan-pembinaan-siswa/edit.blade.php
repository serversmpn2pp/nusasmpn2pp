@extends('layouts.app')

@section('title', 'Edit Pembinaan atau Pelanggaran - NUSA')

@section('content')
    <div class="page-header pembinaan-create-header">
        <div>
            <p class="eyebrow">Kesiswaan & BK</p>
            <h1 class="page-title pembinaan-page-title">Edit pembinaan atau pelanggaran</h1>
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
