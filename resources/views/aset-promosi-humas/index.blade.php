@extends('layouts.app')
@section('title', 'Bank Aset Promosi - NUSA')
@section('content')
@include('aset-promosi-humas._style')
<div class="publikasi-page aset-page">
    <div class="page-header"><div><p class="eyebrow">Humas</p><h1 class="page-title">Bank Aset Promosi</h1></div>@izin('aset_promosi_humas.kelola')<a class="button button-primary" href="{{ route('aset-promosi-humas.create') }}">Tambah aset</a>@endizin</div>
    @include('agenda-humas._messages')
    <div class="agenda-metrics">@foreach (['aktif' => 'Aset aktif', 'foto' => 'Foto & logo', 'tautan' => 'Tautan aktif', 'arsip' => 'Diarsipkan'] as $kode => $label)<div class="agenda-metric"><span>{{ $label }}</span><strong>{{ $statistik[$kode] }}</strong></div>@endforeach</div>
    <section class="agenda-section"><form class="aset-filter" method="GET">
        <div class="field"><label for="kata_kunci">Cari aset</label><input class="input" id="kata_kunci" name="kata_kunci" value="{{ $filter['kata_kunci'] ?? '' }}" maxlength="120" placeholder="Nama atau kata kunci"></div>
        <div class="field"><label for="kategori">Kategori</label><select class="select" id="kategori" name="kategori"><option value="">Semua kategori</option>@foreach (\App\Models\AsetPromosiHumas::KATEGORI as $kode => $label)<option value="{{ $kode }}" @selected(($filter['kategori'] ?? '') === $kode)>{{ $label }}</option>@endforeach</select></div>
        <div class="field"><label for="sumber">Jenis aset</label><select class="select" id="sumber" name="sumber"><option value="">Berkas & tautan</option><option value="berkas" @selected(($filter['sumber'] ?? '') === 'berkas')>Berkas</option><option value="tautan" @selected(($filter['sumber'] ?? '') === 'tautan')>Tautan</option></select></div>
        <div class="field"><label for="status">Status</label><select class="select" id="status" name="status">@foreach (\App\Models\AsetPromosiHumas::STATUS as $kode => $label)<option value="{{ $kode }}" @selected(($filter['status'] ?? 'aktif') === $kode)>{{ $label }}</option>@endforeach</select></div>
        <button class="button button-muted" type="submit">Tampilkan</button>
    </form></section>
    <div class="aset-grid">
        @forelse ($daftar as $aset)
            <article class="aset-item">
                <a href="{{ route('aset-promosi-humas.show', $aset) }}" aria-label="Lihat {{ $aset->nama }}">
                    @if ($bolehDokumen && $aset->berkas && str_starts_with($aset->berkas->tipe_file, 'image/'))<img class="aset-cover" src="{{ route('aset-promosi-humas.berkas', [$aset, $aset->berkas]) }}" alt="{{ $aset->nama }}" loading="lazy">
                    @else<div class="aset-cover aset-placeholder">{{ $aset->sumber === 'tautan' ? 'Tautan' : 'Berkas' }}</div>@endif
                </a>
                <div class="aset-body"><div class="aset-tags"><span>{{ \App\Models\AsetPromosiHumas::KATEGORI[$aset->kategori] }}</span>@if ($aset->status === 'arsip')<span class="arsip">Diarsipkan</span>@endif</div><h2>{{ $aset->nama }}</h2><p class="agenda-muted">{{ $aset->tanggal_aset->format('d-m-Y') }} &middot; Versi {{ $aset->versi + 1 }}</p><p class="agenda-muted">{{ $aset->pemakaian_count }} publikasi</p><div class="agenda-actions"><a class="button button-muted button-sm" href="{{ route('aset-promosi-humas.show', $aset) }}">Lihat aset</a></div></div>
            </article>
        @empty<p class="agenda-muted">Belum ada aset yang sesuai.</p>@endforelse
    </div>{{ $daftar->links() }}
</div>
@endsection
