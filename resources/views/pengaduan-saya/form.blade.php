@extends('layouts.app')
@section('title', 'Kirim Aspirasi / Pengaduan - NUSA')
@section('content')
@include('pengaduan-humas._style')
<div class="publikasi-page pengaduan-page pengaduan-form">
    <div class="page-header"><div><p class="eyebrow">Layanan Orang Tua</p><h1 class="page-title">Kirim aspirasi / pengaduan</h1></div><a class="button button-muted" href="{{ route('pengaduan-saya.index') }}">Kembali</a></div>
    @include('agenda-humas._messages')
    <form method="POST" enctype="multipart/form-data" action="{{ route('pengaduan-saya.store') }}" data-publikasi-upload data-upload-noun="laporan">
        @csrf
        <input type="hidden" name="token_pembuatan" value="{{ old('token_pembuatan', $tokenPembuatan) }}">
        <section class="agenda-section"><h2>Laporan</h2><div class="agenda-field-grid">
            @foreach (['jenis' => ['Jenis laporan', \App\Models\PengaduanHumas::JENIS], 'kategori' => ['Kategori', \App\Models\PengaduanHumas::KATEGORI]] as $name => [$label, $options])
                <div class="field"><label for="{{ $name }}">{{ $label }}</label><select class="select" id="{{ $name }}" name="{{ $name }}" required>@foreach ($options as $value => $text)<option value="{{ $value }}" @selected(old($name, $name === 'jenis' ? 'pengaduan' : 'layanan') === $value)>{{ $text }}</option>@endforeach</select></div>
            @endforeach
            <div class="field span-2"><label for="judul">Judul laporan</label><input class="input" id="judul" name="judul" maxlength="180" required value="{{ old('judul') }}"></div>
            <div class="field span-2"><label for="isi">Isi aspirasi / pengaduan</label><textarea class="textarea" id="isi" name="isi" required minlength="10" maxlength="5000" rows="7">{{ old('isi') }}</textarea></div>
        </div></section>
        <section class="agenda-section"><h2>Identitas & privasi</h2><dl class="agenda-facts"><div><dt>Nama pelapor</dt><dd>{{ $wali->nama_lengkap ?: auth()->user()->nama }}</dd></div><div><dt>Kontak terdaftar</dt><dd>{{ $wali->nomor_wa ?: '-' }}</dd></div></dl>
            <input type="hidden" name="rahasiakan_identitas" value="0"><label class="pengaduan-check"><input type="checkbox" name="rahasiakan_identitas" value="1" @checked(old('rahasiakan_identitas', true))>Rahasiakan identitas dari petugas penanganan</label>
            <p class="pengaduan-privacy">Admin dan pengelola Humas tetap dapat mengetahui akun pelapor. Nama dan kontak tidak ditampilkan kepada petugas penanganan. Identitas yang Anda tulis sendiri dalam isi laporan dapat terbaca oleh petugas.</p>
        </section>
        <section class="agenda-section"><h2>Lampiran (opsional)</h2>@include('pengaduan-humas._file-field')</section>
        @include('publikasi-humas._upload-state')
        <div class="agenda-actions"><button class="button button-primary" type="submit">Kirim laporan</button><a class="button button-muted" href="{{ route('pengaduan-saya.index') }}">Batal</a></div>
    </form>
</div>
@include('publikasi-humas._scripts')
@include('pengaduan-humas._scripts')
@endsection
