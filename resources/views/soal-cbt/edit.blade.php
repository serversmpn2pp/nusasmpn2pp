@extends('layouts.app')

@section('title', 'Edit Soal CBT - NUSA')

@section('content')
    <div class="page-header">
        <div>
            <p class="eyebrow">CBT</p>
            <h1 class="page-title">Edit soal CBT</h1>
        </div>

        <div class="actions">
            <a href="{{ route('soal-cbt.show', $soalCbt) }}" class="button button-muted">Detail</a>
            <a href="{{ route('soal-cbt.index') }}" class="button button-muted">Kembali</a>
        </div>
    </div>

    <form action="{{ route('soal-cbt.update', $soalCbt) }}" method="POST">
        @csrf
        @method('PUT')
        @include('soal-cbt.partials.form', ['tombol' => 'Simpan perubahan'])
    </form>
@endsection
