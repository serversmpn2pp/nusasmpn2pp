@extends('layouts.app')
@section('title', 'Rincian Kliping Berita - NUSA')
@section('content')
@include('kliping-berita-humas._style')
<div class="publikasi-page kliping-page">
    <div class="page-header"><div style="min-width:0"><p class="eyebrow">Humas / Kliping Berita</p><h1 class="page-title">{{ $kliping->judul }}</h1></div><div class="actions"><a class="button button-muted" href="{{ route('kliping-berita-humas.index') }}">Daftar kliping</a>@izin('kliping_berita_humas.kelola')<a class="button button-primary" href="{{ route('kliping-berita-humas.edit', $kliping) }}">Edit kliping</a>@endizin</div></div>
    @include('agenda-humas._messages')
    <section class="agenda-section"><h2>Pemberitaan media luar</h2>@include('kliping-berita-humas._rincian', ['dataKliping' => $kliping->snapshot()])@if ($kliping->tautan)<div class="agenda-actions"><a class="button button-primary" href="{{ $kliping->tautan }}" target="_blank" rel="noopener noreferrer">Buka berita asli</a></div>@endif</section>
    <section class="agenda-section"><h2>Bukti pemberitaan</h2>
        @if ($bolehDokumen && $kliping->berkas)
            @if (str_starts_with($kliping->berkas->tipe_file, 'image/'))<img class="kliping-preview" src="{{ route('kliping-berita-humas.berkas', [$kliping, $kliping->berkas]) }}" alt="Bukti pemberitaan {{ $kliping->judul }}">@endif
            <p>{{ $kliping->berkas->nama_file_asli }} &middot; Berkas versi {{ $kliping->berkas->versi }}</p><div class="agenda-actions"><a class="button button-muted" href="{{ route('kliping-berita-humas.berkas', [$kliping, $kliping->berkas]) }}" target="_blank" rel="noopener">Buka bukti</a><a class="button button-muted" href="{{ route('kliping-berita-humas.berkas', [$kliping, $kliping->berkas, 'unduh' => 1]) }}">Unduh bukti</a><a href="{{ route('dokumen-humas.show', $kliping->berkas->dokumen) }}">Dokumen Humas</a></div>
        @elseif ($kliping->riwayat_dokumen_humas_id)<p class="agenda-muted">Bukti privat. Akses Pusat Dokumen Humas diperlukan.</p>
        @else<p class="agenda-muted">Arsip menggunakan tautan sumber berita; belum ada berkas bukti.</p>@endif
    </section>
    <section class="agenda-section"><h2>Riwayat kliping</h2><ul class="publikasi-history">@foreach ($riwayat as $item)<li><strong>{{ $item->aksi }}</strong><p class="agenda-muted">{{ $item->pengguna?->nama ?: 'Akun tidak tersedia' }} &middot; {{ $item->created_at->format('d-m-Y H:i') }} &middot; Versi {{ $item->versi + 1 }}</p>@if ($item->catatan_perubahan)<div class="agenda-text">{{ $item->catatan_perubahan }}</div>@endif<details><summary>Lihat kliping pada versi ini</summary><div class="publikasi-snapshot"><h3>{{ $item->snapshot['judul'] }}</h3>@include('kliping-berita-humas._rincian', ['dataKliping' => $item->snapshot])@if ($bolehDokumen && $item->berkas)<div class="agenda-actions"><a href="{{ route('kliping-berita-humas.berkas', [$kliping, $item->berkas, 'unduh' => 1]) }}">Unduh bukti versi ini: {{ $item->berkas->nama_file_asli }}</a></div>@endif</div></details></li>@endforeach</ul>{{ $riwayat->links() }}</section>
</div>
@endsection
