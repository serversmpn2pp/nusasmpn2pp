@extends('layouts.app')
@section('admin-module', 'humas')
@section('title', 'Kelola FAQ - NUSA')
@section('content')
@include('humas._operasional-style')
<div class="page-header"><div><p class="eyebrow">Humas / FAQ & Bantuan</p><h1 class="page-title">{{ $faq->exists?'Edit pertanyaan':'Tambah pertanyaan' }}</h1></div><a class="button button-muted" href="{{ route('faq-humas.index') }}">Kembali</a></div>
@include('agenda-humas._messages')
<form method="POST" action="{{ $faq->exists?route('faq-humas.update',$faq):route('faq-humas.store') }}">@csrf
@if($faq->exists)@method('PUT')<input type="hidden" name="versi" value="{{ $faq->versi }}">@else<input type="hidden" name="token_pembuatan" value="{{ old('token_pembuatan',$token) }}">@endif
<section class="ho-band ho-band--blue"><h2><i data-lucide="circle-help" aria-hidden="true"></i>Pertanyaan & jawaban</h2><div class="field"><label for="pertanyaan">Pertanyaan</label><input class="input" id="pertanyaan" name="pertanyaan" maxlength="240" value="{{ old('pertanyaan',$faq->pertanyaan) }}" required></div><div class="field"><label for="jawaban">Jawaban</label><textarea class="textarea" id="jawaban" name="jawaban" rows="8" minlength="5" maxlength="10000" required>{{ old('jawaban',$faq->jawaban) }}</textarea></div></section>
<section class="ho-band"><h2><i data-lucide="users" aria-hidden="true"></i>Pengelompokan & pembaca</h2><div class="ho-grid">
@foreach(['urusan'=>['Urusan',\App\Models\DokumenHumas::URUSAN],'sasaran'=>['Pembaca',\App\Models\FaqHumas::SASARAN]] as $k=>[$label,$opsi])<div class="field"><label for="{{ $k }}">{{ $label }}</label><select class="select" id="{{ $k }}" name="{{ $k }}">@foreach($opsi as $key=>$v)<option value="{{ $key }}" @selected(old($k,$faq->$k)===$key)>{{ $v }}</option>@endforeach</select></div>@endforeach
<div class="field"><label for="urutan">Urutan tampil</label><input type="number" class="input" id="urutan" name="urutan" min="0" max="999" value="{{ old('urutan',$faq->urutan) }}" required></div>
@if($faq->exists)<div class="field"><label for="alasan">Alasan perubahan</label><input class="input" id="alasan" name="alasan" minlength="5" maxlength="1000" value="{{ old('alasan') }}" required></div>@endif</div></section>
<div class="alert alert-warning">Disimpan sebagai draf. FAQ yang diedit perlu diterbitkan kembali agar terlihat oleh pembaca.</div><div class="actions"><button class="button button-primary"><i data-lucide="save" aria-hidden="true"></i>Simpan draf</button></div></form>
@endsection
