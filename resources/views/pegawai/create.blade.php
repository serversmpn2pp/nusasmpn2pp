@extends('layouts.app')

@section('title', 'Tambah Pegawai - NUSA')

@section('content')
    <div class="page-header">
        <div>
            <p class="eyebrow">Pegawai</p>
            <h1 class="page-title">Tambah pegawai</h1>
        </div>

        <div class="actions">
            <a href="{{ route('pegawai.import.create') }}" class="button button-muted">Import Excel</a>
            <a href="{{ route('pegawai.index') }}" class="button button-muted">Kembali</a>
        </div>
    </div>

    <form action="{{ route('pegawai.store') }}" method="POST" enctype="multipart/form-data">
        @csrf
        @include('pegawai.partials.form', ['tombol' => 'Simpan pegawai'])
    </form>
@endsection
