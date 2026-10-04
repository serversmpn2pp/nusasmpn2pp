@extends('layouts.app')
@section('title', ($aset->exists ? 'Edit aset' : 'Tambah aset').' - NUSA')
@section('content')
@include('aset-promosi-humas._style')
<div class="publikasi-page aset-page" style="max-width:1000px">
    <div class="page-header"><div><p class="eyebrow">Humas / Bank Aset Promosi</p><h1 class="page-title">{{ $aset->exists ? 'Edit aset promosi' : 'Tambah aset promosi' }}</h1></div><a class="button button-muted" href="{{ $aset->exists ? route('aset-promosi-humas.show', $aset) : route('aset-promosi-humas.index') }}">Kembali</a></div>
    @include('agenda-humas._messages')
    <form method="POST" enctype="multipart/form-data" action="{{ $aset->exists ? route('aset-promosi-humas.update', $aset) : route('aset-promosi-humas.store') }}" data-publikasi-upload>
        @csrf
        @if ($aset->exists)@method('PUT')<input type="hidden" name="versi" value="{{ old('versi', $aset->versi) }}">@else<input type="hidden" name="token_pembuatan" value="{{ old('token_pembuatan', $tokenPembuatan) }}">@endif
        <section class="agenda-section"><h2>Identitas aset</h2><div class="agenda-field-grid">
            <div class="field span-2"><label for="nama">Nama aset</label><input class="input" id="nama" name="nama" value="{{ old('nama', $aset->nama) }}" maxlength="180" required></div>
            <div class="field"><label for="kategori">Kategori</label><select class="select" id="kategori" name="kategori" required>@foreach (\App\Models\AsetPromosiHumas::KATEGORI as $kode => $label)<option value="{{ $kode }}" @selected(old('kategori', $aset->kategori) === $kode)>{{ $label }}</option>@endforeach</select></div>
            <div class="field"><label for="tanggal_aset">Tanggal aset</label><input class="input" id="tanggal_aset" name="tanggal_aset" type="date" value="{{ old('tanggal_aset', $aset->tanggal_aset?->format('Y-m-d')) }}" required></div>
            <div class="field span-2"><label for="deskripsi">Keterangan (opsional)</label><textarea class="textarea" id="deskripsi" name="deskripsi" rows="3" maxlength="5000">{{ old('deskripsi', $aset->deskripsi) }}</textarea></div>
            <div class="field"><label for="kata_kunci_aset">Kata kunci (opsional)</label><input class="input" id="kata_kunci_aset" name="kata_kunci" maxlength="200" value="{{ old('kata_kunci', $aset->kata_kunci) }}"></div>
            <div class="field"><label for="kredit">Pembuat / pemilik (opsional)</label><input class="input" id="kredit" name="kredit" maxlength="180" value="{{ old('kredit', $aset->kredit) }}"></div>
            <div class="field span-2"><label for="ketentuan_penggunaan">Ketentuan penggunaan (opsional)</label><textarea class="textarea" id="ketentuan_penggunaan" name="ketentuan_penggunaan" rows="2" maxlength="1000">{{ old('ketentuan_penggunaan', $aset->ketentuan_penggunaan) }}</textarea></div>
        </div></section>
        <section class="agenda-section"><h2>Berkas atau tautan</h2>
            @php($metode = old('metode', $aset->exists ? 'tetap' : (auth()->user()->memilikiIzin('dokumen_humas.kelola') ? 'unggah' : 'tautan')))
            <fieldset class="aset-source"><legend class="sr-only">Pilihan berkas atau tautan</legend>
                @if ($aset->exists)<label><input type="radio" name="metode" value="tetap" @checked($metode === 'tetap')>Tidak mengganti berkas / tautan</label>@endif
                @izin('dokumen_humas.kelola')<label><input type="radio" name="metode" value="unggah" @checked($metode === 'unggah')>Unggah berkas</label>@endizin
                @if ($bolehDokumen)<label><input type="radio" name="metode" value="dokumen" @checked($metode === 'dokumen')>Pilih dokumen Humas</label>@endif
                <label><input type="radio" name="metode" value="tautan" @checked($metode === 'tautan')>Tautan video / media</label>
            </fieldset>
            @if ($aset->exists)<div data-aset-source="tetap" @if ($metode !== 'tetap') hidden @endif><p>{{ $aset->sumber === 'tautan' ? $aset->tautan : ($bolehDokumen ? $aset->berkas?->nama_file_asli : 'Berkas privat') }}</p></div>@endif
            @izin('dokumen_humas.kelola')<div class="field" data-aset-source="unggah" @if ($metode !== 'unggah') hidden @endif><label for="berkas">Berkas aset (PDF, Office, JPG, PNG, WebP; maksimal 20 MB)</label><input class="file-input" id="berkas" name="berkas" type="file" accept=".pdf,.doc,.docx,.xls,.xlsx,.ppt,.pptx,.jpg,.jpeg,.png,.webp" @disabled($metode !== 'unggah')></div>@endizin
            @if ($bolehDokumen)<div data-aset-source="dokumen" @if ($metode !== 'dokumen') hidden @endif><div class="field"><label for="cari_dokumen">Cari dokumen</label><input class="input" id="cari_dokumen" type="search" maxlength="120" data-dokumen-search="{{ route('aset-promosi-humas.dokumen') }}" @disabled($metode !== 'dokumen')></div><div class="field" style="margin-top:14px"><label for="dokumen_humas_id">Dokumen Humas</label><select class="select" id="dokumen_humas_id" name="dokumen_humas_id" @disabled($metode !== 'dokumen')><option value="">Pilih dokumen</option>@foreach ($dokumen as $item)<option value="{{ $item->id }}" @selected(old('dokumen_humas_id') == $item->id)>{{ $item->judul }}</option>@endforeach</select><p data-dokumen-state role="status" class="agenda-muted"></p></div></div>@endif
            <div class="field" data-aset-source="tautan" @if ($metode !== 'tautan') hidden @endif><label for="tautan">Tautan video / media</label><input class="input" type="url" id="tautan" name="tautan" value="{{ old('tautan', $aset->tautan) }}" maxlength="2000" @disabled($metode !== 'tautan')></div>
        </section>
        @if ($aset->exists)<section class="agenda-section"><div class="agenda-field-grid"><div class="field"><label for="status">Status aset</label><select class="select" id="status" name="status">@foreach (\App\Models\AsetPromosiHumas::STATUS as $kode => $label)<option value="{{ $kode }}" @selected(old('status', $aset->status) === $kode)>{{ $label }}</option>@endforeach</select></div><div class="field"><label for="catatan_revisi">Alasan perubahan</label><textarea class="textarea" id="catatan_revisi" name="catatan_revisi" rows="2" minlength="5" maxlength="2000">{{ old('catatan_revisi') }}</textarea></div></div></section>@endif
        @include('publikasi-humas._upload-state')
        <div class="agenda-actions"><button class="button button-primary" type="submit">Simpan aset</button><a class="button button-muted" href="{{ $aset->exists ? route('aset-promosi-humas.show', $aset) : route('aset-promosi-humas.index') }}">Batal</a></div>
    </form>
</div>
@include('publikasi-humas._scripts')
@include('aset-promosi-humas._scripts')
@endsection
