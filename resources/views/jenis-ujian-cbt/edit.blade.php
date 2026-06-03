@extends('layouts.app')

@section('title', 'Edit Jenis Ujian CBT - NUSA')

@section('content')
    <div class="page-header">
        <div>
            <p class="eyebrow">CBT</p>
            <h1 class="page-title">Edit jenis ujian CBT</h1>
        </div>

        <div class="actions">
            <a href="{{ route('jenis-ujian-cbt.show', $jenisUjianCbt) }}" class="button button-muted">Detail</a>
            <a href="{{ route('jenis-ujian-cbt.index') }}" class="button button-muted">Kembali</a>
        </div>
    </div>

    <form action="{{ route('jenis-ujian-cbt.update', $jenisUjianCbt) }}" method="POST">
        @csrf
        @method('PUT')
        @include('jenis-ujian-cbt.partials.form', ['tombol' => 'Simpan perubahan'])
    </form>
@endsection
