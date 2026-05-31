@extends('layouts.app')

@section('title', 'Tambah Unit Aset - NUSA')

@section('content')
    <div class="page-header">
        <div>
            <p class="eyebrow">Sarana Prasarana</p>
            <h1 class="page-title">Tambah unit aset</h1>
        </div>

        <a href="{{ route('unit-barang.index') }}" class="button button-muted">Kembali</a>
    </div>

    <form action="{{ route('unit-barang.store') }}" method="POST">
        @csrf
        @include('unit-barang.partials.form', ['tombol' => 'Simpan unit aset', 'modeTambah' => true])
    </form>
@endsection
