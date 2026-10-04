@extends('layouts.app')
@section('title', e($agendaHumas->judul).' - Agenda Humas')
@section('content')
    @include('agenda-humas._style')
    @php
        $bolehKelola = auth()->user()->memilikiIzin('agenda_humas.kelola');
        $bolehUbah = $bolehKelola && $agendaHumas->status !== 'dibatalkan';
        $bolehDokumen = auth()->user()->memilikiIzin(['dokumen_humas.lihat', 'dokumen_humas.kelola']);
    @endphp
    <div class="page-header">
        <div class="agenda-heading"><p class="eyebrow">Humas / Agenda & Pertemuan</p><h1 class="page-title">{{ $agendaHumas->judul }}</h1><p class="help-text">{{ $agendaHumas->waktu_mulai->locale('id')->translatedFormat('l, d F Y') }} &middot; {{ $agendaHumas->waktu_mulai->format('H:i') }} WIB &middot; {{ $agendaHumas->tempat }}</p></div>
        <div class="actions"><a href="{{ route('agenda-humas.index') }}" class="button button-muted">Kembali</a>@if ($bolehKelola)<a href="{{ route('agenda-humas.edit', $agendaHumas) }}" class="button button-muted">Edit agenda</a>@endif</div>
    </div>
    @include('agenda-humas._messages')
    @if ($mitraTerkait->isNotEmpty())<div class="agenda-actions" style="margin:0 0 18px"><span class="agenda-muted">Mitra terkait:</span>@foreach ($mitraTerkait as $mitra)<a href="{{ route('kemitraan-humas.show', $mitra) }}">{{ $mitra->nama }}</a>@endforeach</div>@endif
    @if ($programKomiteTerkait->isNotEmpty())<div class="agenda-actions" style="margin:0 0 18px"><span class="agenda-muted">Program komite:</span>@foreach ($programKomiteTerkait as $program)<a href="{{ route('komite-humas.program.show', [$program->periode, $program]) }}">{{ $program->nama }}</a>@endforeach</div>@endif
    @if ($agendaHumas->status === 'dibatalkan')<div class="alert alert-warning"><strong>Agenda dibatalkan.</strong> {{ $agendaHumas->alasan_pembatalan }}</div>@endif
    <nav class="agenda-tabs" aria-label="Bagian agenda">
        @foreach (['ringkasan' => 'Ringkasan', 'peserta' => 'Peserta & Kehadiran', 'qr' => 'E-Presensi QR', 'notulen' => 'Notulen', 'tindak-lanjut' => 'Tindak Lanjut', 'dokumen' => 'Dokumen'] as $kode => $nama)
            <a href="{{ route('agenda-humas.show', [$agendaHumas, 'tab' => $kode]) }}" @if ($tab === $kode) aria-current="page" @endif>{{ $nama }}@if ($kode === 'peserta')<span class="agenda-count">{{ array_sum($rekapPresensi) }}</span>@elseif ($kode === 'tindak-lanjut')<span class="agenda-count">{{ $agendaHumas->tindakLanjut->where('status', '!=', 'selesai')->count() }}</span>@endif</a>
        @endforeach
    </nav>
    @include('agenda-humas._'.$tab)
    @include('agenda-humas._scripts')
@endsection
