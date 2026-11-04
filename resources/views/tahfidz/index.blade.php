@extends('layouts.app')
@section('admin-module', 'akademik')
@section('title', 'Tahfidz - NUSA')
@include('tahfidz._assets')

@section('content')
    <div class="tf-page">
        <div class="page-header">
            <div><p class="eyebrow">Akademik / Penilaian</p><h1 class="page-title">Tahfidz</h1></div>
            @if ($bolehKelola && $tahun->isNotEmpty())
                <div class="actions"><a href="{{ route('tahfidz.create') }}" class="button button-primary"><i data-lucide="plus" aria-hidden="true"></i> Tambah kelompok</a></div>
            @endif
        </div>
        @include('tahfidz._nav')
        @include('tahfidz._pesan')

        @if ($tahun->isNotEmpty())
            <form method="GET" action="{{ route('tahfidz.index') }}" class="feature-filter tf-filters">
                <div class="field"><label for="tf-tahun">Tahun pelajaran</label>
                    <select id="tf-tahun" name="tahun" class="select">
                        @foreach ($tahun as $item)
                            <option value="{{ $item->id }}" @selected((int) $tahunId === $item->id)>{{ $item->nama }}{{ $item->aktif ? ' (aktif)' : '' }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="field"><label for="tf-status">Status</label>
                    <select id="tf-status" name="status" class="select">
                        <option value="">Semua status</option>
                        <option value="aktif" @selected($status === 'aktif')>Aktif</option>
                        <option value="nonaktif" @selected($status === 'nonaktif')>Nonaktif</option>
                    </select>
                </div>
                <button type="submit" class="button button-muted"><i data-lucide="filter" aria-hidden="true"></i> Tampilkan</button>
            </form>
        @endif

        <section class="tf-surface" aria-labelledby="tf-daftar">
            <header class="tf-band"><h2 id="tf-daftar">{{ $bolehKelola ? 'Kelompok Tahfidz' : 'Kelompok bimbingan saya' }}</h2><span>{{ $kelompok->total() }} kelompok</span></header>
            @if ($kelompok->isEmpty())
                <p class="empty-state">{{ $tahun->isEmpty() ? 'Belum ada tahun pelajaran.' : 'Belum ada kelompok sesuai pilihan.' }}</p>
            @else
                <ul class="tf-list">
                    @foreach ($kelompok as $item)
                        <li class="tf-group-row">
                            <div><p class="person-name">{{ $item->nama }}</p><p class="person-meta">{{ $item->pembimbing->nama_lengkap }}</p></div>
                            <div class="tf-row-meta"><span>{{ $item->anggota_aktif_count }} siswa</span><span class="badge {{ $item->aktif ? 'badge-active' : 'badge-inactive' }}">{{ $item->aktif ? 'Aktif' : 'Nonaktif' }}</span></div>
                            <a href="{{ route('tahfidz.show', $item) }}" class="button button-muted" aria-label="Lihat {{ $item->nama }}">Lihat <i data-lucide="chevron-right" aria-hidden="true"></i></a>
                        </li>
                    @endforeach
                </ul>
            @endif
        </section>
        @if ($kelompok->hasPages())<div class="tf-pagination">{{ $kelompok->links() }}</div>@endif
    </div>
@endsection
