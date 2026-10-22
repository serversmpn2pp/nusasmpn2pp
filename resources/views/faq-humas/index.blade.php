@extends('layouts.app')
@section('admin-module', 'humas')
@section('title', 'FAQ & Bantuan - NUSA')
@section('content')
@include('humas._operasional-style')
@include('humas._faq-kinerja-style')
<div class="page-header"><div><p class="eyebrow">Layanan Sekolah</p><h1 class="page-title">FAQ & bantuan</h1></div>@if($kelola)<a class="button button-primary" href="{{ route('faq-humas.create') }}"><i data-lucide="plus" aria-hidden="true"></i>Tambah FAQ</a>@endif</div>
@include('agenda-humas._messages')
<form class="ho-band ho-band--blue fk-search" method="GET" action="{{ route('faq-humas.index') }}">
    <div class="field"><label for="kata_kunci">Pertanyaan / kata kunci</label><input class="input" id="kata_kunci" name="kata_kunci" maxlength="120" value="{{ $filter['kata_kunci'] ?? '' }}" placeholder="Cari informasi sekolah"></div>
    <div class="field"><label for="urusan">Urusan</label><select class="select" id="urusan" name="urusan"><option value="">Semua urusan</option>@foreach(\App\Models\DokumenHumas::URUSAN as $k=>$v)<option value="{{ $k }}" @selected(($filter['urusan'] ?? '')===$k)>{{ $v }}</option>@endforeach</select></div>
    @if($kelola)<div class="field"><label for="status">Status</label><select class="select" id="status" name="status"><option value="">Semua status</option>@foreach(\App\Models\FaqHumas::STATUS as $k=>$v)<option value="{{ $k }}" @selected(($filter['status'] ?? '')===$k)>{{ $v }}</option>@endforeach</select></div><div class="field"><label for="sasaran">Pembaca</label><select class="select" id="sasaran" name="sasaran"><option value="">Semua pembaca</option>@foreach(\App\Models\FaqHumas::SASARAN as $k=>$v)<option value="{{ $k }}" @selected(($filter['sasaran'] ?? '')===$k)>{{ $v }}</option>@endforeach</select></div>@endif
    <div class="actions"><button class="button button-primary"><i data-lucide="search" aria-hidden="true"></i>Cari</button><a class="button button-muted" href="{{ route('faq-humas.index') }}">Reset</a></div>
</form>
<div class="fk-list-heading"><h2>Pertanyaan yang sering diajukan</h2><span>{{ $daftar->total() }} pertanyaan</span></div>
<div class="fk-faq-list">@forelse($daftar as $f)<details class="fk-faq"><summary><i data-lucide="circle-help" aria-hidden="true"></i><span><small>{{ \App\Models\DokumenHumas::URUSAN[$f->urusan] }}</small><strong>{{ $f->pertanyaan }}</strong></span><i class="fk-chevron" data-lucide="chevron-down" aria-hidden="true"></i></summary><div class="fk-answer"><p class="fk-prose">{{ $f->jawaban }}</p><div class="actions"><a href="{{ route('faq-humas.show',$f) }}">Buka pertanyaan</a>@if($kelola)<span class="badge {{ $f->status==='terbit'?'badge-active':'badge-warning' }}">{{ \App\Models\FaqHumas::STATUS[$f->status] }}</span><span class="badge">{{ \App\Models\FaqHumas::SASARAN[$f->sasaran] }}</span><a class="button button-muted" href="{{ route('faq-humas.edit',$f) }}">Edit FAQ</a>@endif</div></div></details>@empty<div class="empty-state"><i data-lucide="search" aria-hidden="true"></i><h3>Belum ada pertanyaan yang sesuai</h3><p>{{ $kelola ? 'Belum ada FAQ untuk filter ini.' : 'Informasi yang telah diterbitkan akan tampil di sini.' }}</p></div>@endforelse</div>
{{ $daftar->links() }}
@endsection
