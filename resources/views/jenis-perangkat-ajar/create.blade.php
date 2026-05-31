@extends('layouts.app')

@section('title', 'Tambah Jenis Perangkat Ajar - NUSA')

@section('content')
    <div class="page-header">
        <div>
            <p class="eyebrow">Kurikulum</p>
            <h1 class="page-title">Tambah jenis perangkat ajar</h1>
        </div>

        <a href="{{ route('jenis-perangkat-ajar.index') }}" class="button button-muted">Kembali</a>
    </div>

    <form action="{{ route('jenis-perangkat-ajar.store') }}" method="POST">
        @csrf
        @include('jenis-perangkat-ajar.partials.form', ['tombol' => 'Simpan jenis perangkat'])
    </form>
@endsection
