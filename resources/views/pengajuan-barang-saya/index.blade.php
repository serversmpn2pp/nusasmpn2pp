@extends('layouts.app')

@section('title', 'Pengajuan Barang Saya - NUSA')

@section('content')
    <style>
        .request-stats { grid-template-columns: repeat(4, minmax(0, 1fr)); }
        .request-filter { display: grid; grid-template-columns: minmax(210px, 320px) auto; gap: 12px; align-items: end; }
        @media (max-width: 700px) {
            .request-stats { grid-template-columns: repeat(2, minmax(0, 1fr)); }
            .request-filter { grid-template-columns: 1fr; }
        }
    </style>

    <div class="page-header">
        <div>
            <p class="eyebrow">Layanan Sarana Prasarana</p>
            <h1 class="page-title">Pengajuan barang saya</h1>
            <p class="help-text" style="margin-top: 6px;">Pantau peminjaman aset dan permintaan barang habis pakai Anda.</p>
        </div>
        <a href="{{ route('katalog-barang.index') }}" class="button button-primary">Buka katalog barang</a>
    </div>

    <section class="stats-grid request-stats">
        <article class="panel stat"><p class="stat-label">Semua pengajuan</p><p class="stat-value">{{ $ringkasan['semua'] }}</p></article>
        <article class="panel stat inactive"><p class="stat-label">Menunggu petugas</p><p class="stat-value">{{ $ringkasan['menunggu'] }}</p></article>
        <article class="panel stat active"><p class="stat-label">Dipenuhi</p><p class="stat-value">{{ $ringkasan['dipenuhi'] }}</p></article>
        <article class="panel stat"><p class="stat-label">Ditolak/dibatalkan</p><p class="stat-value">{{ $ringkasan['selesai'] }}</p></article>
    </section>

    @if (session('berhasil'))
        <div class="alert">{{ session('berhasil') }}</div>
    @endif

    <form action="{{ route('pengajuan-barang-saya.index') }}" method="GET" class="panel panel-pad" style="margin-bottom: 24px;">
        <div class="request-filter">
            <div class="field">
                <label for="status">Status pengajuan</label>
                <select id="status" name="status" class="select">
                    <option value="semua">Semua status</option>
                    @foreach ($daftarStatus as $nilai => $label)
                        <option value="{{ $nilai }}" @selected($status === $nilai)>{{ $label }}</option>
                    @endforeach
                </select>
            </div>
            <div class="actions">
                <button type="submit" class="button button-dark">Tampilkan</button>
                @if ($status !== 'semua')
                    <a href="{{ route('pengajuan-barang-saya.index') }}" class="button button-muted">Reset</a>
                @endif
            </div>
        </div>
    </form>

    <section class="panel">
        <div class="desktop-only table-wrap">
            <table class="employee-table">
                <thead><tr><th>Nomor</th><th>Barang</th><th>Dibutuhkan</th><th>Jumlah</th><th>Status</th><th class="text-right">Aksi</th></tr></thead>
                <tbody>
                    @forelse ($pengajuanBarang as $item)
                        <tr>
                            <td><strong>{{ $item->nomor_pengajuan }}</strong><p class="person-meta">{{ $item->tanggal_pengajuan->locale('id')->translatedFormat('d M Y') }}</p></td>
                            <td><p class="person-name">{{ $item->barang->nama }}</p><p class="person-meta">{{ $item->labelJenis() }}</p></td>
                            <td>{{ $item->tanggal_dibutuhkan->locale('id')->translatedFormat('d M Y') }}</td>
                            <td>{{ number_format((float) $item->jumlah, 2, ',', '.') }} {{ $item->barang->satuanBarang->nama }}</td>
                            <td><span class="badge {{ $item->status === 'dipenuhi' ? 'badge-active' : ($item->status === 'menunggu' ? 'badge-warning' : 'badge-muted') }}">{{ $item->labelStatus() }}</span></td>
                            <td><div class="actions" style="justify-content: flex-end;"><a href="{{ route('pengajuan-barang-saya.show', $item) }}" class="button button-muted">Lihat</a></div></td>
                        </tr>
                    @empty
                        <tr><td colspan="6" class="empty-state">Belum ada pengajuan barang.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        <div class="mobile-only mobile-list">
            @forelse ($pengajuanBarang as $item)
                <article class="mobile-card">
                    <div class="mobile-card-head">
                        <div><p class="person-name">{{ $item->barang->nama }}</p><p class="person-meta">{{ $item->nomor_pengajuan }}</p></div>
                        <span class="badge {{ $item->status === 'dipenuhi' ? 'badge-active' : ($item->status === 'menunggu' ? 'badge-warning' : 'badge-muted') }}">{{ $item->labelStatus() }}</span>
                    </div>
                    <dl class="quick-facts">
                        <div><dt>Dibutuhkan</dt><dd>{{ $item->tanggal_dibutuhkan->locale('id')->translatedFormat('d M Y') }}</dd></div>
                        <div><dt>Jumlah</dt><dd>{{ number_format((float) $item->jumlah, 2, ',', '.') }} {{ $item->barang->satuanBarang->nama }}</dd></div>
                    </dl>
                    <div class="actions" style="margin-top: 14px;"><a href="{{ route('pengajuan-barang-saya.show', $item) }}" class="button button-muted">Lihat detail</a></div>
                </article>
            @empty
                <div class="empty-state">Belum ada pengajuan barang.</div>
            @endforelse
        </div>
    </section>

    @if ($pengajuanBarang->hasPages())
        <nav class="pagination-simple">
            <div>Halaman {{ $pengajuanBarang->currentPage() }} dari {{ $pengajuanBarang->lastPage() }}</div>
            <div class="actions">
                @if ($pengajuanBarang->onFirstPage())<span class="button button-muted" aria-disabled="true">Sebelumnya</span>@else<a href="{{ $pengajuanBarang->previousPageUrl() }}" class="button button-muted">Sebelumnya</a>@endif
                @if ($pengajuanBarang->hasMorePages())<a href="{{ $pengajuanBarang->nextPageUrl() }}" class="button button-muted">Berikutnya</a>@else<span class="button button-muted" aria-disabled="true">Berikutnya</span>@endif
            </div>
        </nav>
    @endif
@endsection
