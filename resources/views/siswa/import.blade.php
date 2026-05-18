@extends('layouts.app')

@section('title', 'Import Siswa - NUSA')

@section('content')
    <div class="page-header">
        <div>
            <p class="eyebrow">Siswa</p>
            <h1 class="page-title">Import Excel siswa</h1>
        </div>

        <div class="actions">
            <a href="{{ asset('templates/template_import_siswa.xlsx') }}" class="button button-primary" download>
                Unduh template Excel
            </a>
            <a href="{{ route('siswa.index') }}" class="button button-muted">Kembali</a>
        </div>
    </div>

    @if ($errors->any())
        <div class="alert alert-danger">
            <strong>Import belum bisa diproses.</strong>
            <ul>
                @foreach ($errors->all() as $error)
                    <li>{{ $error }}</li>
                @endforeach
            </ul>
        </div>
    @endif

    @if ($tahunPelajaranAktif)
        <div class="alert">
            Kolom <strong>KLS</strong> pada Excel akan menempatkan siswa ke kelas tahun pelajaran aktif:
            <strong>{{ $tahunPelajaranAktif->nama }}</strong>. Kelas aktif tersedia: {{ $jumlahKelasAktif }}.
        </div>
    @else
        <div class="alert alert-danger">
            Belum ada tahun pelajaran aktif. Data siswa tetap bisa dibaca, tetapi penempatan ke kelas belum bisa dilakukan.
        </div>
    @endif

    <form action="{{ route('siswa.import.store') }}" method="POST" enctype="multipart/form-data" class="panel panel-pad">
        @csrf

        <div class="form-grid">
            <div class="field span-2">
                <label for="berkas_excel">Berkas Excel siswa</label>
                <input id="berkas_excel" name="berkas_excel" type="file" accept=".xlsx" class="file-input" required>
                <p class="help-text">Format yang diterima: .xlsx. Isi kolom KLS sesuai nama kelas aktif, misalnya VII.A, VIII.B, atau IX.C.</p>
            </div>
        </div>

        <div class="form-actions" style="margin-top: 20px;">
            <button type="submit" class="button button-primary">Import siswa</button>
        </div>
    </form>
@endsection
