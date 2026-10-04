@extends('layouts.app')
@section('title', 'Portofolio Akreditasi - NUSA')
@section('ak-actions')<a class="button button-muted" href="{{ $portofolio->exists ? route('akreditasi-humas.show', $portofolio) : route('akreditasi-humas.index') }}">Kembali</a>@endsection
@section('content')
<main class="ak-page">
    @include('akreditasi-humas._header', ['judulHalaman' => $portofolio->exists ? 'Edit portofolio' : 'Tambah portofolio'])
    <form method="POST" action="{{ $portofolio->exists ? route('akreditasi-humas.update', $portofolio) : route('akreditasi-humas.store') }}" data-save class="ak-section">
        @csrf
        @if($portofolio->exists)@method('PUT')<input type="hidden" name="versi" value="{{ $portofolio->versi }}">@else<input type="hidden" name="token_pembuatan" value="{{ old('token_pembuatan', $tokenPembuatan) }}">@endif
        <div class="ak-grid">
            <div class="field ak-wide"><label for="nama">Nama portofolio</label><input class="input" id="nama" name="nama" maxlength="180" value="{{ old('nama', $portofolio->nama) }}" required></div>
            <div class="field"><label for="instrumen">Instrumen / versi yang digunakan sekolah</label><input class="input" id="instrumen" name="instrumen" maxlength="180" value="{{ old('instrumen', $portofolio->instrumen) }}" required></div>
            <div class="field"><label for="tahun_pelajaran_id">Tahun pelajaran</label><select class="select" id="tahun_pelajaran_id" name="tahun_pelajaran_id" required><option value="">Pilih tahun</option>@foreach($tahun as $t)<option value="{{ $t->id }}" @selected((string)old('tahun_pelajaran_id', $portofolio->tahun_pelajaran_id) === (string)$t->id)>{{ $t->nama }}</option>@endforeach</select></div>
            <div class="field"><label for="penanggung_jawab">Penanggung jawab</label><input class="input" id="penanggung_jawab" name="penanggung_jawab" maxlength="180" value="{{ old('penanggung_jawab', $portofolio->penanggung_jawab) }}" required></div>
            <div class="field ak-wide"><label for="catatan">Catatan portofolio</label><textarea class="input" id="catatan" name="catatan" maxlength="5000">{{ old('catatan', $portofolio->catatan) }}</textarea></div>
            @if($portofolio->exists)<div class="field ak-wide"><label for="alasan">Alasan perubahan</label><textarea class="input" id="alasan" name="alasan" minlength="5" maxlength="2000" required>{{ old('alasan') }}</textarea></div>@endif
        </div>
        <div class="actions" style="margin-top:20px"><button type="submit" class="button button-primary">Simpan portofolio</button></div>
    </form>
</main>
@endsection
