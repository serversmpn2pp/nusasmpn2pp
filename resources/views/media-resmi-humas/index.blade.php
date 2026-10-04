@extends('layouts.app')
@section('title', 'Daftar Media Resmi - NUSA')
@section('content')
@include('media-resmi-humas._style')
<div class="publikasi-page media-page">
    <div class="page-header"><div><p class="eyebrow">Humas</p><h1 class="page-title">Daftar Media Resmi</h1></div>@izin('media_resmi_humas.kelola')<a class="button button-primary" href="{{ route('media-resmi-humas.create') }}">Tambah media</a>@endizin</div>
    @include('agenda-humas._messages')
    <div class="agenda-metrics">@foreach (['aktif' => 'Media aktif', 'nonaktif' => 'Tidak aktif', 'arsip' => 'Diarsipkan', 'penanggung_jawab' => 'Penanggung jawab aktif'] as $kode => $label)<div class="agenda-metric"><span>{{ $label }}</span><strong>{{ $statistik[$kode] ?? 0 }}</strong></div>@endforeach</div>
    <section class="agenda-section"><form class="media-filter" method="GET">
        <div class="field"><label for="kata_kunci">Cari media</label><input class="input" id="kata_kunci" name="kata_kunci" value="{{ $filter['kata_kunci'] ?? '' }}" maxlength="120" placeholder="Nama, alamat, atau penanggung jawab"></div>
        <div class="field"><label for="jenis">Jenis media</label><select class="select" id="jenis" name="jenis"><option value="">Semua jenis</option>@foreach (\App\Models\MediaResmiHumas::JENIS as $kode => $label)<option value="{{ $kode }}" @selected(($filter['jenis'] ?? '') === $kode)>{{ $label }}</option>@endforeach</select></div>
        <div class="field"><label for="penanggung_jawab">Penanggung jawab</label><select class="select" id="penanggung_jawab" name="penanggung_jawab"><option value="">Semua petugas</option>@foreach ($penanggungJawab as $nama)<option value="{{ $nama }}" @selected(($filter['penanggung_jawab'] ?? '') === $nama)>{{ $nama }}</option>@endforeach</select></div>
        <div class="field"><label for="status">Status</label><select class="select" id="status" name="status">@foreach (\App\Models\MediaResmiHumas::STATUS + ['semua' => 'Semua status'] as $kode => $label)<option value="{{ $kode }}" @selected(($filter['status'] ?? 'aktif') === $kode)>{{ $label }}</option>@endforeach</select></div>
        <button class="button button-muted" type="submit">Tampilkan</button>
    </form></section>
    <ul class="media-list">@forelse ($daftar as $media)<li class="media-row">
        <div><span class="media-type media-type--{{ $media->jenis }}">{{ \App\Models\MediaResmiHumas::JENIS[$media->jenis] }}</span><h2><a href="{{ route('media-resmi-humas.show', $media) }}">{{ $media->nama }}</a></h2><a class="media-url" href="{{ $media->tautan }}" target="_blank" rel="noopener noreferrer">{{ $media->tautan }}</a>@if ($media->identitas_akun)<p class="agenda-muted">{{ $media->identitas_akun }}</p>@endif</div>
        <div class="media-pic"><p class="agenda-muted">Penanggung jawab</p><strong>{{ $media->penanggung_jawab }}</strong>@if ($media->jabatan_penanggung_jawab)<p class="agenda-muted">{{ $media->jabatan_penanggung_jawab }}</p>@endif</div>
        <div><span class="media-status media-status--{{ $media->status }}">{{ \App\Models\MediaResmiHumas::STATUS[$media->status] }}</span><p class="agenda-muted">{{ $media->tanggal_diperiksa ? 'Diperiksa '.$media->tanggal_diperiksa->format('d-m-Y') : 'Belum dicatat pemeriksaannya' }}</p></div>
        <div class="media-actions"><a class="button button-muted button-sm" href="{{ route('media-resmi-humas.show', $media) }}">Rincian</a><a class="button button-muted button-sm" href="{{ $media->tautan }}" target="_blank" rel="noopener noreferrer">Buka media</a></div>
    </li>@empty<li class="agenda-empty"><strong>Belum ada media yang sesuai.</strong></li>@endforelse</ul>{{ $daftar->links() }}
</div>
@endsection
