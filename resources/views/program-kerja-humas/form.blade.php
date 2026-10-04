@extends('layouts.app')
@section('title', ($program->exists ? 'Edit' : 'Tambah').' Program Kerja Humas - NUSA')
@section('program-actions')<a class="button button-muted" href="{{ $program->exists ? route('program-kerja-humas.show', $program) : route('program-kerja-humas.index') }}">Kembali</a>@endsection
@section('content')
<div class="publikasi-page humas-program-page">
    @include('program-kerja-humas._header', ['judulHalaman' => $program->exists ? 'Edit program kerja' : 'Tambah program kerja'])
    @if($tahun->isEmpty())<div class="alert alert-warning">Tahun pelajaran belum tersedia.</div>@endif
    <form method="POST" action="{{ $program->exists ? route('program-kerja-humas.update', $program) : route('program-kerja-humas.store') }}" data-agenda-submit data-humas-program-form>
        @csrf
        @if($program->exists)@method('PUT')<input type="hidden" name="versi" value="{{ old('versi', $program->versi) }}">@else<input type="hidden" name="token_pembuatan" value="{{ old('token_pembuatan', $tokenPembuatan) }}">@endif
        <section class="agenda-section"><h2>Rencana program</h2><div class="agenda-field-grid">
            <div class="field span-2"><label for="nama">Nama program</label><input class="input" id="nama" name="nama" value="{{ old('nama', $program->nama) }}" maxlength="180" required autofocus></div>
            <div class="field"><label for="tahun_pelajaran_id">Tahun pelajaran</label><select class="select" id="tahun_pelajaran_id" name="tahun_pelajaran_id" required><option value="">Pilih tahun pelajaran</option>@foreach($tahun as $t)<option value="{{ $t->id }}" data-mulai="{{ $t->tanggal_mulai?->format('Y-m-d') }}" data-selesai="{{ $t->tanggal_selesai?->format('Y-m-d') }}" @selected((string)old('tahun_pelajaran_id', $program->tahun_pelajaran_id) === (string)$t->id)>{{ $t->nama }}</option>@endforeach</select></div>
            @foreach(['semester' => ['Periode', \App\Models\ProgramKerjaHumas::SEMESTER], 'bidang' => ['Bidang', \App\Models\ProgramKerjaHumas::BIDANG]] as $key => [$label, $pilihan])<div class="field"><label for="{{ $key }}">{{ $label }}</label><select class="select" id="{{ $key }}" name="{{ $key }}" required>@foreach($pilihan as $kode => $nama)<option value="{{ $kode }}" @selected(old($key, $program->$key) === $kode)>{{ $nama }}</option>@endforeach</select></div>@endforeach
            <div class="field"><label for="penanggung_jawab">Penanggung jawab</label><input class="input" id="penanggung_jawab" name="penanggung_jawab" maxlength="180" value="{{ old('penanggung_jawab', $program->penanggung_jawab) }}" required></div>
            <div class="field"><label for="tanggal_mulai">Rencana mulai</label><input type="date" class="input" id="tanggal_mulai" name="tanggal_mulai" value="{{ old('tanggal_mulai', $program->tanggal_mulai?->format('Y-m-d')) }}" required></div>
            <div class="field"><label for="tanggal_selesai">Target selesai</label><input type="date" class="input" id="tanggal_selesai" name="tanggal_selesai" value="{{ old('tanggal_selesai', $program->tanggal_selesai?->format('Y-m-d')) }}" required></div>
            @foreach(['tujuan' => 'Tujuan', 'sasaran' => 'Sasaran', 'target_hasil' => 'Target hasil / indikator keberhasilan'] as $key => $label)<div class="field span-2"><label for="{{ $key }}">{{ $label }}</label><textarea class="textarea" id="{{ $key }}" name="{{ $key }}" rows="3" maxlength="5000" required>{{ old($key, $program->$key) }}</textarea></div>@endforeach
            <div class="field"><label for="target_kegiatan">Target jumlah kegiatan</label><input type="number" class="input" id="target_kegiatan" name="target_kegiatan" min="1" max="10000" step="1" value="{{ old('target_kegiatan', $program->target_kegiatan) }}" required></div>
            <div class="field"><label for="status_program">Status program</label><select class="select" id="status_program" name="status" required>@foreach(\App\Models\ProgramKerjaHumas::STATUS as $kode => $nama)<option value="{{ $kode }}" @selected(old('status', $program->status) === $kode) @disabled(!$program->exists && $kode === 'selesai')>{{ $nama }}</option>@endforeach</select></div>
        </div></section>
        <section class="agenda-section"><h2>Evaluasi program</h2><div class="agenda-field-grid"><div class="field span-2"><label for="evaluasi">Evaluasi / alasan pembatalan</label><textarea class="textarea" id="evaluasi" name="evaluasi" rows="4" maxlength="10000">{{ old('evaluasi', $program->evaluasi) }}</textarea></div>
            @if($program->exists)<div class="field span-2"><label for="catatan_perubahan">Alasan perubahan</label><textarea class="textarea" id="catatan_perubahan" name="catatan_perubahan" rows="2" minlength="5" maxlength="2000" required>{{ old('catatan_perubahan') }}</textarea></div>@endif
        </div></section>
        <div class="form-actions"><button class="button button-primary" type="submit" @disabled($tahun->isEmpty())>Simpan program</button><a class="button button-muted" href="{{ route('program-kerja-humas.index') }}">Batal</a></div>
    </form>
</div>
@include('program-kerja-humas._scripts')
@include('agenda-humas._scripts')
@endsection
