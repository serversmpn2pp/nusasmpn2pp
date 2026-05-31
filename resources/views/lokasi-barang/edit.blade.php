@extends('layouts.app')

@section('title', 'Edit Lokasi Barang - NUSA')

@section('content')
    <div class="page-header">
        <div>
            <p class="eyebrow">Sarana Prasarana</p>
            <h1 class="page-title">Edit lokasi barang</h1>
        </div>

        <div class="actions">
            <a href="{{ route('lokasi-barang.show', $lokasiBarang) }}" class="button button-muted">Detail</a>
            <a href="{{ route('lokasi-barang.index') }}" class="button button-muted">Kembali</a>
        </div>
    </div>

    <form action="{{ route('lokasi-barang.update', $lokasiBarang) }}" method="POST">
        @csrf
        @method('PUT')
        @include('lokasi-barang.partials.form', ['tombol' => 'Simpan perubahan'])
    </form>
@endsection
