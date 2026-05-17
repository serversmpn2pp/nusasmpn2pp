@extends('layouts.app')

@section('title', 'Import Pegawai - NUSA')

@section('content')
    <div class="page-header">
        <div>
            <p class="eyebrow">Pegawai</p>
            <h1 class="page-title">Import Excel pegawai</h1>
        </div>

        <div class="actions">
            <a href="{{ asset('templates/template_import_pegawai.xlsx') }}" class="button button-primary" download>
                Unduh template Excel
            </a>
            <a href="{{ route('pegawai.create') }}" class="button button-muted">Tambah manual</a>
            <a href="{{ route('pegawai.index') }}" class="button button-muted">Kembali</a>
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

    <form action="{{ route('pegawai.import.store') }}" method="POST" enctype="multipart/form-data" class="panel panel-pad">
        @csrf

        <div class="form-grid">
            <div class="field span-2">
                <label for="berkas_excel">Berkas Excel pegawai</label>
                <input id="berkas_excel" name="berkas_excel" type="file" accept=".xlsx" class="file-input" required>
                <p class="help-text">Format yang diterima: .xlsx. Gunakan template yang tersedia agar posisi kolom langsung sesuai dengan NUSA.</p>
            </div>
        </div>

        <div class="form-actions" style="margin-top: 20px;">
            <button type="submit" class="button button-primary">Import pegawai</button>
        </div>
    </form>
@endsection
