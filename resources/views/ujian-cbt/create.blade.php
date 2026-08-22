@extends('layouts.app')

@section('title', ($alur === 'kelas' ? 'Siapkan Asesmen Kelas' : 'Tambah Paket CBT') . ' - NUSA')

@section('content')
    <div class="page-header">
        <div>
            <p class="eyebrow">{{ $alur === 'kelas' ? 'Asesmen Kelas' : 'CBT' }}</p>
            <h1 class="page-title">{{ $alur === 'kelas' ? 'Siapkan asesmen kelas' : 'Tambah paket CBT' }}</h1>
            @if ($alur === 'kelas')
                <p class="help-text" style="margin-top: 8px; max-width: 720px;">Untuk asesmen pada jam mengajar guru. Pilih mata pelajaran, tingkat, kelas, waktu, dan komponen nilai tujuan. Pengaturan panitia dan ruang tidak diperlukan.</p>
            @endif
        </div>

        <a href="{{ route('ujian-cbt.index') }}" class="button button-muted">Kembali</a>
    </div>

    <form action="{{ route('ujian-cbt.store') }}" method="POST">
        @csrf
        @include('ujian-cbt.partials.form', ['tombol' => $alur === 'kelas' ? 'Simpan asesmen' : 'Simpan paket CBT'])
    </form>
@endsection
