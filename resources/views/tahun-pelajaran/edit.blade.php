@extends('layouts.app')

@section('title', 'Edit Tahun Pelajaran - NUSA')

@section('content')
    <div class="page-header">
        <div>
            <p class="eyebrow">Akademik</p>
            <h1 class="page-title">Edit tahun pelajaran</h1>
        </div>

        <div class="actions">
            <a href="{{ route('tahun-pelajaran.show', $tahunPelajaran) }}" class="button button-muted">Detail</a>
            <a href="{{ route('tahun-pelajaran.index') }}" class="button button-muted">Kembali</a>
        </div>
    </div>

    <form action="{{ route('tahun-pelajaran.update', $tahunPelajaran) }}" method="POST">
        @csrf
        @method('PUT')
        @include('tahun-pelajaran.partials.form', ['tombol' => 'Simpan perubahan'])
    </form>
@endsection
