@extends('layouts.app')

@section('title', 'Edit Ujian Terpusat - NUSA')

@section('content')
    <div class="page-header">
        <div>
            <p class="eyebrow">Ujian Terpusat</p>
            <h1 class="page-title">Edit informasi kegiatan</h1>
            <p class="page-subtitle">{{ $kegiatan->nama }}</p>
        </div>
        <a href="{{ route('ujian-terpusat.show', $kegiatan) }}" class="button button-muted">Kembali</a>
    </div>

    <form action="{{ route('ujian-terpusat.update', $kegiatan) }}" method="POST">
        @csrf
        @method('PUT')
        @include('ujian-terpusat.partials.form', ['tombol' => 'Simpan perubahan'])
    </form>
@endsection
