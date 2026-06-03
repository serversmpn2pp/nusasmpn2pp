@extends('layouts.app')

@section('title', 'Tambah Soal CBT - NUSA')

@section('content')
    <div class="page-header">
        <div>
            <p class="eyebrow">CBT</p>
            <h1 class="page-title">Tambah soal CBT</h1>
        </div>

        <a href="{{ route('soal-cbt.index') }}" class="button button-muted">Kembali</a>
    </div>

    <form action="{{ route('soal-cbt.store') }}" method="POST">
        @csrf
        @include('soal-cbt.partials.form', ['tombol' => 'Simpan soal'])
    </form>
@endsection
