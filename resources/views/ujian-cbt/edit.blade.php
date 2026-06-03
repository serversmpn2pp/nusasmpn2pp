@extends('layouts.app')

@section('title', 'Edit Paket CBT - NUSA')

@section('content')
    <div class="page-header">
        <div>
            <p class="eyebrow">CBT</p>
            <h1 class="page-title">Edit paket CBT</h1>
        </div>

        <div class="actions">
            <a href="{{ route('ujian-cbt.show', $ujianCbt) }}" class="button button-muted">Detail</a>
            <a href="{{ route('ujian-cbt.index') }}" class="button button-muted">Kembali</a>
        </div>
    </div>

    <form action="{{ route('ujian-cbt.update', $ujianCbt) }}" method="POST">
        @csrf
        @method('PUT')
        @include('ujian-cbt.partials.form', ['tombol' => 'Simpan perubahan'])
    </form>
@endsection
