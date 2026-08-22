@extends('layouts.app')

@section('title', 'Edit Asesmen Kelas - NUSA')

@section('content')
    <div class="page-header">
        <div>
            <p class="eyebrow">Asesmen Kelas</p>
            <h1 class="page-title">Edit asesmen kelas</h1>
        </div>
        <a href="{{ route('asesmen-kelas-cbt.show', $ujianCbt) }}" class="button button-muted">Kembali</a>
    </div>

    <form action="{{ route('asesmen-kelas-cbt.update', $ujianCbt) }}" method="POST">
        @csrf
        @method('PUT')
        @include('asesmen-kelas-cbt.partials.form', ['tombol' => 'Simpan perubahan'])
    </form>
@endsection
