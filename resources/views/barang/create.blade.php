@extends('layouts.app')

@section('title', 'Tambah Barang - NUSA')

@section('content')
    <div class="page-header">
        <div>
            <p class="eyebrow">Sarana Prasarana</p>
            <h1 class="page-title">Tambah barang</h1>
        </div>

        <a href="{{ route('barang.index') }}" class="button button-muted">Kembali</a>
    </div>

    <form action="{{ route('barang.store') }}" method="POST">
        @csrf
        @include('barang.partials.form', ['tombol' => 'Simpan barang'])
    </form>
@endsection
