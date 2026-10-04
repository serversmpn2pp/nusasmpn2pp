@extends('layouts.app')
@section('title', ($tiket->exists ? 'Koreksi tiket' : 'Catat tiket').' - NUSA')
@section('content')
@include('pengaduan-humas._style')
<div class="publikasi-page pengaduan-page pengaduan-form">
    <div class="page-header"><div><p class="eyebrow">Humas / Aspirasi & Pengaduan</p><h1 class="page-title">{{ $tiket->exists ? 'Koreksi tiket '.$tiket->nomor : 'Catat tiket' }}</h1></div><a class="button button-muted" href="{{ $tiket->exists ? route('pengaduan-humas.show', $tiket) : route('pengaduan-humas.index') }}">Kembali</a></div>
    @include('agenda-humas._messages')
    <form method="POST" enctype="multipart/form-data" action="{{ $tiket->exists ? route('pengaduan-humas.update', $tiket) : route('pengaduan-humas.store') }}" data-publikasi-upload data-upload-noun="tiket">
        @csrf
        @if ($tiket->exists)@method('PUT')<input type="hidden" name="versi" value="{{ old('versi', $tiket->versi) }}">@else<input type="hidden" name="token_pembuatan" value="{{ old('token_pembuatan', $tokenPembuatan) }}">@endif
        <section class="agenda-section"><h2>Laporan yang diterima</h2><div class="agenda-field-grid">
            <div class="field span-2"><label for="judul">Judul tiket</label><input class="input" id="judul" name="judul" maxlength="180" required value="{{ old('judul', $tiket->judul) }}"></div>
            @foreach (['jenis' => ['Jenis laporan', \App\Models\PengaduanHumas::JENIS], 'kategori' => ['Kategori', \App\Models\PengaduanHumas::KATEGORI], 'kanal' => ['Diterima melalui', \Illuminate\Support\Arr::except(\App\Models\PengaduanHumas::KANAL, 'akun_orang_tua')], 'prioritas' => ['Prioritas', \App\Models\PengaduanHumas::PRIORITAS]] as $name => [$label, $options])<div class="field"><label for="{{ $name }}">{{ $label }}</label><select class="select" id="{{ $name }}" name="{{ $name }}" required>@foreach ($options as $value => $text)<option value="{{ $value }}" @selected(old($name, $tiket->$name) === $value)>{{ $text }}</option>@endforeach</select></div>@endforeach
            <div class="field"><label for="tanggal_diterima">Tanggal diterima</label><input class="input" type="date" id="tanggal_diterima" name="tanggal_diterima" required max="{{ today()->format('Y-m-d') }}" value="{{ old('tanggal_diterima', $tiket->tanggal_diterima?->format('Y-m-d')) }}"></div>
            <div class="field span-2"><label for="isi">Isi laporan untuk penanganan</label><textarea class="textarea" id="isi" name="isi" required minlength="10" maxlength="5000" rows="6">{{ old('isi', $tiket->isi) }}</textarea></div>
        </div></section>
        <section class="agenda-section"><h2>Identitas pelapor &middot; Privat pengelola</h2><input type="hidden" name="anonim" value="0"><label class="pengaduan-check"><input type="checkbox" id="anonim" name="anonim" value="1" @checked(old('anonim', $tiket->anonim))>Tanpa identitas pelapor</label><div class="agenda-field-grid" data-identitas style="margin-top:18px"><div class="field"><label for="nama_pelapor">Nama pelapor</label><input class="input" id="nama_pelapor" name="nama_pelapor" maxlength="180" value="{{ old('nama_pelapor', $tiket->nama_pelapor) }}"></div><div class="field"><label for="kontak_pelapor">Kontak untuk dihubungi (opsional)</label><input class="input" id="kontak_pelapor" name="kontak_pelapor" maxlength="250" value="{{ old('kontak_pelapor', $tiket->kontak_pelapor) }}"></div></div></section>
        @if (!$tiket->exists)<section class="agenda-section"><h2>Lampiran &middot; Privat pengelola</h2>@include('pengaduan-humas._file-field')</section>@else<section class="agenda-section"><div class="field"><label for="catatan_perubahan">Alasan koreksi</label><textarea class="textarea" id="catatan_perubahan" name="catatan_perubahan" rows="3" minlength="5" maxlength="2000" required>{{ old('catatan_perubahan') }}</textarea></div></section>@endif
        @include('publikasi-humas._upload-state')
        <div class="agenda-actions"><button class="button button-primary" type="submit">Simpan tiket</button><a class="button button-muted" href="{{ $tiket->exists ? route('pengaduan-humas.show', $tiket) : route('pengaduan-humas.index') }}">Batal</a></div>
    </form>
</div>
@include('publikasi-humas._scripts')
@include('pengaduan-humas._scripts')
@endsection
