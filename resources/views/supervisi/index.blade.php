@extends('layouts.app')
@section('admin-module', 'supervisi')
@section('title','Supervisi Akademik - NUSA')
@section('content')
<div class="sp-workspace">
    <div class="page-header"><div><p class="eyebrow">Akademik</p><h1 class="page-title">Supervisi Akademik</h1></div>@if($hak_akses['kelola'])<a class="button button-primary" href="{{ route('supervisi.program.index') }}">Kelola program</a>@endif</div>
    @include('supervisi._style')
    <div class="sp-summary"><div class="sp-stat amber"><span>Draf jadwal</span><strong>{{ $statistik['draf'] ?? 0 }}</strong></div><div class="sp-stat teal"><span>Terjadwal</span><strong>{{ $statistik['terjadwal'] ?? 0 }}</strong></div><div class="sp-stat"><span>Dibatalkan</span><strong>{{ $statistik['dibatalkan'] ?? 0 }}</strong></div></div>
    <form class="feature-filter sp-filters" method="GET" action="{{ route('supervisi.index') }}">
        <div class="field"><label for="sp-search">Guru atau topik</label><input class="input" id="sp-search" name="kata_kunci" value="{{ $filter['kata_kunci'] ?? '' }}" placeholder="Cari guru atau topik"></div>
        <div class="field"><label for="sp-program">Program</label><select class="select" id="sp-program" name="program_id"><option value="">Semua program</option>@foreach($program as $p)<option value="{{ $p->id }}" @selected(($filter['program_id'] ?? '') == $p->id)>{{ $p->nama }}</option>@endforeach</select></div>
        <div class="field"><label for="sp-status">Status</label><select class="select" id="sp-status" name="status"><option value="">Semua status</option>@foreach(\App\Models\SesiSupervisi::STATUS as $k=>$v)<option value="{{ $k }}" @selected(($filter['status'] ?? '') === $k)>{{ $v }}</option>@endforeach</select></div>
        <div class="sp-actions"><button class="button button-primary">Tampilkan</button><a class="button button-muted" href="{{ route('supervisi.index') }}">Reset</a></div>
        <div class="field"><label for="sp-from">Mulai tanggal</label><input class="input" type="date" id="sp-from" name="dari" value="{{ $filter['dari'] ?? '' }}"></div><div class="field"><label for="sp-to">Sampai tanggal</label><input class="input" type="date" id="sp-to" name="sampai" value="{{ $filter['sampai'] ?? '' }}"></div>
    </form>
    @include('supervisi._sesi-table',['items'=>$daftar,'pilih'=>false])
    {{ $daftar->links() }}
</div>
@endsection
