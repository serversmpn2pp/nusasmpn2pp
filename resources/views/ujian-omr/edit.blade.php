@extends('layouts.app')

@section('title', 'Edit Ujian OMR - NUSA')

@section('content')
    <div class="page-header">
        <div>
            <p class="eyebrow">Penilaian OMR</p>
            <h1 class="page-title">Edit ujian</h1>
        </div>
        <div class="actions">
            <a href="{{ route('ujian-omr.show', $ujianOmr) }}" class="button button-muted">Detail</a>
            <a href="{{ route('ujian-omr.index') }}" class="button button-muted">Kembali</a>
        </div>
    </div>

    <form action="{{ route('ujian-omr.update', $ujianOmr) }}" method="POST">
        @csrf
        @method('PUT')
        @include('ujian-omr.partials.form', ['tombol' => 'Simpan perubahan'])
    </form>
@endsection
