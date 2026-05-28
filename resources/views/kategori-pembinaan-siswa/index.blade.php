@extends('layouts.app')

@section('title', 'Kategori Pembinaan Siswa - NUSA')

@section('content')
    <div class="page-header">
        <div>
            <p class="eyebrow">Pembinaan</p>
            <h1 class="page-title">Kategori pembinaan siswa</h1>
        </div>

        @izin('bk.kelola')
            <a href="{{ route('kategori-pembinaan-siswa.create') }}" class="button button-primary">Tambah kategori</a>
        @endizin
    </div>

    <div class="stats-grid">
        <div class="panel stat">
            <p class="stat-label">Total</p>
            <p class="stat-value">{{ $jumlahKategori }}</p>
        </div>
        <div class="panel stat active">
            <p class="stat-label">Aktif</p>
            <p class="stat-value">{{ $jumlahAktif }}</p>
        </div>
        <div class="panel stat inactive">
            <p class="stat-label">Nonaktif</p>
            <p class="stat-value">{{ $jumlahNonaktif }}</p>
        </div>
    </div>

    @if (session('berhasil'))
        <div class="alert">{{ session('berhasil') }}</div>
    @endif

    <form action="{{ route('kategori-pembinaan-siswa.index') }}" method="GET" class="panel panel-pad" style="margin-bottom: 24px;">
        <div class="filter-grid filter-grid-wide">
            <div class="field">
                <label for="kata_kunci">Cari kategori</label>
                <input id="kata_kunci" name="kata_kunci" type="search" value="{{ $kataKunci }}" placeholder="Nama, kode, atau deskripsi" class="input">
            </div>

            <div class="field">
                <label for="status">Status</label>
                <select id="status" name="status" class="select">
                    <option value="semua" @selected($status === 'semua')>Semua</option>
                    <option value="aktif" @selected($status === 'aktif')>Aktif</option>
                    <option value="nonaktif" @selected($status === 'nonaktif')>Nonaktif</option>
                </select>
            </div>

            <div class="actions">
                <button type="submit" class="button button-dark">Terapkan</button>
                <a href="{{ route('kategori-pembinaan-siswa.index') }}" class="button button-muted">Reset</a>
            </div>
        </div>
    </form>

    <section class="panel">
        <div class="desktop-only table-wrap">
            <table class="employee-table">
                <thead>
                    <tr>
                        <th>Kategori</th>
                        <th>Deskripsi</th>
                        <th>Status</th>
                        <th class="text-right">Aksi</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($kategoriPembinaanSiswa as $item)
                        <tr>
                            <td>
                                <p class="person-name">{{ $item->nama }}</p>
                                <p class="person-meta">{{ $item->kode }}</p>
                            </td>
                            <td>{{ $item->deskripsi ?: '-' }}</td>
                            <td>
                                @if ($item->aktif)
                                    <span class="badge badge-active">Aktif</span>
                                @else
                                    <span class="badge badge-inactive">Nonaktif</span>
                                @endif
                            </td>
                            <td>
                                <div class="actions" style="justify-content: flex-end;">
                                    <a href="{{ route('kategori-pembinaan-siswa.show', $item) }}" class="button button-muted">Lihat</a>
                                    @izin('bk.kelola')
                                        <a href="{{ route('kategori-pembinaan-siswa.edit', $item) }}" class="button button-dark">Edit</a>
                                    @endizin
                                </div>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="4" class="empty-state">Belum ada kategori pembinaan siswa.</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        <div class="mobile-only mobile-list">
            @forelse ($kategoriPembinaanSiswa as $item)
                <article class="mobile-card">
                    <div class="mobile-card-head">
                        <div>
                            <p class="person-name">{{ $item->nama }}</p>
                            <p class="person-meta">{{ $item->kode }}</p>
                        </div>

                        @if ($item->aktif)
                            <span class="badge badge-active">Aktif</span>
                        @else
                            <span class="badge badge-inactive">Nonaktif</span>
                        @endif
                    </div>

                    <p class="help-text" style="margin-top: 12px;">{{ $item->deskripsi ?: 'Belum ada deskripsi.' }}</p>

                    <div class="actions" style="margin-top: 14px;">
                        <a href="{{ route('kategori-pembinaan-siswa.show', $item) }}" class="button button-muted">Lihat</a>
                        @izin('bk.kelola')
                            <a href="{{ route('kategori-pembinaan-siswa.edit', $item) }}" class="button button-dark">Edit</a>
                        @endizin
                    </div>
                </article>
            @empty
                <div class="empty-state">Belum ada kategori pembinaan siswa.</div>
            @endforelse
        </div>
    </section>

    @if ($kategoriPembinaanSiswa->hasPages())
        <nav class="pagination-simple">
            <div>
                Halaman {{ $kategoriPembinaanSiswa->currentPage() }} dari {{ $kategoriPembinaanSiswa->lastPage() }}
            </div>
            <div class="actions">
                @if ($kategoriPembinaanSiswa->onFirstPage())
                    <span class="button button-muted" aria-disabled="true">Sebelumnya</span>
                @else
                    <a href="{{ $kategoriPembinaanSiswa->previousPageUrl() }}" class="button button-muted">Sebelumnya</a>
                @endif

                @if ($kategoriPembinaanSiswa->hasMorePages())
                    <a href="{{ $kategoriPembinaanSiswa->nextPageUrl() }}" class="button button-muted">Berikutnya</a>
                @else
                    <span class="button button-muted" aria-disabled="true">Berikutnya</span>
                @endif
            </div>
        </nav>
    @endif
@endsection
