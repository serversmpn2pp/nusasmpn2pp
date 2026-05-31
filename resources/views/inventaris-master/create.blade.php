@extends('layouts.app')

@section('title', 'Tambah ' . $judul . ' - NUSA')

@section('content')
    <div class="page-header">
        <div>
            <p class="eyebrow">Sarana Prasarana</p>
            <h1 class="page-title">Tambah {{ $judulSingular }}</h1>
        </div>

        <a href="{{ route($routePrefix . '.index') }}" class="button button-muted">Kembali</a>
    </div>

    <form action="{{ route($routePrefix . '.store') }}" method="POST">
        @csrf
        @include('inventaris-master.partials.form', ['tombol' => 'Simpan ' . $judulSingular])
    </form>
@endsection
