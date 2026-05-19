@extends('layouts.app')

@section('title', 'Tambah Komponen Nilai - NUSA')

@section('content')
    <div class="page-header">
        <div>
            <p class="eyebrow">Penilaian</p>
            <h1 class="page-title">Tambah komponen nilai</h1>
        </div>

        <a href="{{ route('komponen-nilai.index') }}" class="button button-muted">Kembali</a>
    </div>

    <form action="{{ route('komponen-nilai.store') }}" method="POST">
        @csrf
        @include('komponen-nilai.partials.form', ['tombol' => 'Simpan komponen'])
    </form>
@endsection
