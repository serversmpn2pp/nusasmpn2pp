@extends('layouts.app')

@section('title', 'Edit Soal - NUSA')

@section('content')
    <div class="page-header">
        <div>
            <p class="eyebrow">CBT</p>
            <h1 class="page-title">Edit soal</h1>
            <p class="page-subtitle">Perbaiki isi atau jawaban, kemudian simpan sebagai draf atau soal siap.</p>
        </div>

        <div class="actions">
            <a href="{{ route('soal-cbt.show', $soalCbt) }}" class="button button-muted">Detail</a>
            <a href="{{ route('soal-cbt.index') }}" class="button button-muted">Kembali</a>
        </div>
    </div>

    <form action="{{ route('soal-cbt.update', $soalCbt) }}" method="POST" enctype="multipart/form-data">
        @csrf
        @method('PUT')
        @include('soal-cbt.partials.form')
    </form>
@endsection
