@extends('layouts.app')

@section('title', 'Edit Unit Aset - NUSA')

@section('content')
    <div class="page-header">
        <div>
            <p class="eyebrow">Sarana Prasarana</p>
            <h1 class="page-title">Edit unit aset</h1>
        </div>

        <div class="actions">
            <a href="{{ route('unit-barang.show', $unitBarang) }}" class="button button-muted">Detail</a>
            <a href="{{ route('unit-barang.index') }}" class="button button-muted">Kembali</a>
        </div>
    </div>

    <form action="{{ route('unit-barang.update', $unitBarang) }}" method="POST">
        @csrf
        @method('PUT')
        @include('unit-barang.partials.form', ['tombol' => 'Simpan perubahan', 'modeTambah' => false])
    </form>
@endsection
