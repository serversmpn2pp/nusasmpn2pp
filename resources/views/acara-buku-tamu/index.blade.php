@extends('layouts.app')
@section('admin-module', 'humas')
@section('title', 'Acara & Pameran - NUSA')
@section('content')
@include('humas._operasional-style')
<link rel="stylesheet" href="{{ asset('css/acara-buku-tamu-admin.css') }}?v={{ filemtime(public_path('css/acara-buku-tamu-admin.css')) }}">
<div class="event-admin">
    <div class="page-header"><div><p class="eyebrow">Humas / Buku Tamu</p><h1 class="page-title">Acara & Pameran</h1></div>@if($hak_akses['dapat_kelola'])<a class="button button-primary" href="{{ route('acara-buku-tamu.create') }}"><i data-lucide="calendar-plus" aria-hidden="true"></i>Buat acara</a>@endif</div>
    @include('agenda-humas._messages')
    <nav class="event-tabs" aria-label="Buku tamu"><a href="{{ route('buku-tamu.index') }}">Kunjungan sekolah</a><a href="{{ route('acara-buku-tamu.index') }}" aria-current="page">Acara & Pameran</a></nav>
    <div class="ho-metrics"><div class="ho-metric">Acara dibuka<strong>{{ $ringkasan['dibuka'] ?? 0 }}</strong></div><div class="ho-metric">Draf<strong>{{ $ringkasan['draf'] ?? 0 }}</strong></div><div class="ho-metric">Acara ditutup<strong>{{ $ringkasan['ditutup'] ?? 0 }}</strong></div></div>
    <form method="GET" class="feature-filter event-search"><div class="field"><label for="q">Nama acara / lokasi</label><input class="input" id="q" name="q" value="{{ $filter['q'] ?? '' }}" maxlength="120" placeholder="Cari acara"></div><div class="field"><label for="status">Status</label><select class="select" name="status" id="status"><option value="">Semua status</option>@foreach(\App\Models\AcaraBukuTamu::STATUS as $key => $label)<option value="{{ $key }}" @selected(($filter['status'] ?? '') === $key)>{{ $label }}</option>@endforeach</select></div><button class="button button-primary"><i data-lucide="search" aria-hidden="true"></i>Cari</button><a class="button button-muted" href="{{ route('acara-buku-tamu.index') }}" title="Reset pencarian" aria-label="Reset pencarian"><i data-lucide="rotate-ccw" aria-hidden="true"></i></a></form>
    <section class="event-list" aria-label="Daftar acara">@forelse($daftar as $a)
        <article class="event-list-item"><img src="{{ $a->urlBanner() }}" alt="" loading="lazy"><div class="event-list-main"><span class="badge {{ $a->status === 'dibuka' ? 'badge-active' : 'badge-inactive' }}">{{ \App\Models\AcaraBukuTamu::STATUS[$a->status] }}</span><h2><a href="{{ route('acara-buku-tamu.show', $a) }}">{{ $a->nama }}</a></h2><p>{{ $a->lokasi }}</p><small>{{ $a->mulai_pada->format('d/m/Y H:i') }} &ndash; {{ $a->selesai_pada->format('d/m/Y H:i') }} WIB</small></div><div class="event-list-total"><strong>{{ number_format($a->jumlah_respon, 0, ',', '.') }}</strong><span>pengunjung tercatat</span><a class="button button-muted" href="{{ route('acara-buku-tamu.show', $a) }}">Lihat rekap<i data-lucide="arrow-right" aria-hidden="true"></i></a></div></article>
    @empty<x-ui.empty-state icon="ticket" title="Belum ada acara">Acara dan pameran sekolah akan tampil di sini.</x-ui.empty-state>@endforelse</section>
    {{ $daftar->links() }}
</div>
@endsection
