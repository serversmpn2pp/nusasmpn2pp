@extends('layouts.app')

@section('title', 'Unit Aset - NUSA')

@section('content')
    <style>
        .asset-unit-filter-grid {
            grid-template-columns: minmax(190px, 1fr) minmax(170px, .8fr) minmax(150px, .7fr) minmax(150px, .7fr) 140px auto;
        }

        @media (max-width: 1180px) {
            .asset-unit-filter-grid {
                grid-template-columns: repeat(2, minmax(0, 1fr));
            }
        }

        @media (max-width: 620px) {
            .asset-unit-filter-grid {
                grid-template-columns: 1fr;
            }
        }
    </style>

    <div class="page-header">
        <div>
            <p class="eyebrow">Sarana Prasarana</p>
            <h1 class="page-title">Unit aset individual</h1>
        </div>

        <div class="actions">
            <a href="{{ route('label-barcode-inventaris.index') }}" class="button button-muted">Cetak label barcode</a>
            @izin('barang.kelola')
                <a href="{{ route('unit-barang.create') }}" class="button button-primary">Tambah unit aset</a>
            @endizin
        </div>
    </div>

    <div class="stats-grid">
        <div class="panel stat">
            <p class="stat-label">Total unit</p>
            <p class="stat-value">{{ $jumlahUnit }}</p>
        </div>
        <div class="panel stat active">
            <p class="stat-label">Unit aktif</p>
            <p class="stat-value">{{ $jumlahAktif }}</p>
        </div>
        <div class="panel stat">
            <p class="stat-label">Tersedia</p>
            <p class="stat-value">{{ $jumlahTersedia }}</p>
        </div>
        <div class="panel stat inactive">
            <p class="stat-label">Perlu perhatian</p>
            <p class="stat-value">{{ $jumlahPerluPerhatian }}</p>
        </div>
    </div>

    @if (session('berhasil'))
        <div class="alert">{{ session('berhasil') }}</div>
    @endif

    <form action="{{ route('unit-barang.index') }}" method="GET" class="panel panel-pad" style="margin-bottom: 24px;">
        <div class="filter-grid asset-unit-filter-grid">
            <div class="field">
                <label for="kata_kunci">Cari unit aset</label>
                <input id="kata_kunci" name="kata_kunci" type="search" value="{{ $kataKunci }}" placeholder="Kode, barang, atau nomor seri" class="input">
            </div>

            <div class="field">
                <label for="barang_id">Barang</label>
                <select id="barang_id" name="barang_id" class="select">
                    <option value="semua">Semua barang</option>
                    @foreach ($daftarBarang as $item)
                        <option value="{{ $item->id }}" @selected((string) $barangId === (string) $item->id)>{{ $item->nama }}</option>
                    @endforeach
                </select>
            </div>

            <div class="field">
                <label for="lokasi_barang_id">Lokasi</label>
                <select id="lokasi_barang_id" name="lokasi_barang_id" class="select">
                    <option value="semua">Semua lokasi</option>
                    @foreach ($daftarLokasi as $item)
                        <option value="{{ $item->id }}" @selected((string) $lokasiBarangId === (string) $item->id)>{{ $item->nama }}</option>
                    @endforeach
                </select>
            </div>

            <div class="field">
                <label for="kondisi">Kondisi</label>
                <select id="kondisi" name="kondisi" class="select">
                    <option value="semua">Semua kondisi</option>
                    @foreach ($daftarKondisi as $nilaiKondisi => $labelKondisi)
                        <option value="{{ $nilaiKondisi }}" @selected($kondisi === $nilaiKondisi)>{{ $labelKondisi }}</option>
                    @endforeach
                </select>
            </div>

            <div class="field">
                <label for="status_unit">Status unit</label>
                <select id="status_unit" name="status_unit" class="select">
                    <option value="semua">Semua status</option>
                    @foreach ($daftarStatusUnit as $nilaiStatus => $labelStatus)
                        <option value="{{ $nilaiStatus }}" @selected($statusUnit === $nilaiStatus)>{{ $labelStatus }}</option>
                    @endforeach
                </select>
            </div>

            <div class="field">
                <label for="status">Data</label>
                <select id="status" name="status" class="select">
                    <option value="semua" @selected($status === 'semua')>Semua</option>
                    <option value="aktif" @selected($status === 'aktif')>Aktif</option>
                    <option value="nonaktif" @selected($status === 'nonaktif')>Nonaktif</option>
                </select>
            </div>

            <div class="actions">
                <button type="submit" class="button button-dark">Terapkan</button>
                <a href="{{ route('unit-barang.index') }}" class="button button-muted">Reset</a>
            </div>
        </div>
    </form>

    <section class="panel">
        <div class="desktop-only table-wrap">
            <table class="employee-table">
                <thead>
                    <tr>
                        <th>Kode unit barang</th>
                        <th>Barang</th>
                        <th>Lokasi</th>
                        <th>Kondisi</th>
                        <th>Status unit</th>
                        <th>Nomor seri</th>
                        <th class="text-right">Aksi</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($unitBarang as $item)
                        <tr>
                            <td>
                                <p class="person-name">{{ $item->kodeBarangUnit() }}</p>
                                <p class="person-meta">ID NUSA {{ $item->kode_inventaris }}</p>
                            </td>
                            <td>{{ $item->barang->nama }}</td>
                            <td>{{ $item->lokasiBarang?->nama ?: '-' }}</td>
                            <td>{{ $item->labelKondisi() }}</td>
                            <td>
                                <span class="badge {{ $item->status_unit === 'tersedia' ? 'badge-active' : 'badge-inactive' }}">{{ $item->labelStatus() }}</span>
                            </td>
                            <td>{{ $item->nomor_seri ?: '-' }}</td>
                            <td>
                                <div class="actions" style="justify-content: flex-end;">
                                    <a href="{{ route('label-barcode-inventaris.index', ['unit_barang_id' => [$item->id]]) }}" class="button button-muted">Label</a>
                                    <a href="{{ route('unit-barang.show', $item) }}" class="button button-muted">Lihat</a>
                                    @izin('barang.kelola')
                                        <a href="{{ route('unit-barang.edit', $item) }}" class="button button-dark">Edit</a>
                                    @endizin
                                </div>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="7" class="empty-state">Belum ada unit aset individual.</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        <div class="mobile-only mobile-list">
            @forelse ($unitBarang as $item)
                <article class="mobile-card">
                    <div class="mobile-card-head">
                        <div>
                            <p class="person-name">{{ $item->barang->nama }}</p>
                            <p class="person-meta">{{ $item->kodeBarangUnit() }}</p>
                            <p class="person-meta">ID NUSA {{ $item->kode_inventaris }}</p>
                        </div>
                        <span class="badge {{ $item->status_unit === 'tersedia' ? 'badge-active' : 'badge-inactive' }}">{{ $item->labelStatus() }}</span>
                    </div>

                    <dl class="quick-facts">
                        <div>
                            <dt>Lokasi</dt>
                            <dd>{{ $item->lokasiBarang?->nama ?: '-' }}</dd>
                        </div>
                        <div>
                            <dt>Kondisi</dt>
                            <dd>{{ $item->labelKondisi() }}</dd>
                        </div>
                        <div>
                            <dt>Nomor seri</dt>
                            <dd>{{ $item->nomor_seri ?: '-' }}</dd>
                        </div>
                    </dl>

                    <div class="actions" style="margin-top: 14px;">
                        <a href="{{ route('label-barcode-inventaris.index', ['unit_barang_id' => [$item->id]]) }}" class="button button-muted">Label</a>
                        <a href="{{ route('unit-barang.show', $item) }}" class="button button-muted">Lihat</a>
                        @izin('barang.kelola')
                            <a href="{{ route('unit-barang.edit', $item) }}" class="button button-dark">Edit</a>
                        @endizin
                    </div>
                </article>
            @empty
                <div class="empty-state">Belum ada unit aset individual.</div>
            @endforelse
        </div>
    </section>

    @if ($unitBarang->hasPages())
        <nav class="pagination-simple">
            <div>Halaman {{ $unitBarang->currentPage() }} dari {{ $unitBarang->lastPage() }}</div>
            <div class="actions">
                @if ($unitBarang->onFirstPage())
                    <span class="button button-muted" aria-disabled="true">Sebelumnya</span>
                @else
                    <a href="{{ $unitBarang->previousPageUrl() }}" class="button button-muted">Sebelumnya</a>
                @endif

                @if ($unitBarang->hasMorePages())
                    <a href="{{ $unitBarang->nextPageUrl() }}" class="button button-muted">Berikutnya</a>
                @else
                    <span class="button button-muted" aria-disabled="true">Berikutnya</span>
                @endif
            </div>
        </nav>
    @endif
@endsection
