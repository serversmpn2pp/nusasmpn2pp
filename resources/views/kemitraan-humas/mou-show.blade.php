@extends('layouts.app')
@section('title', e($mou->judul).' - MoU')
@section('content')
@include('kemitraan-humas._style')
@php($statusBerlaku = $mou->statusBerlaku())
<div class="mitra-page"><div class="page-header"><div class="mitra-heading"><p class="eyebrow">{{ $mitra->nama }}</p><h1 class="page-title">{{ $mou->judul }}</h1></div><div class="actions"><a class="button button-muted" href="{{ route('kemitraan-humas.show', $mitra) }}">Kembali ke mitra</a>@izin('kemitraan_humas.kelola')<a class="button button-muted" href="{{ route('kemitraan-humas.mou.edit', [$mitra, $mou]) }}">Edit MoU</a>@endizin</div></div>
    @include('agenda-humas._messages')
    <div class="mitra-summary"><span class="mitra-badge mitra-badge--{{ $statusBerlaku }}">{{ \App\Models\KerjaSamaHumas::STATUS_BERLAKU[$statusBerlaku] }}</span>@if ($mou->tanggal_selesai)<span class="agenda-muted">Berakhir {{ $mou->tanggal_selesai->format('d-m-Y') }}</span>@endif</div>
    @if ($mou->status === 'diakhiri')<div class="alert alert-warning"><strong>Perjanjian diakhiri.</strong> {{ $mou->alasan_diakhiri }}</div>@endif
    <section class="agenda-section"><h2>Rincian perjanjian</h2><dl class="agenda-facts"><div><dt>Nomor MoU</dt><dd>{{ $mou->nomor ?: '-' }}</dd></div><div><dt>Bidang</dt><dd>{{ \App\Models\KerjaSamaHumas::BIDANG[$mou->bidang] }}</dd></div><div><dt>Tanggal mulai</dt><dd>{{ $mou->tanggal_mulai?->format('d-m-Y') ?: '-' }}</dd></div><div><dt>Tanggal berakhir</dt><dd>{{ $mou->tanggal_selesai?->format('d-m-Y') ?: '-' }}</dd></div><div><dt>Penanggung jawab sekolah</dt><dd>{{ $mou->penanggung_jawab ?: '-' }}</dd></div><div><dt>Pengingat</dt><dd>{{ $mou->ingatkan_hari_sebelum === 0 ? 'Pada hari berakhir' : $mou->ingatkan_hari_sebelum.' hari sebelum berakhir' }}</dd></div></dl><h3>Ruang lingkup / tujuan</h3><div class="agenda-text">{{ $mou->ruang_lingkup }}</div></section>
    <section class="agenda-section">
        <h2>Berkas perjanjian</h2>
        @if (!$bolehDokumen)
            <p class="agenda-muted">Akses Pusat Dokumen Humas belum diberikan.</p>
        @elseif ($mou->dokumen)
            <strong style="overflow-wrap:anywhere">{{ $mou->dokumen->judul }}</strong>
            <p class="agenda-muted">{{ $mou->dokumen->nama_file_asli }} &middot; {{ $mou->dokumen->ukuranFileTampil() }}{{ $mou->dokumen->status === 'arsip' ? ' - Diarsipkan' : '' }}</p>
            <div class="agenda-actions">
                <a class="button button-primary" href="{{ route('dokumen-humas.unduh', $mou->dokumen) }}">Unduh MoU</a>
                <a class="button button-muted" href="{{ route('dokumen-humas.show', $mou->dokumen) }}">Dokumen & riwayat revisi</a>
                @izin('dokumen_humas.kelola')
                    <a class="button button-muted" href="{{ route('dokumen-humas.edit', $mou->dokumen) }}">Perbarui berkas</a>
                @endizin
            </div>
        @else
            <p class="agenda-muted">Berkas MoU belum dihubungkan.</p>
            @izin('kemitraan_humas.kelola')
                @if ($mitra->status === 'aktif' && $mou->status !== 'diakhiri')
                    @izin('dokumen_humas.kelola')
                        <a class="button button-primary" href="{{ route('dokumen-humas.create', ['kerja_sama_humas_id' => $mou->id]) }}">Unggah berkas MoU</a>
                    @endizin
                @endif
            @endizin
        @endif
    </section>
    <section class="agenda-section"><h2>Jejak perubahan MoU</h2>@include('kemitraan-humas._riwayat')</section>
</div>@endsection
