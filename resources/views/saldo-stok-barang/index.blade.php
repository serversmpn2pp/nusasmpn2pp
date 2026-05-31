@extends('layouts.app')

@section('title', 'Saldo Stok Barang - NUSA')

@section('content')
    <style>
        .stock-filter-grid {
            grid-template-columns: minmax(210px, 1fr) minmax(170px, .8fr) minmax(170px, .8fr) 150px auto;
        }

        @media (max-width: 1080px) {
            .stock-filter-grid {
                grid-template-columns: repeat(2, minmax(0, 1fr));
            }
        }

        @media (max-width: 620px) {
            .stock-filter-grid {
                grid-template-columns: 1fr;
            }
        }
    </style>

    <div class="page-header">
        <div>
            <p class="eyebrow">Sarana Prasarana</p>
            <h1 class="page-title">Saldo stok barang</h1>
        </div>

        <div class="actions">
            <a href="{{ route('mutasi-stok-barang.index') }}" class="button button-muted">Riwayat mutasi</a>
            @izin('barang.kelola')
                <a href="{{ route('mutasi-stok-barang.create') }}" class="button button-primary">Catat mutasi</a>
            @endizin
        </div>
    </div>

    <div class="stats-grid">
        <div class="panel stat">
            <p class="stat-label">Baris saldo</p>
            <p class="stat-value">{{ $jumlahBarisSaldo }}</p>
        </div>
        <div class="panel stat active">
            <p class="stat-label">Lokasi terisi stok</p>
            <p class="stat-value">{{ $jumlahLokasiStok }}</p>
        </div>
        <div class="panel stat inactive">
            <p class="stat-label">Stok menipis</p>
            <p class="stat-value">{{ $jumlahSaldoMenipis }}</p>
        </div>
        <div class="panel stat inactive">
            <p class="stat-label">Stok habis</p>
            <p class="stat-value">{{ $jumlahSaldoHabis }}</p>
        </div>
    </div>

    <form action="{{ route('saldo-stok-barang.index') }}" method="GET" class="panel panel-pad" style="margin-bottom: 24px;">
        <div class="filter-grid stock-filter-grid">
            <div class="field">
                <label for="kata_kunci">Cari barang</label>
                <input id="kata_kunci" name="kata_kunci" type="search" value="{{ $kataKunci }}" placeholder="Nama atau kode barang" class="input">
            </div>

            <div class="field">
                <label for="kategori_barang_id">Kategori</label>
                <select id="kategori_barang_id" name="kategori_barang_id" class="select">
                    <option value="semua">Semua kategori</option>
                    @foreach ($daftarKategori as $item)
                        <option value="{{ $item->id }}" @selected((string) $kategoriBarangId === (string) $item->id)>{{ $item->nama }}</option>
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
                <label for="status_stok">Status stok</label>
                <select id="status_stok" name="status_stok" class="select">
                    <option value="semua" @selected($statusStok === 'semua')>Semua</option>
                    <option value="aman" @selected($statusStok === 'aman')>Aman</option>
                    <option value="menipis" @selected($statusStok === 'menipis')>Menipis</option>
                    <option value="habis" @selected($statusStok === 'habis')>Habis</option>
                </select>
            </div>

            <div class="actions">
                <button type="submit" class="button button-dark">Terapkan</button>
                <a href="{{ route('saldo-stok-barang.index') }}" class="button button-muted">Reset</a>
            </div>
        </div>
    </form>

    <section class="panel">
        <div class="desktop-only table-wrap">
            <table class="employee-table">
                <thead>
                    <tr>
                        <th>Barang</th>
                        <th>Lokasi</th>
                        <th>Saldo saat ini</th>
                        <th>Stok minimum</th>
                        <th>Status</th>
                        <th class="text-right">Aksi</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($saldoStokBarang as $item)
                        @php
                            $saldo = (float) $item->jumlah;
                            $minimum = (float) $item->barang->stok_minimum;
                            $statusSaldo = $saldo <= 0 ? 'Habis' : ($saldo <= $minimum ? 'Menipis' : 'Aman');
                            $badgeSaldo = $statusSaldo === 'Aman' ? 'badge-active' : 'badge-inactive';
                        @endphp
                        <tr>
                            <td>
                                <p class="person-name">{{ $item->barang->nama }}</p>
                                <p class="person-meta">{{ $item->barang->kode }}</p>
                            </td>
                            <td>{{ $item->lokasiBarang->nama }}</td>
                            <td><strong>{{ number_format($saldo, 2, ',', '.') }} {{ $item->barang->satuanBarang->nama }}</strong></td>
                            <td>{{ number_format($minimum, 2, ',', '.') }} {{ $item->barang->satuanBarang->nama }}</td>
                            <td><span class="badge {{ $badgeSaldo }}">{{ $statusSaldo }}</span></td>
                            <td>
                                <div class="actions" style="justify-content: flex-end;">
                                    <a href="{{ route('mutasi-stok-barang.index', ['barang_id' => $item->barang_id, 'lokasi_barang_id' => $item->lokasi_barang_id]) }}" class="button button-muted">Riwayat</a>
                                    @izin('barang.kelola')
                                        <a href="{{ route('mutasi-stok-barang.create', ['barang_id' => $item->barang_id]) }}" class="button button-dark">Catat</a>
                                    @endizin
                                </div>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="6" class="empty-state">Belum ada saldo stok. Catat stok awal atau stok masuk pertama.</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        <div class="mobile-only mobile-list">
            @forelse ($saldoStokBarang as $item)
                @php
                    $saldo = (float) $item->jumlah;
                    $minimum = (float) $item->barang->stok_minimum;
                    $statusSaldo = $saldo <= 0 ? 'Habis' : ($saldo <= $minimum ? 'Menipis' : 'Aman');
                    $badgeSaldo = $statusSaldo === 'Aman' ? 'badge-active' : 'badge-inactive';
                @endphp
                <article class="mobile-card">
                    <div class="mobile-card-head">
                        <div>
                            <p class="person-name">{{ $item->barang->nama }}</p>
                            <p class="person-meta">{{ $item->barang->kode }} - {{ $item->lokasiBarang->nama }}</p>
                        </div>
                        <span class="badge {{ $badgeSaldo }}">{{ $statusSaldo }}</span>
                    </div>

                    <dl class="quick-facts">
                        <div>
                            <dt>Saldo</dt>
                            <dd>{{ number_format($saldo, 2, ',', '.') }} {{ $item->barang->satuanBarang->nama }}</dd>
                        </div>
                        <div>
                            <dt>Minimum</dt>
                            <dd>{{ number_format($minimum, 2, ',', '.') }} {{ $item->barang->satuanBarang->nama }}</dd>
                        </div>
                    </dl>

                    <div class="actions" style="margin-top: 14px;">
                        <a href="{{ route('mutasi-stok-barang.index', ['barang_id' => $item->barang_id, 'lokasi_barang_id' => $item->lokasi_barang_id]) }}" class="button button-muted">Riwayat</a>
                        @izin('barang.kelola')
                            <a href="{{ route('mutasi-stok-barang.create', ['barang_id' => $item->barang_id]) }}" class="button button-dark">Catat</a>
                        @endizin
                    </div>
                </article>
            @empty
                <div class="empty-state">Belum ada saldo stok. Catat stok awal atau stok masuk pertama.</div>
            @endforelse
        </div>
    </section>

    @if ($saldoStokBarang->hasPages())
        <nav class="pagination-simple">
            <div>Halaman {{ $saldoStokBarang->currentPage() }} dari {{ $saldoStokBarang->lastPage() }}</div>
            <div class="actions">
                @if ($saldoStokBarang->onFirstPage())
                    <span class="button button-muted" aria-disabled="true">Sebelumnya</span>
                @else
                    <a href="{{ $saldoStokBarang->previousPageUrl() }}" class="button button-muted">Sebelumnya</a>
                @endif

                @if ($saldoStokBarang->hasMorePages())
                    <a href="{{ $saldoStokBarang->nextPageUrl() }}" class="button button-muted">Berikutnya</a>
                @else
                    <span class="button button-muted" aria-disabled="true">Berikutnya</span>
                @endif
            </div>
        </nav>
    @endif
@endsection
