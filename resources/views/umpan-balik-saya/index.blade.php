@extends('layouts.app')
@section('title', 'Umpan Balik Saya - NUSA')
@section('content')
<main class="uf-page uf-parent">
    @include('umpan-balik-humas._style')
    <header class="uf-head"><div><p class="eyebrow">Layanan Orang Tua</p><h1 class="page-title">Umpan Balik Saya</h1></div></header>
    @include('agenda-humas._messages')
    <nav class="agenda-tabs" aria-label="Daftar evaluasi">@foreach(['aktif'=>'Belum diisi','riwayat'=>'Riwayat','semua'=>'Semua'] as $k=>$label)<a href="{{ route('umpan-balik-saya.index',['tab'=>$k]) }}" @if($tab===$k) aria-current="page" @endif>{{ $label }}</a>@endforeach</nav>
    @forelse($daftar as $s)@php($f = $s->formulir)<article class="uf-row"><div><span class="uf-badge uf-badge--{{ $s->dikirim_pada ? 'selesai' : $f->status }}">{{ $s->dikirim_pada ? 'Sudah dikirim' : $f->labelStatus() }}</span><h2>{{ $f->judul }}</h2><p class="agenda-muted">{{ $f->mulai_pada->format('d-m-Y H:i') }} s.d. {{ $f->selesai_pada->format('d-m-Y H:i') }} WIB</p></div><a class="button {{ !$s->dikirim_pada && $f->menerimaJawaban() ? 'button-primary' : 'button-muted' }}" href="{{ route('umpan-balik-saya.show',$f) }}">{{ $s->dikirim_pada ? 'Lihat jawaban' : 'Buka evaluasi' }}</a></article>@empty<div class="agenda-empty"><strong>Belum ada formulir evaluasi pada daftar ini.</strong></div>@endforelse
    {{ $daftar->links() }}
</main>
@endsection
