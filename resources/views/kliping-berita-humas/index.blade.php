@extends('layouts.app')
@section('title', 'Kliping Berita - NUSA')
@section('content')
@include('kliping-berita-humas._style')
<div class="publikasi-page kliping-page">
    <div class="page-header"><div><p class="eyebrow">Humas / Pemberitaan Media Luar</p><h1 class="page-title">Kliping Berita</h1></div>@izin('kliping_berita_humas.kelola')<a class="button button-primary" href="{{ route('kliping-berita-humas.create') }}">Tambah kliping</a>@endizin</div>
    @include('agenda-humas._messages')
    <div class="agenda-metrics">@foreach (['aktif' => 'Kliping aktif', 'bulan_ini' => 'Terbit bulan ini', 'media' => 'Media luar', 'arsip' => 'Diarsipkan'] as $kode => $label)<div class="agenda-metric"><span>{{ $label }}</span><strong>{{ $statistik[$kode] }}</strong></div>@endforeach</div>
    <section class="agenda-section"><form class="kliping-filter" method="GET">
        <div class="field span-2"><label for="kata_kunci">Cari pemberitaan</label><input class="input" id="kata_kunci" name="kata_kunci" maxlength="120" value="{{ $filter['kata_kunci'] ?? '' }}" placeholder="Judul, media, atau penulis"></div>
        <div class="field"><label for="nama_media">Nama media luar</label><select class="select" id="nama_media" name="nama_media"><option value="">Semua media</option>@foreach ($daftarMedia as $nama)<option value="{{ $nama }}" @selected(($filter['nama_media'] ?? '') === $nama)>{{ $nama }}</option>@endforeach</select></div>
        <div class="field"><label for="jenis">Jenis media</label><select class="select" id="jenis" name="jenis"><option value="">Semua jenis</option>@foreach (\App\Models\KlipingBeritaHumas::JENIS as $kode => $label)<option value="{{ $kode }}" @selected(($filter['jenis'] ?? '') === $kode)>{{ $label }}</option>@endforeach</select></div>
        <div class="field"><label for="topik">Topik</label><select class="select" id="topik" name="topik"><option value="">Semua topik</option>@foreach (\App\Models\KlipingBeritaHumas::TOPIK as $kode => $label)<option value="{{ $kode }}" @selected(($filter['topik'] ?? '') === $kode)>{{ $label }}</option>@endforeach</select></div>
        <div class="field"><label for="status">Status</label><select class="select" id="status" name="status">@foreach (\App\Models\KlipingBeritaHumas::STATUS + ['semua' => 'Semua status'] as $kode => $label)<option value="{{ $kode }}" @selected(($filter['status'] ?? 'aktif') === $kode)>{{ $label }}</option>@endforeach</select></div>
        <div class="field"><label for="mulai">Terbit mulai</label><input class="input" type="date" id="mulai" name="mulai" value="{{ $filter['mulai'] ?? '' }}"></div><div class="field"><label for="sampai">Terbit sampai</label><input class="input" type="date" id="sampai" name="sampai" value="{{ $filter['sampai'] ?? '' }}"></div>
        <div class="agenda-actions" style="margin:0"><button class="button button-muted" type="submit">Tampilkan</button><a href="{{ route('kliping-berita-humas.index') }}">Reset filter</a></div>
    </form></section>
    <ul class="kliping-list">@forelse ($daftar as $kliping)<li class="kliping-row">
        <a href="{{ route('kliping-berita-humas.show', $kliping) }}" aria-label="Lihat {{ $kliping->judul }}">@if ($bolehDokumen && $kliping->berkas && str_starts_with($kliping->berkas->tipe_file, 'image/'))<img class="kliping-thumb" src="{{ route('kliping-berita-humas.berkas', [$kliping, $kliping->berkas]) }}" alt="Bukti {{ $kliping->judul }}" loading="lazy">@else<div class="kliping-thumb kliping-placeholder">{{ $kliping->riwayat_dokumen_humas_id ? 'Bukti berita' : 'Tautan berita' }}</div>@endif</a>
        <div><div class="kliping-tags"><span class="kliping-tag kliping-tag--{{ $kliping->topik }}">{{ \App\Models\KlipingBeritaHumas::TOPIK[$kliping->topik] }}</span>@if ($kliping->status === 'arsip')<span class="kliping-tag kliping-tag--arsip">Diarsipkan</span>@endif</div><h2><a href="{{ route('kliping-berita-humas.show', $kliping) }}">{{ $kliping->judul }}</a></h2><p><strong>{{ $kliping->nama_media }}</strong></p><p class="agenda-muted">{{ $kliping->tanggal_terbit->format('d-m-Y') }} &middot; {{ \App\Models\KlipingBeritaHumas::JENIS[$kliping->jenis] }}</p></div>
        <div class="agenda-actions"><a class="button button-muted button-sm" href="{{ route('kliping-berita-humas.show', $kliping) }}">Lihat kliping</a>@if ($kliping->tautan)<a class="button button-muted button-sm" href="{{ $kliping->tautan }}" target="_blank" rel="noopener noreferrer">Buka berita</a>@endif</div>
    </li>@empty<li class="agenda-empty"><strong>Belum ada kliping yang sesuai.</strong></li>@endforelse</ul>{{ $daftar->links() }}
</div>
@endsection
