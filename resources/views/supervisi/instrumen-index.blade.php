@extends('layouts.app')
@section('title','Instrumen Supervisi - NUSA')
@section('content')
<div class="sp-workspace"><div class="page-header"><div><p class="eyebrow">Supervisi Akademik</p><h1 class="page-title">Instrumen supervisi</h1></div><a class="button button-primary" href="{{ route('supervisi.instrumen.create') }}">Tambah instrumen</a></div>@include('supervisi._style')
<div class="sp-table-wrap"><table class="sp-table"><thead><tr><th>Instrumen</th><th>Perencanaan</th><th>Pelaksanaan</th><th>Status</th><th>Aksi</th></tr></thead><tbody>
@forelse($daftar as $i)<tr><td><strong>{{ $i['nama'] }}</strong><small>Versi {{ $i['nomor'] }}{{ $i['aktif'] ? '' : ' · Nonaktif' }}</small></td><td>{{ $i['ringkasan']['perencanaan']['jumlah_indikator'] }} indikator<small>Maksimal {{ $i['ringkasan']['perencanaan']['skor_maksimal'] }}</small></td><td>{{ $i['ringkasan']['pelaksanaan']['jumlah_indikator'] }} indikator<small>Maksimal {{ $i['ringkasan']['pelaksanaan']['skor_maksimal'] }}</small></td><td><span class="sp-badge {{ $i['status'] }}">{{ ucfirst($i['status']) }}</span></td><td><a class="button button-muted" href="{{ route('supervisi.instrumen.show',$i['id']) }}">Buka instrumen</a></td></tr>
@empty<tr><td colspan="5" class="sp-empty">Belum ada instrumen supervisi.</td></tr>@endforelse</tbody></table></div>{{ $daftar->links() }}</div>
@endsection
