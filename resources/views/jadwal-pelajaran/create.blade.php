@extends('layouts.app')

@section('title', 'Tambah Jadwal Pelajaran - NUSA')

@section('content')
    <div class="page-header">
        <div>
            <p class="eyebrow">Akademik</p>
            <h1 class="page-title">Tambah jadwal pelajaran</h1>
        </div>

        <a href="{{ route('jadwal-pelajaran.index') }}" class="button button-muted">Kembali</a>
    </div>

    <form action="{{ route('jadwal-pelajaran.store') }}" method="POST">
        @csrf
        @include('jadwal-pelajaran.partials.form', ['tombol' => 'Simpan jadwal'])
    </form>
@endsection
