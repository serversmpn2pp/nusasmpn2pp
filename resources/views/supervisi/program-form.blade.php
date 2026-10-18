@extends('layouts.app')
@section('admin-module', 'supervisi')
@section('title','Pengaturan Program Supervisi - NUSA')
@section('content')
<div class="sp-workspace"><div class="page-header"><div><p class="eyebrow">Supervisi Akademik</p><h1 class="page-title">{{ $program->exists ? 'Edit' : 'Tambah' }} program</h1></div><a class="button button-muted" href="{{ route('supervisi.program.index') }}">Kembali</a></div>@include('supervisi._style')
<form method="POST" action="{{ $program->exists ? route('supervisi.program.update',$program) : route('supervisi.program.store') }}">@csrf @if($program->exists) @method('PUT')<input type="hidden" name="versi" value="{{ $program->versi }}">@else<input type="hidden" name="token_pembuatan" value="{{ old('token_pembuatan',$token) }}">@endif
<section class="sp-band"><div class="sp-head"><h2>Identitas program</h2><span class="sp-badge {{ $program->status }}">{{ \App\Models\ProgramSupervisi::STATUS[$program->status] }}</span></div><div class="sp-body sp-grid">
<div class="field sp-wide"><label for="sp-name">Nama program</label><input class="input" id="sp-name" name="nama" required maxlength="180" value="{{ old('nama',$program->nama) }}"></div>
<div class="field"><label for="sp-year">Tahun pelajaran</label><select class="select" id="sp-year" name="tahun_pelajaran_id" required><option value="">Pilih tahun pelajaran</option>@foreach($tahun as $t)<option value="{{ $t->id }}" @selected(old('tahun_pelajaran_id',$program->tahun_pelajaran_id)==$t->id)>{{ $t->nama }}</option>@endforeach</select></div>
<div class="field"><label for="sp-semester">Semester</label><select class="select" id="sp-semester" name="semester">@foreach(['ganjil'=>'Ganjil','genap'=>'Genap'] as $k=>$v)<option value="{{ $k }}" @selected(old('semester',$program->semester)===$k)>{{ $v }}</option>@endforeach</select></div>
<div class="field"><label for="sp-start">Tanggal mulai</label><input class="input" id="sp-start" type="date" name="tanggal_mulai" required value="{{ old('tanggal_mulai',$program->tanggal_mulai?->toDateString()) }}"></div>
<div class="field"><label for="sp-end">Tanggal selesai</label><input class="input" id="sp-end" type="date" name="tanggal_selesai" required value="{{ old('tanggal_selesai',$program->tanggal_selesai?->toDateString()) }}"></div>
<div class="field sp-wide"><label for="sp-purpose">Tujuan program</label><textarea class="input" id="sp-purpose" name="tujuan" rows="3" required maxlength="3000">{{ old('tujuan',$program->tujuan) }}</textarea></div>
<div class="field"><label for="sp-state">Status program</label><select class="select" id="sp-state" name="status">@foreach(\App\Models\ProgramSupervisi::STATUS as $k=>$v)<option value="{{ $k }}" @selected(old('status',$program->status)===$k)>{{ $v }}</option>@endforeach</select></div>
@if($program->exists)<div class="field sp-wide"><label for="sp-reason">Alasan perubahan</label><textarea class="input" id="sp-reason" name="alasan" required minlength="10" maxlength="1000">{{ old('alasan') }}</textarea></div>@endif
</div></section><div class="sp-footer"><p>Program draf belum dapat menerbitkan jadwal.</p><button class="button button-primary">Simpan program</button></div></form></div>
@endsection
