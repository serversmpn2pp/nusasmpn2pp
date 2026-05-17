@extends('layouts.app')

@section('title', 'Edit Siswa - NUSA')

@section('content')
    <div class="page-header">
        <div>
            <p class="eyebrow">Siswa</p>
            <h1 class="page-title">Edit siswa</h1>
        </div>

        <a href="{{ route('siswa.show', $siswa) }}" class="button button-muted">Detail</a>
    </div>

    <form action="{{ route('siswa.update', $siswa) }}" method="POST" enctype="multipart/form-data">
        @csrf
        @method('PUT')
        @include('siswa.partials.form', ['tombol' => 'Simpan perubahan'])
    </form>
@endsection
