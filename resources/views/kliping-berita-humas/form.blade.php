@extends('layouts.app')
@section('title', ($kliping->exists ? 'Edit kliping berita' : 'Tambah kliping berita').' - NUSA')
@section('content')
@include('kliping-berita-humas._style')
<div class="publikasi-page kliping-page" style="max-width:1000px">
    <div class="page-header"><div><p class="eyebrow">Humas / Kliping Berita</p><h1 class="page-title">{{ $kliping->exists ? 'Edit kliping berita' : 'Tambah kliping berita' }}</h1></div><a class="button button-muted" href="{{ $kliping->exists ? route('kliping-berita-humas.show', $kliping) : route('kliping-berita-humas.index') }}">Kembali</a></div>
    @include('agenda-humas._messages')
    <form method="POST" enctype="multipart/form-data" action="{{ $kliping->exists ? route('kliping-berita-humas.update', $kliping) : route('kliping-berita-humas.store') }}" data-publikasi-upload data-bukti-tersimpan="{{ $kliping->riwayat_dokumen_humas_id ? 1 : 0 }}">
        @csrf
        @if ($kliping->exists)@method('PUT')<input type="hidden" name="versi" value="{{ old('versi', $kliping->versi) }}">@else<input type="hidden" name="token_pembuatan" value="{{ old('token_pembuatan', $tokenPembuatan) }}">@endif
        <section class="agenda-section"><h2>Pemberitaan media luar</h2><div class="agenda-field-grid">
            <div class="field span-2"><label for="judul">Judul pemberitaan</label><input class="input" id="judul" name="judul" value="{{ old('judul', $kliping->judul) }}" maxlength="180" required></div>
            <div class="field"><label for="nama_media">Nama media luar</label><input class="input" id="nama_media" name="nama_media" value="{{ old('nama_media', $kliping->nama_media) }}" maxlength="180" required></div>
            <div class="field"><label for="jenis">Jenis media</label><select class="select" id="jenis" name="jenis" required>@foreach (\App\Models\KlipingBeritaHumas::JENIS as $kode => $label)<option value="{{ $kode }}" @selected(old('jenis', $kliping->jenis) === $kode)>{{ $label }}</option>@endforeach</select></div>
            <div class="field"><label for="tanggal_terbit">Tanggal terbit / tayang</label><input class="input" type="date" id="tanggal_terbit" name="tanggal_terbit" max="{{ today()->format('Y-m-d') }}" value="{{ old('tanggal_terbit', $kliping->tanggal_terbit?->format('Y-m-d')) }}" required></div>
            <div class="field"><label for="topik">Topik pemberitaan</label><select class="select" id="topik" name="topik" required>@foreach (\App\Models\KlipingBeritaHumas::TOPIK as $kode => $label)<option value="{{ $kode }}" @selected(old('topik', $kliping->topik) === $kode)>{{ $label }}</option>@endforeach</select></div>
            <div class="field"><label for="penulis">Penulis / wartawan (opsional)</label><input class="input" id="penulis" name="penulis" maxlength="180" value="{{ old('penulis', $kliping->penulis) }}"></div>
            <div class="field"><label for="rujukan">Edisi, halaman, atau jam tayang (opsional)</label><input class="input" id="rujukan" name="rujukan" maxlength="250" value="{{ old('rujukan', $kliping->rujukan) }}"></div>
            <div class="field span-2"><label for="tautan">Tautan berita (boleh kosong jika ada bukti)</label><input class="input" id="tautan" name="tautan" type="url" maxlength="2000" value="{{ old('tautan', $kliping->tautan) }}" placeholder="https://"></div>
            <div class="field span-2"><label for="ringkasan">Ringkasan pemberitaan (opsional)</label><textarea class="textarea" id="ringkasan" name="ringkasan" rows="4" maxlength="3000">{{ old('ringkasan', $kliping->ringkasan) }}</textarea></div>
        </div></section>
        <section class="agenda-section"><h2>Bukti pemberitaan</h2>
            @php($metode = old('metode', $kliping->exists ? 'tetap' : 'tanpa'))
            <fieldset class="kliping-source"><legend class="sr-only">Pilihan bukti pemberitaan</legend>
                @if ($kliping->exists)<label><input type="radio" name="metode" value="tetap" @checked($metode === 'tetap')>Tidak mengganti bukti</label>@else<label><input type="radio" name="metode" value="tanpa" @checked($metode === 'tanpa')>Tautan saja</label>@endif
                @izin('dokumen_humas.kelola')<label><input type="radio" name="metode" value="unggah" @checked($metode === 'unggah')>Unggah bukti</label>@endizin
                @if ($bolehDokumen)<label><input type="radio" name="metode" value="dokumen" @checked($metode === 'dokumen')>Pilih dokumen Humas</label>@endif
                @if ($kliping->exists && $kliping->riwayat_dokumen_humas_id)<label><input type="radio" name="metode" value="hapus" @checked($metode === 'hapus')>Lepaskan bukti dari kliping</label>@endif
            </fieldset>
            @if ($kliping->exists)<p class="agenda-muted" data-bukti-lama>{{ $kliping->riwayat_dokumen_humas_id ? ($bolehDokumen ? $kliping->berkas?->nama_file_asli : 'Bukti privat') : 'Belum ada berkas bukti.' }}</p>@endif
            @izin('dokumen_humas.kelola')<div class="field" data-kliping-source="unggah" @if ($metode !== 'unggah') hidden @endif><label for="berkas">PDF, JPG, PNG, WebP (maksimal 20 MB)</label><input class="file-input" type="file" id="berkas" name="berkas" accept=".pdf,.jpg,.jpeg,.png,.webp" @disabled($metode !== 'unggah')><div data-kliping-preview style="margin-top:16px"></div></div>@endizin
            @if ($bolehDokumen)<div data-kliping-source="dokumen" @if ($metode !== 'dokumen') hidden @endif><div class="field"><label for="cari_dokumen">Cari dokumen bukti</label><input class="input" id="cari_dokumen" type="search" maxlength="120" data-dokumen-search="{{ route('kliping-berita-humas.dokumen') }}" @disabled($metode !== 'dokumen')></div><div class="field" style="margin-top:14px"><label for="dokumen_humas_id">Dokumen Humas</label><select class="select" id="dokumen_humas_id" name="dokumen_humas_id" @disabled($metode !== 'dokumen')><option value="">Pilih dokumen</option>@foreach ($dokumen as $item)<option value="{{ $item->id }}" @selected(old('dokumen_humas_id') == $item->id)>{{ $item->judul }}</option>@endforeach</select><p class="agenda-muted" data-dokumen-state role="status"></p></div></div>@endif
        </section>
        <section class="agenda-section"><div class="agenda-field-grid"><div class="field"><label for="status">Status kliping</label><select class="select" id="status" name="status" required>@foreach (\App\Models\KlipingBeritaHumas::STATUS as $kode => $label)<option value="{{ $kode }}" @selected(old('status', $kliping->status) === $kode)>{{ $label }}</option>@endforeach</select></div><div class="field"><label for="catatan">Catatan arsip (opsional)</label><textarea class="textarea" id="catatan" name="catatan" rows="2" maxlength="2000">{{ old('catatan', $kliping->catatan) }}</textarea></div>@if ($kliping->exists)<div class="field span-2"><label for="catatan_perubahan">Alasan perubahan</label><textarea class="textarea" id="catatan_perubahan" name="catatan_perubahan" rows="2" minlength="5" maxlength="2000" required>{{ old('catatan_perubahan') }}</textarea></div>@endif</div></section>
        @include('publikasi-humas._upload-state')
        <div class="agenda-actions"><button class="button button-primary" type="submit">Simpan kliping</button><a class="button button-muted" href="{{ $kliping->exists ? route('kliping-berita-humas.show', $kliping) : route('kliping-berita-humas.index') }}">Batal</a></div>
    </form>
</div>
@include('publikasi-humas._scripts')
@include('kliping-berita-humas._scripts')
@endsection
