@extends('layouts.app')

@section('title', 'Buat Ujian Terpusat - NUSA')

@section('content')
    <div class="page-header">
        <div>
            <p class="eyebrow">Ujian Terpusat</p>
            <h1 class="page-title">Buat kegiatan ujian</h1>
            <p class="page-subtitle">Mulai dari identitas rangkaian ujian. Pengaturan panitia, sesi, dan ruang dilakukan setelah disimpan.</p>
        </div>
    </div>

    <form action="{{ route('ujian-terpusat.store') }}" method="POST">
        @csrf
        @include('ujian-terpusat.partials.form', ['tombol' => 'Simpan dan lanjutkan'])
    </form>
@endsection
