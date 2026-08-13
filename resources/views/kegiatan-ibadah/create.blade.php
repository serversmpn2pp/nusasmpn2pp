@extends('layouts.app')
@section('title', 'Tambah Kegiatan Ibadah - NUSA')
@section('content')
    <div class="page-header"><div><p class="eyebrow">Kehadiran Siswa</p><h1 class="page-title">Tambah Kegiatan Ibadah</h1><p class="page-subtitle">Siapkan kegiatan yang akan menggunakan presensi QR siswa.</p></div><a href="{{ route('kegiatan-ibadah.index') }}" class="button button-muted">Kembali</a></div>
    <form action="{{ route('kegiatan-ibadah.store') }}" method="POST">@csrf @include('kegiatan-ibadah.partials.form',['tombol'=>'Simpan kegiatan'])</form>
@endsection
