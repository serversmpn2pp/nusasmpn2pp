@extends('layouts.app')
@section('title', 'Rincian Aset Promosi - NUSA')
@section('content')
@include('aset-promosi-humas._style')
<div class="publikasi-page aset-page">
    <div class="page-header"><div style="min-width:0"><p class="eyebrow">Humas / Bank Aset Promosi</p><h1 class="page-title">{{ $aset->nama }}</h1></div><div class="actions"><a class="button button-muted" href="{{ route('aset-promosi-humas.index') }}">Daftar aset</a>@izin('aset_promosi_humas.kelola')<a class="button button-primary" href="{{ route('aset-promosi-humas.edit', $aset) }}">Edit aset</a>@endizin</div></div>
    @include('agenda-humas._messages')
    <section class="agenda-section"><h2>Informasi aset</h2><dl class="agenda-facts">
        <div><dt>Kategori</dt><dd>{{ \App\Models\AsetPromosiHumas::KATEGORI[$aset->kategori] }}</dd></div><div><dt>Status</dt><dd>{{ \App\Models\AsetPromosiHumas::STATUS[$aset->status] }}</dd></div>
        <div><dt>Tanggal aset</dt><dd>{{ $aset->tanggal_aset->format('d-m-Y') }}</dd></div><div><dt>Versi aset</dt><dd>{{ $aset->versi + 1 }}</dd></div>
        <div><dt>Kata kunci</dt><dd>{{ $aset->kata_kunci ?: '-' }}</dd></div><div><dt>Pembuat / pemilik</dt><dd>{{ $aset->kredit ?: '-' }}</dd></div>
    </dl>@if ($aset->deskripsi)<div class="agenda-text" style="margin-top:18px">{{ $aset->deskripsi }}</div>@endif @if ($aset->ketentuan_penggunaan)<p><strong>Ketentuan penggunaan</strong></p><div class="agenda-text">{{ $aset->ketentuan_penggunaan }}</div>@endif</section>
    <section class="agenda-section"><h2>Berkas / tautan</h2>
        @if ($aset->sumber === 'tautan')<a href="{{ $aset->tautan }}" target="_blank" rel="noopener noreferrer" style="overflow-wrap:anywhere">{{ $aset->tautan }}</a>
        @elseif ($bolehDokumen && $aset->berkas)
            @if (str_starts_with($aset->berkas->tipe_file, 'image/'))<img class="aset-preview" src="{{ route('aset-promosi-humas.berkas', [$aset, $aset->berkas]) }}" alt="{{ $aset->nama }}">@endif
            <p>{{ $aset->berkas->nama_file_asli }} &middot; Berkas versi {{ $aset->berkas->versi }}</p><div class="agenda-actions"><a class="button button-muted" href="{{ route('aset-promosi-humas.berkas', [$aset, $aset->berkas]) }}" target="_blank" rel="noopener">Buka berkas</a><a class="button button-muted" href="{{ route('aset-promosi-humas.berkas', [$aset, $aset->berkas, 'unduh' => 1]) }}">Unduh berkas</a><a href="{{ route('dokumen-humas.show', $aset->berkas->dokumen) }}">Dokumen Humas</a></div>
        @else<p class="agenda-muted">Berkas privat. Akses Pusat Dokumen Humas diperlukan.</p>@endif
        @if ($aset->status === 'aktif' && ($aset->sumber === 'tautan' || $bolehDokumen))
            @izin('publikasi_humas.kelola')<div class="agenda-actions"><a class="button button-primary" href="{{ route('publikasi-humas.create', ['aset_awal' => $aset->id]) }}">Buat draf dengan aset ini</a></div>@endizin
        @endif
    </section>
    @if ($bolehPublikasi)<section class="agenda-section"><h2>Dipakai dalam publikasi</h2><ul class="publikasi-history">@forelse ($pemakaian as $item)<li><a href="{{ route('publikasi-humas.show', $item->publikasi) }}">{{ $item->publikasi->judul }}</a><p class="agenda-muted">{{ \App\Models\PublikasiHumas::STATUS[$item->publikasi->status] }} &middot; Aset versi {{ $item->snapshot['versi'] + 1 }}</p></li>@empty<li class="agenda-muted">Belum digunakan dalam draf publikasi.</li>@endforelse</ul>{{ $pemakaian->links() }}</section>@endif
    <section class="agenda-section"><h2>Riwayat aset</h2><ul class="publikasi-history">@foreach ($riwayat as $item)<li><strong>{{ $item->aksi }}</strong><p class="agenda-muted">{{ $item->pengguna?->nama ?: 'Akun tidak tersedia' }} &middot; {{ $item->created_at->format('d-m-Y H:i') }} &middot; Versi {{ $item->versi + 1 }}</p>@if ($item->catatan)<div class="agenda-text">{{ $item->catatan }}</div>@endif<details><summary>Lihat versi ini</summary><div class="publikasi-snapshot"><h3>{{ $item->snapshot['nama'] }}</h3><div class="agenda-text">{{ $item->snapshot['deskripsi'] }}</div><p>{{ $item->snapshot['kredit'] }}</p><p>{{ $item->snapshot['ketentuan_penggunaan'] }}</p>@if ($item->snapshot['sumber'] === 'tautan')<a href="{{ $item->snapshot['tautan'] }}" target="_blank" rel="noopener noreferrer">Buka tautan versi ini</a>@elseif ($bolehDokumen && $item->berkas)<a href="{{ route('aset-promosi-humas.berkas', [$aset, $item->berkas, 'unduh' => 1]) }}">Unduh {{ $item->berkas->nama_file_asli }}</a>@endif</div></details></li>@endforeach</ul>{{ $riwayat->links() }}</section>
</div>
@endsection
