@extends('layouts.app')

@section('title', 'Lokasi Barang - NUSA')

@section('content')
    <style>
        .location-filter-grid {
            grid-template-columns: minmax(200px, 1fr) 160px 160px auto;
        }

        @media (max-width: 900px) {
            .location-filter-grid {
                grid-template-columns: repeat(2, minmax(0, 1fr));
            }
        }

        @media (max-width: 620px) {
            .location-filter-grid {
                grid-template-columns: 1fr;
            }
        }
    </style>

    <div class="page-header">
        <div>
            <p class="eyebrow">Sarana Prasarana</p>
            <h1 class="page-title">Lokasi barang</h1>
        </div>

        @izin('barang.kelola')
            <a href="{{ route('lokasi-barang.create') }}" class="button button-primary">Tambah lokasi</a>
        @endizin
    </div>

    <div class="stats-grid">
        <div class="panel stat">
            <p class="stat-label">Total lokasi</p>
            <p class="stat-value">{{ $jumlahLokasi }}</p>
        </div>
        <div class="panel stat active">
            <p class="stat-label">Lokasi aktif</p>
            <p class="stat-value">{{ $jumlahAktif }}</p>
        </div>
        <div class="panel stat inactive">
            <p class="stat-label">Ada penanggung jawab</p>
            <p class="stat-value">{{ $jumlahDenganPenanggungJawab }}</p>
        </div>
    </div>

    @if (session('berhasil'))
        <div class="alert">{{ session('berhasil') }}</div>
    @endif

    <form action="{{ route('lokasi-barang.index') }}" method="GET" class="panel panel-pad" style="margin-bottom: 24px;">
        <div class="filter-grid location-filter-grid">
            <div class="field">
                <label for="kata_kunci">Cari lokasi</label>
                <input id="kata_kunci" name="kata_kunci" type="search" value="{{ $kataKunci }}" placeholder="Nama, kode, atau deskripsi" class="input">
            </div>

            <div class="field">
                <label for="jenis">Jenis lokasi</label>
                <select id="jenis" name="jenis" class="select">
                    <option value="semua">Semua</option>
                    @foreach ($daftarJenis as $nilaiJenis => $labelJenis)
                        <option value="{{ $nilaiJenis }}" @selected($jenis === $nilaiJenis)>{{ $labelJenis }}</option>
                    @endforeach
                </select>
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
                <a href="{{ route('lokasi-barang.index') }}" class="button button-muted">Reset</a>
            </div>
        </div>
    </form>

    <section class="panel">
        <div class="desktop-only table-wrap">
            <table class="employee-table">
                <thead>
                    <tr>
                        <th>Lokasi</th>
                        <th>Jenis</th>
                        <th>Penanggung jawab</th>
                        <th>Jenis barang</th>
                        <th>Status</th>
                        <th class="text-right">Aksi</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($lokasiBarang as $item)
                        <tr>
                            <td>
                                <p class="person-name">{{ $item->nama }}</p>
                                <p class="person-meta">{{ $item->kode }}</p>
                            </td>
                            <td>{{ $item->labelJenis() }}</td>
                            <td>{{ $item->penanggungJawab?->nama_lengkap ?: '-' }}</td>
                            <td>{{ $item->barang_sebagai_penyimpanan_count }}</td>
                            <td>
                                @if ($item->aktif)
                                    <span class="badge badge-active">Aktif</span>
                                @else
                                    <span class="badge badge-inactive">Nonaktif</span>
                                @endif
                            </td>
                            <td>
                                <div class="actions" style="justify-content: flex-end;">
                                    <a href="{{ route('lokasi-barang.show', $item) }}" class="button button-muted">Lihat</a>
                                    @izin('barang.kelola')
                                        <a href="{{ route('lokasi-barang.edit', $item) }}" class="button button-dark">Edit</a>
                                    @endizin
                                </div>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="6" class="empty-state">Belum ada lokasi barang.</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        <div class="mobile-only mobile-list">
            @forelse ($lokasiBarang as $item)
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

                    <dl class="quick-facts">
                        <div>
                            <dt>Jenis</dt>
                            <dd>{{ $item->labelJenis() }}</dd>
                        </div>
                        <div>
                            <dt>Jenis barang</dt>
                            <dd>{{ $item->barang_sebagai_penyimpanan_count }}</dd>
                        </div>
                    </dl>

                    <p class="help-text" style="margin-top: 12px;">Penanggung jawab: {{ $item->penanggungJawab?->nama_lengkap ?: '-' }}</p>

                    <div class="actions" style="margin-top: 14px;">
                        <a href="{{ route('lokasi-barang.show', $item) }}" class="button button-muted">Lihat</a>
                        @izin('barang.kelola')
                            <a href="{{ route('lokasi-barang.edit', $item) }}" class="button button-dark">Edit</a>
                        @endizin
                    </div>
                </article>
            @empty
                <div class="empty-state">Belum ada lokasi barang.</div>
            @endforelse
        </div>
    </section>

    @if ($lokasiBarang->hasPages())
        <nav class="pagination-simple">
            <div>Halaman {{ $lokasiBarang->currentPage() }} dari {{ $lokasiBarang->lastPage() }}</div>
            <div class="actions">
                @if ($lokasiBarang->onFirstPage())
                    <span class="button button-muted" aria-disabled="true">Sebelumnya</span>
                @else
                    <a href="{{ $lokasiBarang->previousPageUrl() }}" class="button button-muted">Sebelumnya</a>
                @endif

                @if ($lokasiBarang->hasMorePages())
                    <a href="{{ $lokasiBarang->nextPageUrl() }}" class="button button-muted">Berikutnya</a>
                @else
                    <span class="button button-muted" aria-disabled="true">Berikutnya</span>
                @endif
            </div>
        </nav>
    @endif
@endsection
