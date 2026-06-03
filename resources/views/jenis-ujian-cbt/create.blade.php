@extends('layouts.app')

@section('title', 'Tambah Jenis Ujian CBT - NUSA')

@section('content')
    <div class="page-header">
        <div>
            <p class="eyebrow">CBT</p>
            <h1 class="page-title">Tambah jenis ujian CBT</h1>
        </div>

        <a href="{{ route('jenis-ujian-cbt.index') }}" class="button button-muted">Kembali</a>
    </div>

    <form action="{{ route('jenis-ujian-cbt.store') }}" method="POST">
        @csrf
        @include('jenis-ujian-cbt.partials.form', ['tombol' => 'Simpan jenis ujian'])
    </form>
@endsection
