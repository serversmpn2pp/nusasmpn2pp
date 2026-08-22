@extends('layouts.app')

@section('title', 'Tambah Soal - NUSA')

@section('content')
    <div class="page-header">
        <div>
            <p class="eyebrow">CBT</p>
            <h1 class="page-title">Tambah soal</h1>
            <p class="page-subtitle">Pilih jenis soal, tulis pertanyaan, lalu tentukan jawabannya.</p>
        </div>

        <a href="{{ route('soal-cbt.index') }}" class="button button-muted">Kembali</a>
    </div>

    <form action="{{ route('soal-cbt.store') }}" method="POST" enctype="multipart/form-data">
        @csrf
        @include('soal-cbt.partials.form')
    </form>
@endsection
