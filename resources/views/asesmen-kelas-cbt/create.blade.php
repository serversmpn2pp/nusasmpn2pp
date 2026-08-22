@extends('layouts.app')

@section('title', 'Buat Asesmen Kelas - NUSA')

@section('content')
    <div class="page-header">
        <div>
            <p class="eyebrow">Asesmen Kelas</p>
            <h1 class="page-title">Buat asesmen kelas</h1>
            <p class="help-text" style="margin-top: 8px; max-width: 720px;">Siapkan ulangan berbasis CBT untuk kelas yang Anda ajar. Ruang, panitia, dan sesi ujian tidak diperlukan.</p>
        </div>
        <a href="{{ route('asesmen-kelas-cbt.index') }}" class="button button-muted">Kembali</a>
    </div>

    <form action="{{ route('asesmen-kelas-cbt.store') }}" method="POST">
        @csrf
        @include('asesmen-kelas-cbt.partials.form', ['tombol' => 'Simpan dan pilih soal'])
    </form>
@endsection
