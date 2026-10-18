@extends('layouts.app')
@section('admin-module', 'supervisi')
@section('title','Program Supervisi - NUSA')
@section('content')
<div class="sp-workspace"><div class="page-header"><div><p class="eyebrow">Supervisi Akademik</p><h1 class="page-title">Program supervisi</h1></div>@if($hak_akses['kelola'])<a class="button button-primary" href="{{ route('supervisi.program.create') }}">Tambah program</a>@endif</div>
@include('supervisi._style')
<div class="sp-table-wrap"><table class="sp-table"><thead><tr><th>Program</th><th>Tahun dan semester</th><th>Periode</th><th>Status</th><th>Sesi</th><th>Aksi</th></tr></thead><tbody>
@forelse($daftar as $p)<tr><td><strong>{{ $p['nama'] }}</strong></td><td>{{ $p['tahun_pelajaran'] }}<small>{{ ucfirst($p['semester']) }}</small></td><td>{{ \Carbon\Carbon::parse($p['tanggal_mulai'])->format('d-m-Y') }}<small>s.d. {{ \Carbon\Carbon::parse($p['tanggal_selesai'])->format('d-m-Y') }}</small></td><td><span class="sp-badge {{ $p['status'] }}">{{ \App\Models\ProgramSupervisi::STATUS[$p['status']] }}</span></td><td>{{ $p['jumlah_sesi'] }}</td><td><a class="button button-muted" href="{{ route('supervisi.program.show',$p['id']) }}">Buka program</a></td></tr>
@empty<tr><td colspan="6" class="sp-empty">Belum ada program supervisi yang dapat ditampilkan.</td></tr>@endforelse</tbody></table></div>{{ $daftar->links() }}</div>
@endsection
