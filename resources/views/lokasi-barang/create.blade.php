@extends('layouts.app')

@section('title', 'Tambah Lokasi Barang - NUSA')

@section('content')
    <div class="page-header">
        <div>
            <p class="eyebrow">Sarana Prasarana</p>
            <h1 class="page-title">Tambah lokasi barang</h1>
        </div>

        <a href="{{ route('lokasi-barang.index') }}" class="button button-muted">Kembali</a>
    </div>

    <form action="{{ route('lokasi-barang.store') }}" method="POST">
        @csrf
        @include('lokasi-barang.partials.form', ['tombol' => 'Simpan lokasi'])
    </form>
@endsection
