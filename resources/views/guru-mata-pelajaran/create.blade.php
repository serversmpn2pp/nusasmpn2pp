@extends('layouts.app')

@section('title', 'Tambah Guru Mata Pelajaran - NUSA')

@section('content')
    <div class="page-header">
        <div>
            <p class="eyebrow">Akademik</p>
            <h1 class="page-title">Tambah guru mata pelajaran</h1>
        </div>

        <a href="{{ route('guru-mata-pelajaran.index') }}" class="button button-muted">Kembali</a>
    </div>

    <form action="{{ route('guru-mata-pelajaran.store') }}" method="POST">
        @csrf
        @include('guru-mata-pelajaran.partials.form', ['tombol' => 'Simpan penugasan'])
    </form>
@endsection
