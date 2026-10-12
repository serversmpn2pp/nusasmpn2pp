@extends('layouts.app')
@section('title', ($monitoring ? 'Monitoring Tindak Lanjut Surat' : 'Tindak Lanjut Surat Saya').' - NUSA')
@section('content')
    @include('persuratan._style')
    <div class="page-header"><div><p class="eyebrow">Persuratan &amp; Arsip</p><h1 class="page-title">{{ $monitoring ? 'Monitoring tindak lanjut surat' : 'Tindak lanjut surat saya' }}</h1></div><a class="button button-muted" href="{{ route($monitoring ? 'persuratan.index' : 'surat-saya.index') }}">{{ $monitoring ? 'Arsip surat' : 'Surat tugas saya' }}</a></div>
    @include('persuratan._messages')
    <form method="GET" action="{{ route($monitoring ? 'tindak-lanjut-surat.monitoring' : 'tindak-lanjut-surat.index') }}" class="surat-filter">
        <div><label for="kata_kunci">Cari surat / penerima</label><input class="input" id="kata_kunci" name="kata_kunci" value="{{ $filter['kata_kunci'] ?? '' }}" maxlength="120" placeholder="Nomor, perihal, atau nama pegawai"></div>
        <div><label for="jenis">Jenis tindak lanjut</label><select class="select" id="jenis" name="jenis"><option value="">Semua jenis</option><option value="disposisi" @selected(($filter['jenis'] ?? '') === 'disposisi')>Disposisi surat masuk</option><option value="tugas" @selected(($filter['jenis'] ?? '') === 'tugas')>Pelaksanaan surat tugas</option></select></div>
        <div><label for="status">Status</label><select class="select" id="status" name="status"><option value="">Semua status</option>@foreach(\App\Models\TindakLanjutSuratTu::STATUS as $kode => $label)<option value="{{ $kode }}" @selected(($filter['status'] ?? '') === $kode)>{{ $label }}</option>@endforeach</select></div>
        <div class="surat-filter-actions"><label><input type="checkbox" name="terlambat" value="1" @checked($filter['terlambat'] ?? false)> Melewati tenggat</label>@if($monitoring)<label><input type="checkbox" name="pemeriksaan_saya" value="1" @checked($filter['pemeriksaan_saya'] ?? false)> Menunggu pemeriksaan saya</label>@endif<button class="button button-primary">Tampilkan</button><a class="button button-muted" href="{{ route($monitoring ? 'tindak-lanjut-surat.monitoring' : 'tindak-lanjut-surat.index') }}">Reset</a></div>
    </form>
    <div class="surat-summary"><div><span>Belum mulai</span><strong>{{ $jumlah['belum_mulai'] ?? 0 }}</strong></div><div><span>Diproses / perbaikan</span><strong>{{ ($jumlah['diproses'] ?? 0) + ($jumlah['perlu_perbaikan'] ?? 0) }}</strong></div><div><span>Menunggu pemeriksaan</span><strong>{{ $jumlah['menunggu_pemeriksaan'] ?? 0 }}</strong></div><div><span>Selesai</span><strong>{{ $jumlah['selesai'] ?? 0 }}</strong></div></div>
    <div class="surat-shell">@if($tugas->isEmpty())<div class="surat-empty">Belum ada tindak lanjut yang sesuai.</div>@else
        <table class="surat-table surat-table--list"><thead><tr><th>Surat</th><th>Penerima</th><th>Tenggat</th><th>Status tindak lanjut</th><th>Pemeriksa</th><th></th></tr></thead><tbody>@foreach($tugas as $t)<tr>
            <td><a href="{{ route('tindak-lanjut-surat.show', $t) }}">{{ $t->surat->nomor_surat }}</a><small>{{ $t->surat->perihal }}</small><small>{{ $t->jenis === 'disposisi' ? 'Disposisi surat masuk' : 'Pelaksanaan surat tugas' }}</small></td>
            <td data-label="Penerima">{{ $t->nama_pegawai }}</td><td data-label="Tenggat">{{ $t->batas_tanggal->format('d-m-Y') }}@if($t->terlambat())<small class="surat-late">Melewati tenggat</small>@endif</td>
            <td data-label="Status"><span class="surat-status surat-status--{{ $t->status }}">{{ \App\Models\TindakLanjutSuratTu::STATUS[$t->status] }}</span>@if($t->surat->status === 'dibatalkan')<small class="surat-late">Surat dibatalkan / digantikan</small>@endif</td><td data-label="Pemeriksa">{{ $t->pemeriksa?->nama ?? '-' }}</td><td><a class="button button-muted button-sm" href="{{ route('tindak-lanjut-surat.show', $t) }}">Buka</a></td>
        </tr>@endforeach</tbody></table>@endif</div><div class="surat-pagination">{{ $tugas->links() }}</div>
@endsection
