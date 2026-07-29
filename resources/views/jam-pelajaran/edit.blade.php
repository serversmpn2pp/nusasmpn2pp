@extends('layouts.app')

@section('title', 'Edit Jam Pelajaran - NUSA')

@section('content')
    <div class="page-header">
        <div>
            <p class="eyebrow">Akademik</p>
            <h1 class="page-title">Edit jam pelajaran</h1>
        </div>

        <div class="actions">
            <a href="{{ route('jam-pelajaran.show', $jamPelajaran) }}" class="button button-muted">Detail</a>
            <a href="{{ route('jam-pelajaran.index') }}" class="button button-muted">Kembali</a>
        </div>
    </div>

    <form action="{{ route('jam-pelajaran.update', $jamPelajaran) }}" method="POST">
        @csrf
        @method('PUT')
        @include('jam-pelajaran.partials.form', ['tombol' => 'Simpan dan Terapkan'])
    </form>
@endsection
