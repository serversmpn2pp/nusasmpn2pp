@extends('layouts.app')

@section('title', 'Tambah Ujian OMR - NUSA')

@section('content')
    <div class="page-header">
        <div>
            <p class="eyebrow">Penilaian OMR</p>
            <h1 class="page-title">Tambah ujian</h1>
        </div>
        <a href="{{ route('ujian-omr.index') }}" class="button button-muted">Kembali</a>
    </div>

    <form action="{{ route('ujian-omr.store') }}" method="POST">
        @csrf
        @include('ujian-omr.partials.form', ['tombol' => 'Simpan ujian'])
    </form>
@endsection
