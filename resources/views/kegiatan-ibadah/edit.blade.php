@extends('layouts.app')
@section('title', 'Edit Kegiatan Ibadah - NUSA')
@section('content')
    <div class="page-header"><div><p class="eyebrow">Kehadiran Siswa</p><h1 class="page-title">Edit Kegiatan Ibadah</h1><p class="page-subtitle">Perbarui identitas dan status kegiatan.</p></div><a href="{{ route('kegiatan-ibadah.show',$kegiatanIbadah) }}" class="button button-muted">Kembali</a></div>
    <form action="{{ route('kegiatan-ibadah.update',$kegiatanIbadah) }}" method="POST">@csrf @method('PUT') @include('kegiatan-ibadah.partials.form',['tombol'=>'Simpan perubahan'])</form>
@endsection
