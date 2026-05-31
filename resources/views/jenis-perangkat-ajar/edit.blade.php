@extends('layouts.app')

@section('title', 'Edit Jenis Perangkat Ajar - NUSA')

@section('content')
    <div class="page-header">
        <div>
            <p class="eyebrow">Kurikulum</p>
            <h1 class="page-title">Edit jenis perangkat ajar</h1>
        </div>

        <div class="actions">
            <a href="{{ route('jenis-perangkat-ajar.show', $jenisPerangkatAjar) }}" class="button button-muted">Detail</a>
            <a href="{{ route('jenis-perangkat-ajar.index') }}" class="button button-muted">Kembali</a>
        </div>
    </div>

    <form action="{{ route('jenis-perangkat-ajar.update', $jenisPerangkatAjar) }}" method="POST">
        @csrf
        @method('PUT')
        @include('jenis-perangkat-ajar.partials.form', ['tombol' => 'Simpan perubahan'])
    </form>
@endsection
