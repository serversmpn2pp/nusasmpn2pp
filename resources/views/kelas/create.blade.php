@extends('layouts.app')

@section('title', 'Tambah Kelas - NUSA')

@section('content')
    <div class="page-header">
        <div>
            <p class="eyebrow">Akademik</p>
            <h1 class="page-title">Tambah kelas</h1>
        </div>

        <a href="{{ route('kelas.index') }}" class="button button-muted">Kembali</a>
    </div>

    <form action="{{ route('kelas.store') }}" method="POST">
        @csrf
        @include('kelas.partials.form', ['tombol' => 'Simpan kelas'])
    </form>
@endsection
