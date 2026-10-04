@extends('layouts.app')
@section('title', 'Rincian Media Resmi - NUSA')
@section('content')
@include('media-resmi-humas._style')
<div class="publikasi-page media-page">
    <div class="page-header"><div style="min-width:0"><p class="eyebrow">Humas / Daftar Media Resmi</p><h1 class="page-title">{{ $media->nama }}</h1></div><div class="actions"><a class="button button-muted" href="{{ route('media-resmi-humas.index') }}">Daftar media</a>@izin('media_resmi_humas.kelola')<a class="button button-primary" href="{{ route('media-resmi-humas.edit', $media) }}">Edit media</a>@endizin</div></div>
    @include('agenda-humas._messages')
    <section class="agenda-section"><h2>Informasi media resmi</h2>@include('media-resmi-humas._rincian', ['dataMedia' => $media->snapshot()])<div class="agenda-actions"><a class="button button-primary" href="{{ $media->tautan }}" target="_blank" rel="noopener noreferrer">Buka media</a></div></section>
    <section class="agenda-section"><h2>Riwayat pengelolaan</h2><ul class="publikasi-history">@foreach ($riwayat as $item)<li><strong>{{ $item->aksi }}</strong><p class="agenda-muted">{{ $item->pengguna?->nama ?: 'Akun tidak tersedia' }} &middot; {{ $item->created_at->format('d-m-Y H:i') }} &middot; Versi {{ $item->versi + 1 }}</p>@if ($item->catatan_perubahan)<div class="agenda-text">{{ $item->catatan_perubahan }}</div>@endif<details><summary>Lihat data pada versi ini</summary><div class="publikasi-snapshot"><h3>{{ $item->snapshot['nama'] }}</h3>@include('media-resmi-humas._rincian', ['dataMedia' => $item->snapshot])</div></details></li>@endforeach</ul>{{ $riwayat->links() }}</section>
</div>
@endsection
