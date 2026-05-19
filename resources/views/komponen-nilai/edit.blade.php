@extends('layouts.app')

@section('title', 'Edit Komponen Nilai - NUSA')

@section('content')
    <div class="page-header">
        <div>
            <p class="eyebrow">Penilaian</p>
            <h1 class="page-title">Edit komponen nilai</h1>
        </div>

        <div class="actions">
            <a href="{{ route('komponen-nilai.show', $komponenNilai) }}" class="button button-muted">Detail</a>
            <a href="{{ route('komponen-nilai.index') }}" class="button button-muted">Kembali</a>
        </div>
    </div>

    <form action="{{ route('komponen-nilai.update', $komponenNilai) }}" method="POST">
        @csrf
        @method('PUT')
        @include('komponen-nilai.partials.form', ['tombol' => 'Simpan perubahan'])
    </form>
@endsection
