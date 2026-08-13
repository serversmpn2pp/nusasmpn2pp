@extends('layouts.app')

@section('title', 'Kegiatan Ibadah - NUSA')

@section('content')
    <div class="page-header">
        <div>
            <p class="eyebrow">Kehadiran Siswa</p>
            <h1 class="page-title">Kegiatan Ibadah</h1>
            <p class="page-subtitle">Kelola kegiatan keagamaan yang menggunakan presensi kartu pelajar.</p>
        </div>
        <div class="actions">
            <a href="{{ route('jadwal-kegiatan-ibadah.index') }}" class="button button-muted">Jadwal ibadah</a>
            <a href="{{ route('kegiatan-ibadah.create') }}" class="button button-primary">Tambah kegiatan</a>
        </div>
    </div>

    @if (session('berhasil'))<div class="alert">{{ session('berhasil') }}</div>@endif

    <div class="stats-grid">
        <div class="panel stat"><p class="stat-label">Semua kegiatan</p><p class="stat-value">{{ $jumlahKegiatan }}</p></div>
        <div class="panel stat active"><p class="stat-label">Aktif</p><p class="stat-value">{{ $jumlahAktif }}</p></div>
        <div class="panel stat inactive"><p class="stat-label">Nonaktif</p><p class="stat-value">{{ $jumlahNonaktif }}</p></div>
    </div>

    <form action="{{ route('kegiatan-ibadah.index') }}" method="GET" class="panel panel-pad" style="margin-bottom:18px;">
        <div class="filter-grid">
            <div class="field"><label for="cari">Cari kegiatan</label><input id="cari" name="cari" class="input" value="{{ $cari }}" placeholder="Nama atau kode kegiatan"></div>
            <div class="field"><label for="status">Status</label><select id="status" name="status" class="select" onchange="this.form.submit()"><option value="semua" @selected($status === 'semua')>Semua status</option><option value="aktif" @selected($status === 'aktif')>Aktif</option><option value="nonaktif" @selected($status === 'nonaktif')>Nonaktif</option></select></div>
            <div class="actions"><button type="submit" class="button button-primary">Cari</button><a href="{{ route('kegiatan-ibadah.index') }}" class="button button-muted">Reset</a></div>
        </div>
    </form>

    <section class="panel">
        <div class="desktop-only table-wrap"><table class="employee-table"><thead><tr><th>Kegiatan</th><th>Kode</th><th>Jadwal</th><th>Status</th><th class="text-right">Aksi</th></tr></thead><tbody>
            @forelse ($kegiatanIbadah as $item)
                <tr><td><p class="person-name">{{ $item->nama }}</p><p class="person-meta">{{ $item->keterangan ?: 'Kegiatan ibadah siswa' }}</p></td><td><span class="badge badge-muted">{{ $item->kode }}</span></td><td>{{ $item->jumlah_jadwal_aktif }} aktif</td><td><span class="badge {{ $item->aktif ? 'badge-active' : 'badge-inactive' }}">{{ $item->aktif ? 'Aktif' : 'Nonaktif' }}</span></td><td><div class="actions" style="justify-content:flex-end;"><a href="{{ route('kegiatan-ibadah.show', $item) }}" class="button button-muted button-sm">Lihat</a><a href="{{ route('kegiatan-ibadah.edit', $item) }}" class="button button-dark button-sm">Edit</a></div></td></tr>
            @empty
                <tr><td colspan="5" class="empty-state">Belum ada kegiatan ibadah sesuai pilihan.</td></tr>
            @endforelse
        </tbody></table></div>
        <div class="mobile-only mobile-list">
            @forelse ($kegiatanIbadah as $item)
                <article class="mobile-card"><div class="mobile-card-head"><div><p class="person-name">{{ $item->nama }}</p><p class="person-meta">{{ $item->kode }} &middot; {{ $item->jumlah_jadwal_aktif }} jadwal aktif</p></div><span class="badge {{ $item->aktif ? 'badge-active' : 'badge-inactive' }}">{{ $item->aktif ? 'Aktif' : 'Nonaktif' }}</span></div><p class="help-text" style="margin-top:10px;">{{ $item->keterangan ?: 'Kegiatan ibadah siswa' }}</p><div class="actions" style="margin-top:13px;"><a href="{{ route('kegiatan-ibadah.show', $item) }}" class="button button-muted">Lihat</a><a href="{{ route('kegiatan-ibadah.edit', $item) }}" class="button button-dark">Edit</a></div></article>
            @empty
                <div class="empty-state">Belum ada kegiatan ibadah sesuai pilihan.</div>
            @endforelse
        </div>
    </section>

    @if($kegiatanIbadah->hasPages())<div style="margin-top:16px;">{{ $kegiatanIbadah->links() }}</div>@endif
@endsection
