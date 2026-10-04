@extends('layouts.app')
@section('title', ($program->exists ? 'Edit' : 'Tambah').' Program Komite - NUSA')
@section('program-actions')<a class="button button-muted" href="{{ $program->exists ? route('komite-humas.program.show', [$periode, $program]) : route('komite-humas.program.index', $periode) }}">Kembali</a>@endsection
@section('content')
<div class="publikasi-page komite-page">
    @include('program-komite-humas._header', ['judulHalaman' => $program->exists ? 'Edit program kerja' : 'Tambah program kerja'])
    <form method="POST" action="{{ $program->exists ? route('komite-humas.program.update', [$periode, $program]) : route('komite-humas.program.store', $periode) }}" data-agenda-submit data-program-form>
        @csrf
        @if ($program->exists) @method('PUT')<input type="hidden" name="versi" value="{{ old('versi', $program->versi) }}">@else<input type="hidden" name="token_pembuatan" value="{{ old('token_pembuatan', $tokenPembuatan) }}">@endif
        <section class="agenda-section"><h2>Rencana program</h2><div class="agenda-field-grid">
            <div class="field span-2"><label for="nama">Nama program</label><input id="nama" name="nama" class="input" value="{{ old('nama', $program->nama) }}" maxlength="180" required autofocus></div>
            <div class="field span-2"><label for="tujuan">Tujuan</label><textarea id="tujuan" name="tujuan" class="textarea" rows="3" maxlength="5000" required>{{ old('tujuan', $program->tujuan) }}</textarea></div>
            <div class="field span-2"><label for="target_hasil">Target hasil</label><textarea id="target_hasil" name="target_hasil" class="textarea" rows="3" maxlength="5000" required>{{ old('target_hasil', $program->target_hasil) }}</textarea></div>
            <div class="field"><label for="tanggal_mulai">Tanggal mulai</label><input type="date" id="tanggal_mulai" name="tanggal_mulai" class="input" value="{{ old('tanggal_mulai', $program->tanggal_mulai?->format('Y-m-d')) }}" min="{{ $periode->tanggal_mulai->format('Y-m-d') }}" max="{{ $periode->tanggal_selesai->format('Y-m-d') }}" required></div>
            <div class="field"><label for="tanggal_selesai">Target selesai</label><input type="date" id="tanggal_selesai" name="tanggal_selesai" class="input" value="{{ old('tanggal_selesai', $program->tanggal_selesai?->format('Y-m-d')) }}" min="{{ $periode->tanggal_mulai->format('Y-m-d') }}" max="{{ $periode->tanggal_selesai->format('Y-m-d') }}" required></div>
            <div class="field"><label for="pengurus_komite_humas_id">Penanggung jawab</label><select id="pengurus_komite_humas_id" name="pengurus_komite_humas_id" class="select"><option value="">Belum ditentukan</option>@foreach ($pengurus as $p)<option value="{{ $p->id }}" @selected((string) old('pengurus_komite_humas_id', $program->pengurus_komite_humas_id) === (string) $p->id)>{{ $p->nama }} - {{ \App\Models\PengurusKomiteHumas::JABATAN[$p->jabatan] }}{{ $p->aktif ? '' : ' (Tidak aktif)' }}</option>@endforeach</select></div>
            <div class="field"><label for="status_program">Status program</label><select id="status_program" name="status" class="select" required>@foreach (\App\Models\ProgramKomiteHumas::STATUS as $kode => $nama)<option value="{{ $kode }}" @selected(old('status', $program->status) === $kode) @disabled($periode->status === 'draf' && $kode !== 'rencana')>{{ $nama }}</option>@endforeach</select></div>
        </div></section>
        <section class="agenda-section"><h2>Capaian & evaluasi</h2><div class="agenda-field-grid">
            <div class="field span-2"><label for="capaian">Capaian program</label><textarea id="capaian" name="capaian" class="textarea" rows="4" maxlength="10000">{{ old('capaian', $program->capaian) }}</textarea></div>
            <div class="field span-2"><label for="catatan_evaluasi">Evaluasi / alasan pembatalan</label><textarea id="catatan_evaluasi" name="catatan_evaluasi" class="textarea" rows="3" maxlength="5000">{{ old('catatan_evaluasi', $program->catatan_evaluasi) }}</textarea></div>
            @if ($program->exists)<div class="field span-2"><label for="catatan_perubahan">Alasan perubahan</label><textarea id="catatan_perubahan" name="catatan_perubahan" class="textarea" rows="2" minlength="5" maxlength="2000" required>{{ old('catatan_perubahan') }}</textarea></div>@endif
        </div></section>
        <div class="form-actions"><button type="submit" class="button button-primary">Simpan program</button><a class="button button-muted" href="{{ route('komite-humas.program.index', $periode) }}">Batal</a></div>
    </form>
</div>
@include('program-komite-humas._scripts')
@include('agenda-humas._scripts')
@endsection
