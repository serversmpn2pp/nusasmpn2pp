@extends('layouts.app')
@section('title', ($agendaHumas->exists ? 'Perbarui' : 'Tambah').' Agenda Humas - NUSA')
@section('content')
    @include('agenda-humas._style')
    <div class="page-header">
        <div><p class="eyebrow">Humas / Agenda & Pertemuan</p><h1 class="page-title">{{ $agendaHumas->exists ? 'Perbarui agenda' : 'Tambah agenda' }}</h1></div>
        <a class="button button-muted" href="{{ $agendaHumas->exists ? route('agenda-humas.show', $agendaHumas) : route('agenda-humas.index') }}">Kembali</a>
    </div>
    @include('agenda-humas._messages')
    <form method="POST" action="{{ $agendaHumas->exists ? route('agenda-humas.update', $agendaHumas) : route('agenda-humas.store') }}" data-agenda-submit>
        @csrf @if ($agendaHumas->exists) @method('PUT') @endif
        @if (isset($mitraTerkait))<input type="hidden" name="mitra_humas_id" value="{{ $mitraTerkait->id }}"><p class="help-text">Mitra: {{ $mitraTerkait->nama }}</p>@endif
        <section class="agenda-section">
            <h2>Jadwal pertemuan</h2>
            <div class="agenda-field-grid">
                <div class="field span-2"><label for="judul">Nama agenda</label><input id="judul" name="judul" class="input" value="{{ old('judul', $agendaHumas->judul) }}" maxlength="180" required autofocus></div>
                <div class="field"><label for="jenis">Jenis pertemuan</label><select id="jenis" name="jenis" class="select" required>@foreach (\App\Models\AgendaHumas::JENIS as $kode => $nama)<option value="{{ $kode }}" @selected(old('jenis', $agendaHumas->jenis ?? 'orang_tua') === $kode)>{{ $nama }}</option>@endforeach</select></div>
                <div class="field"><label for="sasaran">Peserta yang diundang</label><input id="sasaran" name="sasaran" class="input" value="{{ old('sasaran', $agendaHumas->sasaran) }}" maxlength="250" placeholder="Misalnya orang tua kelas VII atau pengurus komite"></div>
                <div class="field"><label for="waktu_mulai">Tanggal & waktu mulai (WIB)</label><input type="datetime-local" id="waktu_mulai" name="waktu_mulai" class="input" value="{{ old('waktu_mulai', $agendaHumas->waktu_mulai?->format('Y-m-d\TH:i')) }}" required></div>
                <div class="field"><label for="waktu_selesai">Tanggal & waktu selesai (WIB)</label><input type="datetime-local" id="waktu_selesai" name="waktu_selesai" class="input" value="{{ old('waktu_selesai', $agendaHumas->waktu_selesai?->format('Y-m-d\TH:i')) }}" required></div>
                <div class="field"><label for="tempat">Tempat</label><input id="tempat" name="tempat" class="input" value="{{ old('tempat', $agendaHumas->tempat) }}" maxlength="180" placeholder="Ruang pertemuan atau daring" required></div>
                <div class="field"><label for="tautan_pertemuan">Tautan pertemuan daring</label><input id="tautan_pertemuan" name="tautan_pertemuan" type="url" class="input" value="{{ old('tautan_pertemuan', $agendaHumas->tautan_pertemuan) }}" maxlength="1000" placeholder="https://..."></div>
            </div>
        </section>
        <section class="agenda-section">
            <h2>Pelaksanaan & pokok agenda</h2>
            <div class="agenda-field-grid">
                <div class="field"><label for="pemimpin">Pemimpin pertemuan</label><input id="pemimpin" name="pemimpin" class="input" value="{{ old('pemimpin', $agendaHumas->pemimpin) }}" maxlength="180"></div>
                <div class="field"><label for="notulis">Notulis</label><input id="notulis" name="notulis" class="input" value="{{ old('notulis', $agendaHumas->notulis) }}" maxlength="180"></div>
                <div class="field span-2"><label for="topik">Pokok agenda</label><textarea id="topik" name="topik" class="textarea" rows="5" maxlength="10000" required>{{ old('topik', $agendaHumas->topik) }}</textarea></div>
            </div>
        </section>
        <div class="form-actions"><a class="button button-muted" href="{{ $agendaHumas->exists ? route('agenda-humas.show', $agendaHumas) : route('agenda-humas.index') }}">Batal</a><button type="submit" class="button button-primary">Simpan agenda</button></div>
    </form>
    @include('agenda-humas._scripts')
@endsection
