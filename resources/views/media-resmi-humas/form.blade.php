@extends('layouts.app')
@section('title', ($media->exists ? 'Edit media resmi' : 'Tambah media resmi').' - NUSA')
@section('content')
@include('media-resmi-humas._style')
<div class="publikasi-page media-page" style="max-width:1000px">
    <div class="page-header"><div><p class="eyebrow">Humas / Daftar Media Resmi</p><h1 class="page-title">{{ $media->exists ? 'Edit media resmi' : 'Tambah media resmi' }}</h1></div><a class="button button-muted" href="{{ $media->exists ? route('media-resmi-humas.show', $media) : route('media-resmi-humas.index') }}">Kembali</a></div>
    @include('agenda-humas._messages')
    <form method="POST" action="{{ $media->exists ? route('media-resmi-humas.update', $media) : route('media-resmi-humas.store') }}" data-publikasi-submit>
        @csrf
        @if ($media->exists)@method('PUT')<input type="hidden" name="versi" value="{{ old('versi', $media->versi) }}">@else<input type="hidden" name="token_pembuatan" value="{{ old('token_pembuatan', $tokenPembuatan) }}">@endif
        <section class="agenda-section"><h2>Identitas media</h2><div class="agenda-field-grid">
            <div class="field span-2"><label for="nama">Nama media</label><input class="input" id="nama" name="nama" maxlength="180" value="{{ old('nama', $media->nama) }}" required></div>
            <div class="field"><label for="jenis">Jenis media</label><select class="select" id="jenis" name="jenis" required>@foreach (\App\Models\MediaResmiHumas::JENIS as $kode => $label)<option value="{{ $kode }}" @selected(old('jenis', $media->jenis) === $kode)>{{ $label }}</option>@endforeach</select></div>
            <div class="field"><label for="identitas_akun">Nama akun publik / handle (opsional)</label><input class="input" id="identitas_akun" name="identitas_akun" maxlength="180" value="{{ old('identitas_akun', $media->identitas_akun) }}" placeholder="@namasekolah"></div>
            <div class="field span-2"><label for="tautan">Alamat website / profil media</label><input class="input" id="tautan" name="tautan" type="url" maxlength="2000" value="{{ old('tautan', $media->tautan) }}" placeholder="https://" required></div>
        </div></section>
        <section class="agenda-section"><h2>Penanggung jawab & pengelolaan</h2><div class="agenda-field-grid">
            <div class="field"><label for="penanggung_jawab">Nama penanggung jawab</label><input class="input" id="penanggung_jawab" name="penanggung_jawab" maxlength="180" value="{{ old('penanggung_jawab', $media->penanggung_jawab) }}" required></div>
            <div class="field"><label for="jabatan_penanggung_jawab">Jabatan / tugas (opsional)</label><input class="input" id="jabatan_penanggung_jawab" name="jabatan_penanggung_jawab" maxlength="100" value="{{ old('jabatan_penanggung_jawab', $media->jabatan_penanggung_jawab) }}"></div>
            <div class="field"><label for="status">Status media</label><select class="select" id="status" name="status" required>@foreach (\App\Models\MediaResmiHumas::STATUS as $kode => $label)<option value="{{ $kode }}" @selected(old('status', $media->status) === $kode)>{{ $label }}</option>@endforeach</select></div>
            <div class="field"><label for="tanggal_diperiksa">Tanggal terakhir diperiksa (opsional)</label><input class="input" id="tanggal_diperiksa" name="tanggal_diperiksa" type="date" max="{{ today()->format('Y-m-d') }}" value="{{ old('tanggal_diperiksa', $media->tanggal_diperiksa?->format('Y-m-d')) }}"></div>
            <div class="field span-2"><label for="catatan">Catatan pengelolaan (tanpa kata sandi)</label><textarea class="textarea" id="catatan" name="catatan" rows="3" maxlength="2000">{{ old('catatan', $media->catatan) }}</textarea></div>
            @if ($media->exists)<div class="field span-2"><label for="catatan_perubahan">Alasan perubahan</label><textarea class="textarea" id="catatan_perubahan" name="catatan_perubahan" rows="2" minlength="5" maxlength="2000" required>{{ old('catatan_perubahan') }}</textarea></div>@endif
        </div></section>
        <div class="agenda-actions"><button class="button button-primary" type="submit">Simpan media</button><a class="button button-muted" href="{{ $media->exists ? route('media-resmi-humas.show', $media) : route('media-resmi-humas.index') }}">Batal</a></div>
    </form>
</div>
@include('publikasi-humas._scripts')
@endsection
