@extends('layouts.app')

@section('title', 'Tambah Jam Pelajaran - NUSA')

@section('content')
    <div class="page-header">
        <div>
            <p class="eyebrow">Akademik</p>
            <h1 class="page-title">Tambah jam pelajaran</h1>
        </div>

        <a href="{{ route('jam-pelajaran.index') }}" class="button button-muted">Kembali</a>
    </div>

    <form action="{{ route('jam-pelajaran.store') }}" method="POST">
        @csrf
        @include('jam-pelajaran.partials.form', ['tombol' => 'Simpan jam pelajaran'])
    </form>
@endsection
