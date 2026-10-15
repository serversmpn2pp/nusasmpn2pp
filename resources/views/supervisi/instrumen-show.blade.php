@extends('layouts.app')
@section('title','Rincian Instrumen Supervisi - NUSA')
@section('content')
<div class="sp-workspace"><div class="page-header"><div><p class="eyebrow">Supervisi Akademik</p><h1 class="page-title">{{ $data['nama'] }}</h1></div><div class="sp-actions"><a class="button button-muted" href="{{ route('supervisi.instrumen.index') }}">Kembali</a>@if($data['status']==='draf')<a class="button button-primary" href="{{ route('supervisi.instrumen.edit',$data['id']) }}">Edit draf</a>@endif</div></div>@include('supervisi._style')
@if(!$data['aktif'])<p class="sp-note">Paket instrumen nonaktif. Sesi yang sudah dibuat tetap menyimpan versinya.</p>@endif
@include('supervisi._instrumen-detail')
<section class="sp-band amber"><div class="sp-head"><h2>{{ $data['status']==='draf' ? 'Penerbitan instrumen' : 'Revisi dan status instrumen' }}</h2></div><div class="sp-body"><p>{{ $data['catatan'] }}</p>
<form method="POST" action="{{ route('supervisi.instrumen.tindakan',[$data['id'],$data['status']==='draf'?'terbitkan':'revisi']) }}">@csrf<input type="hidden" name="versi" value="{{ $data['versi'] }}">
<input type="hidden" name="aktif_sekarang" value="{{ (int)$data['aktif'] }}">
<div class="field"><label for="sp-instrument-reason">Catatan / alasan perubahan</label><textarea class="input" id="sp-instrument-reason" name="alasan" minlength="10" maxlength="1000" required>{{ old('alasan') }}</textarea></div>
<label class="sp-check"><input type="checkbox" name="konfirmasi" value="1" required>Saya menyetujui tindakan ini. Sesi yang sudah dibuat tetap memakai versi instrumennya.</label>
<div class="sp-footer"><button class="button button-muted" formaction="{{ route('supervisi.instrumen.tindakan',[$data['id'],'aktif']) }}">{{ $data['aktif']?'Nonaktifkan paket':'Aktifkan paket' }}</button><button class="button button-primary" @disabled($data['status']==='draf'&&!$data['aktif'])>{{ $data['status']==='draf'?'Terbitkan instrumen':'Buat revisi baru' }}</button></div>
</form></div></section>@include('supervisi._riwayat')</div>
@endsection
