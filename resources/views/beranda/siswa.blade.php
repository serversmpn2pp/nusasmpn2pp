@extends('layouts.app')

@section('title', 'Dashboard Siswa - NUSA')

@push('styles')
    <link rel="stylesheet" href="{{ asset('css/dashboard-pegawai.css') }}?v={{ filemtime(public_path('css/dashboard-pegawai.css')) }}">
    <link rel="stylesheet" href="{{ asset('css/dashboard-keluarga.css') }}?v={{ filemtime(public_path('css/dashboard-keluarga.css')) }}">
@endpush

@section('content')
    @include('beranda.partials.dashboard-keluarga', ['modeOrangTua' => false])
@endsection

@push('scripts')
    <script src="{{ asset('vendor/chartjs/chart.umd.min.js') }}" defer></script>
    <script src="{{ asset('js/dashboard-pegawai.js') }}?v={{ filemtime(public_path('js/dashboard-pegawai.js')) }}" defer></script>
@endpush
