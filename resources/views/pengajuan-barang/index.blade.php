@extends('layouts.app')

@section('title', 'Pengajuan Barang - NUSA')

@section('content')
    <style>
        .submission-stats { grid-template-columns: repeat(4, minmax(0, 1fr)); }
        .submission-filter { display: grid; grid-template-columns: minmax(230px, 1.2fr) repeat(2, minmax(170px, .7fr)) auto; gap: 12px; align-items: end; }
        @media (max-width: 900px) { .submission-filter { grid-template-columns: repeat(2, minmax(0, 1fr)); } .submission-filter .actions { grid-column: 1 / -1; } }
        @media (max-width: 650px) { .submission-stats, .submission-filter { grid-template-columns: 1fr; } .submission-filter .actions { grid-column: auto; } }
    </style>

    <div class="page-header">
        <div>
            <p class="eyebrow">Sarana Prasarana</p>
            <h1 class="page-title">Pengajuan barang</h1>
            <p class="help-text" style="margin-top: 6px;">Periksa pengajuan pegawai, lalu pilih unit atau lokasi stok saat barang diserahkan.</p>
        </div>
        <a href="{{ route('peminjaman-barang.index') }}" class="button button-muted">Riwayat transaksi</a>
    </div>

    <section class="stats-grid submission-stats">
        <article class="panel stat"><p class="stat-label">Semua pengajuan</p><p class="stat-value">{{ $ringkasan['semua'] }}</p></article>
        <article class="panel stat inactive"><p class="stat-label">Menunggu diproses</p><p class="stat-value">{{ $ringkasan['menunggu'] }}</p></article>
        <article class="panel stat"><p class="stat-label">Peminjaman aset</p><p class="stat-value">{{ $ringkasan['peminjaman'] }}</p></article>
        <article class="panel stat active"><p class="stat-label">Permintaan habis pakai</p><p class="stat-value">{{ $ringkasan['permintaan'] }}</p></article>
    </section>

    @if (session('berhasil'))<div class="alert">{{ session('berhasil') }}</div>@endif

    <form action="{{ route('pengajuan-barang.index') }}" method="GET" class="panel panel-pad" style="margin-bottom: 24px;">
        <div class="submission-filter">
            <div class="field"><label for="kata_kunci">Cari pengajuan</label><input id="kata_kunci" name="kata_kunci" type="search" value="{{ $kataKunci }}" class="input" placeholder="Nomor, pegawai, atau barang"></div>
            <div class="field"><label for="jenis">Jenis</label><select id="jenis" name="jenis" class="select"><option value="semua">Semua jenis</option>@foreach ($daftarJenis as $nilai => $label)<option value="{{ $nilai }}" @selected($jenis === $nilai)>{{ $label }}</option>@endforeach</select></div>
            <div class="field"><label for="status">Status</label><select id="status" name="status" class="select"><option value="semua">Semua status</option>@foreach ($daftarStatus as $nilai => $label)<option value="{{ $nilai }}" @selected($status === $nilai)>{{ $label }}</option>@endforeach</select></div>
            <div class="actions"><button type="submit" class="button button-dark">Tampilkan</button><a href="{{ route('pengajuan-barang.index') }}" class="button button-muted">Reset</a></div>
        </div>
    </form>

    <section class="panel">
        <div class="desktop-only table-wrap">
            <table class="employee-table">
                <thead><tr><th>Pengajuan</th><th>Pegawai</th><th>Barang</th><th>Dibutuhkan</th><th>Status</th><th class="text-right">Aksi</th></tr></thead>
                <tbody>
                    @forelse ($pengajuanBarang as $item)
                        <tr>
                            <td><strong>{{ $item->nomor_pengajuan }}</strong><p class="person-meta">{{ $item->labelJenis() }}</p></td>
                            <td><p class="person-name">{{ $item->pegawai->nama_lengkap }}</p><p class="person-meta">{{ $item->pegawai->jenis_pegawai ?: 'Pegawai' }}</p></td>
                            <td><p class="person-name">{{ $item->barang->nama }}</p><p class="person-meta">{{ number_format((float) $item->jumlah, 2, ',', '.') }} {{ $item->barang->satuanBarang->nama }}</p></td>
                            <td>{{ $item->tanggal_dibutuhkan->locale('id')->translatedFormat('d M Y') }}</td>
                            <td><span class="badge {{ $item->status === 'dipenuhi' ? 'badge-active' : ($item->status === 'menunggu' ? 'badge-warning' : 'badge-muted') }}">{{ $item->labelStatus() }}</span></td>
                            <td><div class="actions" style="justify-content: flex-end;"><a href="{{ route('pengajuan-barang.show', $item) }}" class="button {{ $item->masihMenunggu() ? 'button-primary' : 'button-muted' }}">{{ $item->masihMenunggu() ? 'Periksa' : 'Lihat' }}</a></div></td>
                        </tr>
                    @empty
                        <tr><td colspan="6" class="empty-state">Tidak ada pengajuan pada pilihan ini.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        <div class="mobile-only mobile-list">
            @forelse ($pengajuanBarang as $item)
                <article class="mobile-card">
                    <div class="mobile-card-head"><div><p class="person-name">{{ $item->pegawai->nama_lengkap }}</p><p class="person-meta">{{ $item->nomor_pengajuan }}</p></div><span class="badge {{ $item->status === 'dipenuhi' ? 'badge-active' : ($item->status === 'menunggu' ? 'badge-warning' : 'badge-muted') }}">{{ $item->labelStatus() }}</span></div>
                    <dl class="quick-facts"><div><dt>Barang</dt><dd>{{ $item->barang->nama }}</dd></div><div><dt>Jumlah</dt><dd>{{ number_format((float) $item->jumlah, 2, ',', '.') }} {{ $item->barang->satuanBarang->nama }}</dd></div><div><dt>Dibutuhkan</dt><dd>{{ $item->tanggal_dibutuhkan->locale('id')->translatedFormat('d M Y') }}</dd></div></dl>
                    <div class="actions" style="margin-top: 14px;"><a href="{{ route('pengajuan-barang.show', $item) }}" class="button {{ $item->masihMenunggu() ? 'button-primary' : 'button-muted' }}">{{ $item->masihMenunggu() ? 'Periksa pengajuan' : 'Lihat detail' }}</a></div>
                </article>
            @empty
                <div class="empty-state">Tidak ada pengajuan pada pilihan ini.</div>
            @endforelse
        </div>
    </section>

    @if ($pengajuanBarang->hasPages())
        <nav class="pagination-simple"><div>Halaman {{ $pengajuanBarang->currentPage() }} dari {{ $pengajuanBarang->lastPage() }}</div><div class="actions">@if ($pengajuanBarang->onFirstPage())<span class="button button-muted" aria-disabled="true">Sebelumnya</span>@else<a href="{{ $pengajuanBarang->previousPageUrl() }}" class="button button-muted">Sebelumnya</a>@endif @if ($pengajuanBarang->hasMorePages())<a href="{{ $pengajuanBarang->nextPageUrl() }}" class="button button-muted">Berikutnya</a>@else<span class="button button-muted" aria-disabled="true">Berikutnya</span>@endif</div></nav>
    @endif
@endsection
