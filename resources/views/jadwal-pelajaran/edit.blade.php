@extends('layouts.app')

@section('title', 'Edit Jadwal Pelajaran - NUSA')

@section('content')
    <div class="page-header">
        <div>
            <p class="eyebrow">Akademik</p>
            <h1 class="page-title">Edit jadwal pelajaran</h1>
        </div>

        <div class="actions">
            <a href="{{ route('jadwal-pelajaran.show', $jadwalPelajaran) }}" class="button button-muted">Detail</a>
            <a href="{{ route('jadwal-pelajaran.index') }}" class="button button-muted">Kembali</a>
        </div>
    </div>

    <form action="{{ route('jadwal-pelajaran.update', $jadwalPelajaran) }}" method="POST">
        @csrf
        @method('PUT')
        @include('jadwal-pelajaran.partials.form', ['tombol' => 'Simpan perubahan'])
    </form>
@endsection
