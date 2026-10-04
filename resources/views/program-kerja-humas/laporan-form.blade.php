@extends('layouts.app')
@section('title', ($laporan->exists ? 'Edit' : 'Tambah').' Laporan Pelaksanaan Humas - NUSA')
@section('program-actions')<a class="button button-muted" href="{{ $laporan->exists ? route('program-kerja-humas.laporan.show', [$program, $laporan]) : route('program-kerja-humas.show', $program) }}">Kembali</a>@endsection
@section('content')
<div class="publikasi-page humas-program-page">
    @include('program-kerja-humas._header', ['judulHalaman' => $laporan->exists ? 'Edit laporan pelaksanaan' : 'Tambah laporan pelaksanaan'])
    <p class="agenda-muted">{{ $program->nama }} &middot; {{ $program->tahunPelajaran->nama }}</p>
    @if($bolehAgenda)<form method="GET" class="agenda-filter"><div class="field"><label for="cari_agenda">Cari agenda terkait (opsional)</label><input class="input" id="cari_agenda" name="cari_agenda" maxlength="120" value="{{ request('cari_agenda') }}"></div><div class="actions"><button type="submit" class="button button-muted">Cari agenda</button></div></form>@endif
    <form method="POST" action="{{ $laporan->exists ? route('program-kerja-humas.laporan.update', [$program, $laporan]) : route('program-kerja-humas.laporan.store', $program) }}" data-agenda-submit data-humas-report-form>
        @csrf
        @if($laporan->exists)@method('PUT')<input type="hidden" name="versi" value="{{ old('versi', $laporan->versi) }}">@else<input type="hidden" name="token_pembuatan" value="{{ old('token_pembuatan', $tokenPembuatan) }}">@endif
        <section class="agenda-section"><h2>Pelaksanaan kegiatan</h2><div class="agenda-field-grid">
            <div class="field span-2"><label for="judul">Nama kegiatan</label><input class="input" id="judul" name="judul" maxlength="180" value="{{ old('judul', $laporan->judul) }}" required autofocus></div>
            @if($bolehAgenda)<div class="field span-2"><label for="agenda_humas_id">Agenda terkait</label><select class="select" id="agenda_humas_id" name="agenda_humas_id"><option value="">Tidak dikaitkan</option>@foreach($agendaTerpilih ? $agenda->prepend($agendaTerpilih)->unique('id') : $agenda as $a)<option value="{{ $a->id }}" @selected((string)old('agenda_humas_id', $laporan->agenda_humas_id) === (string)$a->id)>{{ $a->judul }} - {{ $a->waktu_mulai->format('d-m-Y') }}</option>@endforeach</select></div>@endif
            @php($maksimum = min(today()->format('Y-m-d'), $program->tahunPelajaran->tanggal_selesai?->format('Y-m-d') ?? today()->format('Y-m-d')))
            <div class="field"><label for="tanggal_mulai">Tanggal pelaksanaan mulai</label><input type="date" class="input" id="tanggal_mulai" name="tanggal_mulai" data-minimum="{{ $program->tahunPelajaran->tanggal_mulai?->format('Y-m-d') }}" data-maximum="{{ $maksimum }}" value="{{ old('tanggal_mulai', $laporan->tanggal_mulai?->format('Y-m-d')) }}" required></div>
            <div class="field"><label for="tanggal_selesai">Tanggal pelaksanaan selesai</label><input type="date" class="input" id="tanggal_selesai" name="tanggal_selesai" value="{{ old('tanggal_selesai', $laporan->tanggal_selesai?->format('Y-m-d')) }}" required></div>
            @foreach(['tempat' => 'Tempat', 'pelaksana' => 'Pelaksana / tim kegiatan'] as $key => $label)<div class="field"><label for="{{ $key }}">{{ $label }}</label><input class="input" id="{{ $key }}" name="{{ $key }}" maxlength="180" value="{{ old($key, $laporan->$key) }}" required></div>@endforeach
            <div class="field"><label for="jumlah_peserta">Jumlah peserta</label><input type="number" class="input" id="jumlah_peserta" name="jumlah_peserta" min="0" max="1000000" step="1" value="{{ old('jumlah_peserta', $laporan->jumlah_peserta) }}" required></div>
        </div></section>
        <section class="agenda-section"><h2>Hasil & tindak lanjut</h2><div class="agenda-field-grid">
            @foreach(['uraian' => 'Uraian pelaksanaan', 'hasil' => 'Hasil / capaian', 'kendala' => 'Kendala', 'tindak_lanjut' => 'Tindak lanjut'] as $key => $label)<div class="field span-2"><label for="{{ $key }}">{{ $label }}{{ in_array($key, ['kendala', 'tindak_lanjut']) ? ' (opsional)' : '' }}</label><textarea class="textarea" id="{{ $key }}" name="{{ $key }}" rows="4" maxlength="10000" @required(in_array($key, ['uraian', 'hasil']))>{{ old($key, $laporan->$key) }}</textarea></div>@endforeach
            @if($laporan->exists)<div class="field span-2"><label for="catatan_perubahan">Alasan perubahan</label><textarea class="textarea" id="catatan_perubahan" name="catatan_perubahan" rows="2" minlength="5" maxlength="2000" required>{{ old('catatan_perubahan') }}</textarea></div>@endif
        </div></section><div class="form-actions"><button type="submit" class="button button-primary">Simpan draf laporan</button><a class="button button-muted" href="{{ route('program-kerja-humas.show', $program) }}">Batal</a></div>
    </form>
</div>
@include('program-kerja-humas._scripts')
@include('agenda-humas._scripts')
@endsection
