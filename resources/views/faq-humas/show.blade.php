@extends('layouts.app')
@section('admin-module', 'humas')
@section('title', 'Pertanyaan & Jawaban - NUSA')
@section('content')
@include('humas._operasional-style')
@include('humas._faq-kinerja-style')
<div class="page-header"><div><p class="eyebrow">FAQ & Bantuan</p><h1 class="page-title">Pertanyaan & jawaban</h1></div><div class="actions"><a class="button button-muted" href="{{ route('faq-humas.index') }}">Kembali</a>@if($kelola)<a class="button button-primary" href="{{ route('faq-humas.edit',$faq) }}"><i data-lucide="pencil" aria-hidden="true"></i>Edit FAQ</a>@endif</div></div>
@include('agenda-humas._messages')
<section class="ho-band ho-band--blue"><div class="actions"><span class="badge">{{ \App\Models\DokumenHumas::URUSAN[$faq->urusan] }}</span>@if($kelola)<span class="badge badge-warning">{{ \App\Models\FaqHumas::STATUS[$faq->status] }}</span><span class="badge">{{ \App\Models\FaqHumas::SASARAN[$faq->sasaran] }}</span>@endif</div><h2 class="fk-question">{{ $faq->pertanyaan }}</h2><p class="fk-prose">{{ $faq->jawaban }}</p></section>
@if($kelola)<section class="ho-band"><h2><i data-lucide="send" aria-hidden="true"></i>Penerbitan FAQ</h2><form class="ho-grid" method="POST" action="{{ route('faq-humas.status',$faq) }}">@csrf @method('PATCH')<input type="hidden" name="versi" value="{{ $faq->versi }}"><div class="field"><label for="status">Status baru</label><select class="select" id="status" name="status">@foreach(\App\Models\FaqHumas::STATUS as $k=>$v)@if($k!==$faq->status && !($faq->status==='arsip' && $k==='terbit'))<option value="{{ $k }}">{{ $v }}</option>@endif @endforeach</select></div><div class="field"><label for="alasan">Catatan penerbitan / perubahan</label><input class="input" id="alasan" name="alasan" minlength="5" maxlength="1000" value="{{ old('alasan') }}" required></div><div class="actions span-2"><button class="button button-primary"><i data-lucide="check" aria-hidden="true"></i>Simpan status</button></div></form></section>
@include('humas._riwayat-ringkas')@endif
@endsection
