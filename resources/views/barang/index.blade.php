@extends('layouts.app')

@section('title', 'Inventaris Barang - NUSA')

@section('content')
    <style>
        .goods-filter-grid {
            grid-template-columns: minmax(210px, 1fr) minmax(170px, .75fr) minmax(190px, .85fr) 150px auto;
        }

        @media (max-width: 1120px) {
            .goods-filter-grid {
                grid-template-columns: repeat(2, minmax(0, 1fr));
            }
        }

        @media (max-width: 620px) {
            .goods-filter-grid {
                grid-template-columns: 1fr;
            }
        }
    </style>

    <div class="page-header">
        <div>
            <p class="eyebrow">Sarana Prasarana</p>
            <h1 class="page-title">Inventaris barang</h1>
        </div>

        @izin('barang.kelola')
            <a href="{{ route('barang.create') }}" class="button button-primary">Tambah barang</a>
        @endizin
    </div>

    <div class="stats-grid">
        <div class="panel stat">
            <p class="stat-label">Total jenis barang</p>
            <p class="stat-value">{{ $jumlahBarang }}</p>
        </div>
        <div class="panel stat active">
            <p class="stat-label">Barang aktif</p>
            <p class="stat-value">{{ $jumlahAktif }}</p>
        </div>
        <div class="panel stat inactive">
            <p class="stat-label">Tidak habis pakai</p>
            <p class="stat-value">{{ $jumlahTidakHabisPakai }}</p>
        </div>
        <div class="panel stat">
            <p class="stat-label">Habis pakai</p>
            <p class="stat-value">{{ $jumlahHabisPakai }}</p>
        </div>
    </div>

    @if (session('berhasil'))
        <div class="alert">{{ session('berhasil') }}</div>
    @endif

    <form action="{{ route('barang.index') }}" method="GET" class="panel panel-pad" style="margin-bottom: 24px;">
        <div class="filter-grid goods-filter-grid">
            <div class="field">
                <label for="kata_kunci">Cari barang</label>
                <input id="kata_kunci" name="kata_kunci" type="search" value="{{ $kataKunci }}" placeholder="Nama, kode, atau deskripsi" class="input">
            </div>

            <div class="field">
                <label for="kategori_barang_id">Kategori</label>
                <select id="kategori_barang_id" name="kategori_barang_id" class="select">
                    <option value="semua">Semua kategori</option>
                    @foreach ($daftarKategoriBarang as $kategori)
                        <option value="{{ $kategori->id }}" @selected((string) $kategoriBarangId === (string) $kategori->id)>{{ $kategori->nama }}</option>
                    @endforeach
                </select>
            </div>

            <div class="field">
                <label for="jenis_barang">Jenis barang</label>
                <select id="jenis_barang" name="jenis_barang" class="select">
                    <option value="semua">Semua jenis</option>
                    @foreach ($daftarJenisBarang as $nilaiJenis => $labelJenis)
                        <option value="{{ $nilaiJenis }}" @selected($jenisBarang === $nilaiJenis)>{{ $labelJenis }}</option>
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
                <a href="{{ route('barang.index') }}" class="button button-muted">Reset</a>
            </div>
        </div>
    </form>

    <section class="panel">
        <div class="desktop-only table-wrap">
            <table class="employee-table">
                <thead>
                    <tr>
                        <th>Barang</th>
                        <th>Kategori</th>
                        <th>Jenis barang</th>
                        <th>Lokasi awal</th>
                        <th>Saldo stok</th>
                        <th>Stok minimum</th>
                        <th>Status</th>
                        <th class="text-right">Aksi</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($barang as $item)
                        <tr>
                            <td>
                                <p class="person-name">{{ $item->nama }}</p>
                                <p class="person-meta">{{ $item->kodeKlasifikasi() }}</p>
                            </td>
                            <td>{{ $item->kategoriBarang->nama }}</td>
                            <td>{{ $item->labelJenisBarang() }}</td>
                            <td>{{ $item->lokasiPenyimpanan?->nama ?: '-' }}</td>
                            <td>
                                @if ($item->tipe_pengelolaan === 'aset_individual')
                                    Per unit
                                @else
                                    {{ number_format((float) ($item->saldo_stok_barang_sum_jumlah ?? 0), 2, ',', '.') }} {{ $item->satuanBarang->nama }}
                                @endif
                            </td>
                            <td>{{ number_format((float) $item->stok_minimum, 2, ',', '.') }} {{ $item->satuanBarang->nama }}</td>
                            <td>
                                @if ($item->aktif)
                                    <span class="badge badge-active">Aktif</span>
                                @else
                                    <span class="badge badge-inactive">Nonaktif</span>
                                @endif
                            </td>
                            <td>
                                <div class="actions" style="justify-content: flex-end;">
                                    <a href="{{ route('barang.show', $item) }}" class="button button-muted">Lihat</a>
                                    @izin('barang.kelola')
                                        <a href="{{ route('barang.edit', $item) }}" class="button button-dark">Edit</a>
                                    @endizin
                                </div>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="8" class="empty-state">Belum ada barang inventaris.</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        <div class="mobile-only mobile-list">
            @forelse ($barang as $item)
                <article class="mobile-card">
                    <div class="mobile-card-head">
                        <div>
                            <p class="person-name">{{ $item->nama }}</p>
                            <p class="person-meta">{{ $item->kodeKlasifikasi() }}</p>
                        </div>

                        @if ($item->aktif)
                            <span class="badge badge-active">Aktif</span>
                        @else
                            <span class="badge badge-inactive">Nonaktif</span>
                        @endif
                    </div>

                    <dl class="quick-facts">
                        <div>
                            <dt>Kategori</dt>
                            <dd>{{ $item->kategoriBarang->nama }}</dd>
                        </div>
                        <div>
                            <dt>Satuan</dt>
                            <dd>{{ $item->satuanBarang->nama }}</dd>
                        </div>
                        <div>
                            <dt>Jenis</dt>
                            <dd>{{ $item->labelJenisBarang() }}</dd>
                        </div>
                        <div>
                            <dt>Lokasi awal</dt>
                            <dd>{{ $item->lokasiPenyimpanan?->nama ?: '-' }}</dd>
                        </div>
                        <div>
                            <dt>Saldo stok</dt>
                            <dd>{{ $item->tipe_pengelolaan === 'aset_individual' ? 'Per unit' : number_format((float) ($item->saldo_stok_barang_sum_jumlah ?? 0), 2, ',', '.') . ' ' . $item->satuanBarang->nama }}</dd>
                        </div>
                    </dl>

                    <div class="actions" style="margin-top: 14px;">
                        <a href="{{ route('barang.show', $item) }}" class="button button-muted">Lihat</a>
                        @izin('barang.kelola')
                            <a href="{{ route('barang.edit', $item) }}" class="button button-dark">Edit</a>
                        @endizin
                    </div>
                </article>
            @empty
                <div class="empty-state">Belum ada barang inventaris.</div>
            @endforelse
        </div>
    </section>

    @if ($barang->hasPages())
        <nav class="pagination-simple">
            <div>Halaman {{ $barang->currentPage() }} dari {{ $barang->lastPage() }}</div>
            <div class="actions">
                @if ($barang->onFirstPage())
                    <span class="button button-muted" aria-disabled="true">Sebelumnya</span>
                @else
                    <a href="{{ $barang->previousPageUrl() }}" class="button button-muted">Sebelumnya</a>
                @endif

                @if ($barang->hasMorePages())
                    <a href="{{ $barang->nextPageUrl() }}" class="button button-muted">Berikutnya</a>
                @else
                    <span class="button button-muted" aria-disabled="true">Berikutnya</span>
                @endif
            </div>
        </nav>
    @endif
@endsection
