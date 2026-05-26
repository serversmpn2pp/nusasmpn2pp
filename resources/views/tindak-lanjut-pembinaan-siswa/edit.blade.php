@extends('layouts.app')

@section('title', 'Edit Tindak Lanjut Pembinaan - NUSA')

@section('content')
    <div class="page-header">
        <div>
            <p class="eyebrow">Pembinaan BK</p>
            <h1 class="page-title">Edit tindak lanjut</h1>
        </div>

        <a href="{{ route('laporan-pembinaan-siswa.show', $laporanPembinaanSiswa) }}" class="button button-muted">Kembali</a>
    </div>

    <form action="{{ route('tindak-lanjut-pembinaan-siswa.update', $tindakLanjutPembinaanSiswa) }}" method="POST">
        @csrf
        @method('PUT')
        @include('tindak-lanjut-pembinaan-siswa.partials.form', ['tombol' => 'Simpan perubahan'])
    </form>
@endsection
