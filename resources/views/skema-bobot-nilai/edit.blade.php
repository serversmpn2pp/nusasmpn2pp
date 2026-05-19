@extends('layouts.app')

@section('title', 'Edit Skema Bobot Nilai - NUSA')

@section('content')
    <div class="page-header">
        <div>
            <p class="eyebrow">Penilaian</p>
            <h1 class="page-title">Edit skema bobot nilai</h1>
        </div>

        <div class="actions">
            <a href="{{ route('skema-bobot-nilai.show', $skemaBobotNilai) }}" class="button button-muted">Detail</a>
            <a href="{{ route('skema-bobot-nilai.index') }}" class="button button-muted">Kembali</a>
        </div>
    </div>

    <form action="{{ route('skema-bobot-nilai.update', $skemaBobotNilai) }}" method="POST">
        @csrf
        @method('PUT')
        @include('skema-bobot-nilai.partials.form', ['tombol' => 'Simpan perubahan'])
    </form>
@endsection
