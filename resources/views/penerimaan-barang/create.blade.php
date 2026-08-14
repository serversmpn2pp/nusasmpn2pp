@extends('layouts.app')

@section('title', 'Catat Barang Datang - NUSA')

@section('content')
    <div class="page-header">
        <div>
            <p class="eyebrow">Sarana Prasarana</p>
            <h1 class="page-title">Catat barang datang</h1>
            <p class="page-subtitle">Satu dokumen dapat memuat beberapa jenis barang sekaligus.</p>
        </div>

        <div class="actions">
            <a href="{{ route('penerimaan-barang.import.create') }}" class="button button-muted">Import Excel</a>
            <a href="{{ route('penerimaan-barang.index') }}" class="button button-muted">Kembali</a>
        </div>
    </div>

    <form action="{{ route('penerimaan-barang.store') }}" method="POST">
        @csrf
        @include('penerimaan-barang.partials.form')
    </form>
@endsection
