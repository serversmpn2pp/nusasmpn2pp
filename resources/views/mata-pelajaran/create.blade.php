@extends('layouts.app')

@section('title', 'Tambah Mata Pelajaran - NUSA')

@section('content')
    <div class="page-header">
        <div>
            <p class="eyebrow">Akademik</p>
            <h1 class="page-title">Tambah mata pelajaran</h1>
        </div>

        <a href="{{ route('mata-pelajaran.index') }}" class="button button-muted">Kembali</a>
    </div>

    <form action="{{ route('mata-pelajaran.store') }}" method="POST">
        @csrf
        @include('mata-pelajaran.partials.form', ['tombol' => 'Simpan mata pelajaran'])
    </form>
@endsection
