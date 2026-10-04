@extends('layouts.app')
@section('title', 'Presensi Pertemuan')
@section('content')
    <div class="page-header"><h1 class="page-title">Presensi Pertemuan</h1></div>
    <div class="alert alert-warning">Gunakan akun orang tua/wali yang aktif untuk membuka undangan pertemuan. Akun yang sedang digunakan bukan akun orang tua aktif.</div>
    <a class="button button-muted" href="{{ route('beranda') }}">Kembali ke beranda</a>
@endsection
