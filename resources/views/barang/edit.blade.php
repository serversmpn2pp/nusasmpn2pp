@extends('layouts.app')

@section('title', 'Edit Barang - NUSA')

@section('content')
    <div class="page-header">
        <div>
            <p class="eyebrow">Sarana Prasarana</p>
            <h1 class="page-title">Edit barang</h1>
        </div>

        <div class="actions">
            <a href="{{ route('barang.show', $barang) }}" class="button button-muted">Detail</a>
            <a href="{{ route('barang.index') }}" class="button button-muted">Kembali</a>
        </div>
    </div>

    <form action="{{ route('barang.update', $barang) }}" method="POST">
        @csrf
        @method('PUT')
        @include('barang.partials.form', ['tombol' => 'Simpan perubahan'])
    </form>
@endsection
