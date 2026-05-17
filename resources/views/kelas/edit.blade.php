@extends('layouts.app')

@section('title', 'Edit Kelas - NUSA')

@section('content')
    <div class="page-header">
        <div>
            <p class="eyebrow">Akademik</p>
            <h1 class="page-title">Edit kelas</h1>
        </div>

        <div class="actions">
            <a href="{{ route('kelas.show', $kelas) }}" class="button button-muted">Detail</a>
            <a href="{{ route('kelas.index') }}" class="button button-muted">Kembali</a>
        </div>
    </div>

    <form action="{{ route('kelas.update', $kelas) }}" method="POST">
        @csrf
        @method('PUT')
        @include('kelas.partials.form', ['tombol' => 'Simpan perubahan'])
    </form>
@endsection
