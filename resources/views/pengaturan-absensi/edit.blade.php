@extends('layouts.app')

@section('title', 'Edit Pengaturan Absensi - NUSA')

@section('content')
    <div class="page-header">
        <div>
            <p class="eyebrow">Absensi</p>
            <h1 class="page-title">Edit pengaturan absensi</h1>
        </div>

        <div class="actions">
            <a href="{{ route('pengaturan-absensi.show', $pengaturanAbsensi) }}" class="button button-muted">Detail</a>
            <a href="{{ route('pengaturan-absensi.index') }}" class="button button-muted">Kembali</a>
        </div>
    </div>

    <form action="{{ route('pengaturan-absensi.update', $pengaturanAbsensi) }}" method="POST">
        @csrf
        @method('PUT')
        @include('pengaturan-absensi.partials.form', ['tombol' => 'Simpan perubahan'])
    </form>
@endsection
