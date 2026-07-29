@extends('layouts.app')

@section('title', 'Edit Mata Pelajaran - NUSA')

@section('content')
    <div class="page-header">
        <div>
            <p class="eyebrow">Akademik</p>
            <h1 class="page-title">Edit mata pelajaran</h1>
        </div>

        <div class="actions">
            <a href="{{ route('mata-pelajaran.show', [$mataPelajaran, 'tahun_pelajaran_id' => $tahunPelajaranId]) }}" class="button button-muted">Detail</a>
            <a href="{{ route('mata-pelajaran.index', ['tahun_pelajaran_id' => $tahunPelajaranId]) }}" class="button button-muted">Kembali</a>
        </div>
    </div>

    <form action="{{ route('mata-pelajaran.update', $mataPelajaran) }}" method="POST">
        @csrf
        @method('PUT')
        @include('mata-pelajaran.partials.form', ['tombol' => 'Simpan perubahan'])
    </form>
@endsection
