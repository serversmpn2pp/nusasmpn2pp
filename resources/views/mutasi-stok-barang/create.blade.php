@extends('layouts.app')

@section('title', 'Catat Mutasi Stok - NUSA')

@section('content')
    <div class="page-header">
        <div>
            <p class="eyebrow">Sarana Prasarana</p>
            <h1 class="page-title">Catat mutasi stok</h1>
        </div>

        <a href="{{ route('mutasi-stok-barang.index') }}" class="button button-muted">Kembali</a>
    </div>

    <form action="{{ route('mutasi-stok-barang.store') }}" method="POST">
        @csrf
        @include('mutasi-stok-barang.partials.form')
    </form>
@endsection
