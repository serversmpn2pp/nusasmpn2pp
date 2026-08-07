@extends('layouts.app')

@section('title', 'Mulai Pendampingan Siswa - NUSA')

@section('content')
    <div class="page-header">
        <div>
            <p class="eyebrow">Kesiswaan & BK</p>
            <h1 class="page-title">Mulai pendampingan siswa</h1>
            <p class="page-subtitle">Catat bantuan atau pembinaan yang akan diberikan kepada siswa.</p>
        </div>
    </div>

    @include('pendampingan-siswa.partials.form')
@endsection
