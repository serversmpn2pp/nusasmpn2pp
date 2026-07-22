@extends('layouts.app')

@section('title', 'Tambah Pembinaan atau Pelanggaran - NUSA')

@section('content')
    <div class="page-header pembinaan-create-header">
        <div>
            <p class="eyebrow">Kesiswaan & BK</p>
            <h1 class="page-title pembinaan-page-title">Tambah pembinaan atau pelanggaran</h1>
        </div>

        <a href="{{ route('laporan-pembinaan-siswa.index') }}" class="button button-muted">Kembali</a>
    </div>

    <form action="{{ route('laporan-pembinaan-siswa.store') }}" method="POST" enctype="multipart/form-data">
        @csrf
        @include('laporan-pembinaan-siswa.partials.form', ['tombol' => 'Simpan laporan'])
    </form>
@endsection
