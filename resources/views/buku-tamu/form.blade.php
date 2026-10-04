@extends('layouts.app')
@section('title', ($tamu->exists ? 'Koreksi kunjungan' : 'Catat kedatangan').' - NUSA')
@section('content')
@include('buku-tamu._style')
<div class="tamu-page tamu-form">
    <div class="page-header"><div><p class="eyebrow">Buku Tamu Digital</p><h1 class="page-title">{{ $tamu->exists ? 'Koreksi kunjungan' : 'Catat kedatangan' }}</h1></div><a class="button button-muted" href="{{ $tamu->exists ? route('buku-tamu.show', $tamu) : route('buku-tamu.index') }}">Kembali</a></div>
    @include('buku-tamu._messages')
    <form method="POST" action="{{ $tamu->exists ? route('buku-tamu.update', $tamu) : route('buku-tamu.store') }}" enctype="multipart/form-data" @if (!$tamu->exists) data-tamu-upload @endif>
        @csrf
        @if ($tamu->exists) @method('PUT')<input type="hidden" name="versi" value="{{ old('versi', $tamu->versi) }}">@else<input type="hidden" name="token_pencatatan" value="{{ old('token_pencatatan', $token) }}">@endif
        <section class="tamu-section"><h2>Identitas tamu</h2><div class="tamu-grid">
            <div class="field"><label for="nama_tamu">Nama tamu <span aria-hidden="true">*</span></label><input class="input" id="nama_tamu" name="nama_tamu" value="{{ old('nama_tamu', $tamu->nama_tamu) }}" maxlength="180" autocomplete="name" required></div>
            <div class="field"><label for="nomor_wa">Nomor WhatsApp</label><input class="input" type="tel" id="nomor_wa" name="nomor_wa" value="{{ old('nomor_wa', $tamu->nomor_wa) }}" maxlength="32" autocomplete="tel" placeholder="08... atau +62..."></div>
            <div class="field"><label for="instansi">Instansi / asal</label><input class="input" id="instansi" name="instansi" value="{{ old('instansi', $tamu->instansi) }}" maxlength="180"></div>
            <div class="field"><label for="jabatan">Jabatan</label><input class="input" id="jabatan" name="jabatan" value="{{ old('jabatan', $tamu->jabatan) }}" maxlength="120"></div>
            <div class="field tamu-wide"><label for="alamat_instansi">Alamat instansi / asal</label><input class="input" id="alamat_instansi" name="alamat_instansi" value="{{ old('alamat_instansi', $tamu->alamat_instansi) }}" maxlength="500"></div>
        </div></section>
        <section class="tamu-section"><h2>Keperluan kunjungan</h2><div class="tamu-grid">
            <div class="field"><label for="kategori">Kategori <span aria-hidden="true">*</span></label><select id="kategori" name="kategori" class="select" required>@foreach (\App\Models\KunjunganTamu::KATEGORI as $kode => $nama)<option value="{{ $kode }}" @selected(old('kategori', $tamu->kategori) === $kode)>{{ $nama }}</option>@endforeach</select></div>
            <div class="field"><label for="pegawai_tujuan_id">Pihak yang dituju <span aria-hidden="true">*</span></label><select id="pegawai_tujuan_id" name="pegawai_tujuan_id" class="select" data-tujuan><option value="">Lainnya / tulis tujuan</option>@foreach ($pegawai as $orang)<option value="{{ $orang->id }}" @selected(old('pegawai_tujuan_id', $tamu->pegawai_tujuan_id) == $orang->id)>{{ $orang->nama_lengkap }}{{ $orang->aktif ? '' : ' (tidak aktif)' }}</option>@endforeach</select></div>
            <div class="field tamu-wide" data-tujuan-lain><label for="tujuan_lain">Nama / bagian yang dituju <span aria-hidden="true">*</span></label><input class="input" id="tujuan_lain" name="tujuan_lain" value="{{ old('tujuan_lain', $tamu->pegawai_tujuan_id ? '' : $tamu->nama_tujuan) }}" maxlength="180"></div>
            <div class="field tamu-wide"><label for="keperluan">Keperluan <span aria-hidden="true">*</span></label><textarea class="textarea" id="keperluan" name="keperluan" rows="3" maxlength="3000" required>{{ old('keperluan', $tamu->keperluan) }}</textarea></div>
            <div class="field tamu-wide"><label for="catatan">Catatan petugas</label><textarea class="textarea" id="catatan" name="catatan" rows="2" maxlength="1000">{{ old('catatan', $tamu->catatan) }}</textarea></div>
        </div></section>
        <section class="tamu-section"><h2>Waktu kunjungan</h2>
            @if ($bolehKelola)<div class="tamu-grid"><div class="field"><label for="waktu_datang">Waktu datang <span aria-hidden="true">*</span></label><input class="input" type="datetime-local" id="waktu_datang" name="waktu_datang" value="{{ old('waktu_datang', $tamu->waktu_datang?->format('Y-m-d\TH:i')) }}" required></div>@if ($tamu->exists)<div class="field"><label for="waktu_pulang">Waktu pulang</label><input class="input" type="datetime-local" id="waktu_pulang" name="waktu_pulang" value="{{ old('waktu_pulang', $tamu->waktu_pulang?->format('Y-m-d\TH:i')) }}"></div>@endif</div>
            @else<p>Dicatat otomatis saat disimpan: {{ now()->format('d-m-Y') }} (WIB).</p>@endif
        </section>
        @if (!$tamu->exists)<section class="tamu-section"><h2>Lampiran kunjungan <small style="font-size:12px;font-weight:400">(opsional)</small></h2>@include('buku-tamu._upload')</section>@endif
        <div class="tamu-footer"><button class="button button-primary" type="submit">{{ $tamu->exists ? 'Simpan koreksi' : 'Simpan kedatangan' }}</button><a class="button button-muted" href="{{ $tamu->exists ? route('buku-tamu.show', $tamu) : route('buku-tamu.index') }}">Batal</a>@if ($errors->has('versi'))<a class="button button-muted" href="{{ route('buku-tamu.edit', $tamu) }}">Muat ulang data terbaru</a>@endif</div>
    </form>
</div>
@include('buku-tamu._script')
@endsection
