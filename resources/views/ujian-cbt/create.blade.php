@extends('layouts.app')

@section('title', 'Tambah Paket CBT - NUSA')

@section('content')
    <div class="page-header">
        <div>
            <p class="eyebrow">CBT</p>
            <h1 class="page-title">Tambah paket CBT</h1>
        </div>

        <a href="{{ route('ujian-cbt.index') }}" class="button button-muted">Kembali</a>
    </div>

    <form action="{{ route('ujian-cbt.store') }}" method="POST">
        @csrf
        @include('ujian-cbt.partials.form', ['tombol' => 'Simpan paket CBT'])
    </form>
@endsection
