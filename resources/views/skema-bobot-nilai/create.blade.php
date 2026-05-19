@extends('layouts.app')

@section('title', 'Tambah Skema Bobot Nilai - NUSA')

@section('content')
    <div class="page-header">
        <div>
            <p class="eyebrow">Penilaian</p>
            <h1 class="page-title">Tambah skema bobot nilai</h1>
        </div>

        <a href="{{ route('skema-bobot-nilai.index') }}" class="button button-muted">Kembali</a>
    </div>

    <form action="{{ route('skema-bobot-nilai.store') }}" method="POST">
        @csrf
        @include('skema-bobot-nilai.partials.form', ['tombol' => 'Simpan skema'])
    </form>
@endsection
