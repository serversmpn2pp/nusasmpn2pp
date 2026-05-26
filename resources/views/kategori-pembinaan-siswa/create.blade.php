@extends('layouts.app')

@section('title', 'Tambah Kategori Pembinaan - NUSA')

@section('content')
    <div class="page-header">
        <div>
            <p class="eyebrow">Pembinaan</p>
            <h1 class="page-title">Tambah kategori pembinaan</h1>
        </div>

        <a href="{{ route('kategori-pembinaan-siswa.index') }}" class="button button-muted">Kembali</a>
    </div>

    <form action="{{ route('kategori-pembinaan-siswa.store') }}" method="POST">
        @csrf
        @include('kategori-pembinaan-siswa.partials.form', ['tombol' => 'Simpan kategori'])
    </form>
@endsection
