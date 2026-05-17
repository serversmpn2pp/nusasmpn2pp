@extends('layouts.app')

@section('title', 'Edit Pegawai - NUSA')

@section('content')
    <div class="page-header">
        <div>
            <p class="eyebrow">Pegawai</p>
            <h1 class="page-title">Edit pegawai</h1>
        </div>

        <a href="{{ route('pegawai.show', $pegawai) }}" class="button button-muted">Detail</a>
    </div>

    <form action="{{ route('pegawai.update', $pegawai) }}" method="POST" enctype="multipart/form-data">
        @csrf
        @method('PUT')
        @include('pegawai.partials.form', ['tombol' => 'Simpan perubahan'])
    </form>
@endsection
