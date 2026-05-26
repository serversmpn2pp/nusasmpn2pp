@extends('layouts.app')

@section('title', 'Edit Kategori Pembinaan - NUSA')

@section('content')
    <div class="page-header">
        <div>
            <p class="eyebrow">Pembinaan</p>
            <h1 class="page-title">Edit kategori pembinaan</h1>
        </div>

        <div class="actions">
            <a href="{{ route('kategori-pembinaan-siswa.show', $kategoriPembinaanSiswa) }}" class="button button-muted">Detail</a>
            <a href="{{ route('kategori-pembinaan-siswa.index') }}" class="button button-muted">Kembali</a>
        </div>
    </div>

    <form action="{{ route('kategori-pembinaan-siswa.update', $kategoriPembinaanSiswa) }}" method="POST">
        @csrf
        @method('PUT')
        @include('kategori-pembinaan-siswa.partials.form', ['tombol' => 'Simpan perubahan'])
    </form>
@endsection
