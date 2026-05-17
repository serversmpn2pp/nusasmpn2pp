@extends('layouts.app')

@section('title', 'Tambah Siswa - NUSA')

@section('content')
    <div class="page-header">
        <div>
            <p class="eyebrow">Siswa</p>
            <h1 class="page-title">Tambah siswa</h1>
        </div>

        <a href="{{ route('siswa.index') }}" class="button button-muted">Kembali</a>
    </div>

    <form action="{{ route('siswa.store') }}" method="POST" enctype="multipart/form-data">
        @csrf
        @include('siswa.partials.form', ['tombol' => 'Simpan siswa'])
    </form>
@endsection
