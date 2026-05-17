@extends('layouts.app')

@section('title', 'Tambah Tahun Pelajaran - NUSA')

@section('content')
    <div class="page-header">
        <div>
            <p class="eyebrow">Akademik</p>
            <h1 class="page-title">Tambah tahun pelajaran</h1>
        </div>

        <a href="{{ route('tahun-pelajaran.index') }}" class="button button-muted">Kembali</a>
    </div>

    <form action="{{ route('tahun-pelajaran.store') }}" method="POST">
        @csrf
        @include('tahun-pelajaran.partials.form', ['tombol' => 'Simpan tahun pelajaran'])
    </form>
@endsection
