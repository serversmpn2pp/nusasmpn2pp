@extends('layouts.app')

@section('title', 'Catat Peminjaman Barang - NUSA')

@section('content')
    <div class="page-header">
        <div>
            <p class="eyebrow">Sarana Prasarana</p>
            <h1 class="page-title">Catat peminjaman barang</h1>
        </div>

        <a href="{{ route('peminjaman-barang.index') }}" class="button button-muted">Kembali</a>
    </div>

    <form action="{{ route('peminjaman-barang.store') }}" method="POST">
        @csrf
        @include('peminjaman-barang.partials.form')
    </form>
@endsection
