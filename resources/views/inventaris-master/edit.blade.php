@extends('layouts.app')

@section('title', 'Edit ' . $judul . ' - NUSA')

@section('content')
    <div class="page-header">
        <div>
            <p class="eyebrow">Sarana Prasarana</p>
            <h1 class="page-title">Edit {{ $judulSingular }}</h1>
        </div>

        <div class="actions">
            <a href="{{ route($routePrefix . '.show', $item) }}" class="button button-muted">Detail</a>
            <a href="{{ route($routePrefix . '.index') }}" class="button button-muted">Kembali</a>
        </div>
    </div>

    <form action="{{ route($routePrefix . '.update', $item) }}" method="POST">
        @csrf
        @method('PUT')
        @include('inventaris-master.partials.form', ['tombol' => 'Simpan perubahan'])
    </form>
@endsection
