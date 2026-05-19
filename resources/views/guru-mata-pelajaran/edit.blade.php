@extends('layouts.app')

@section('title', 'Edit Guru Mata Pelajaran - NUSA')

@section('content')
    <div class="page-header">
        <div>
            <p class="eyebrow">Akademik</p>
            <h1 class="page-title">Edit guru mata pelajaran</h1>
        </div>

        <div class="actions">
            <a href="{{ route('guru-mata-pelajaran.show', $guruMataPelajaran) }}" class="button button-muted">Detail</a>
            <a href="{{ route('guru-mata-pelajaran.index') }}" class="button button-muted">Kembali</a>
        </div>
    </div>

    <form action="{{ route('guru-mata-pelajaran.update', $guruMataPelajaran) }}" method="POST">
        @csrf
        @method('PUT')
        @include('guru-mata-pelajaran.partials.form', ['tombol' => 'Simpan perubahan'])
    </form>
@endsection
