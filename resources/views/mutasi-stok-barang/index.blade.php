@extends('layouts.app')

@section('title', 'Mutasi Stok Barang - NUSA')

@section('content')
    <style>
        .stock-movement-filter-grid {
            grid-template-columns: minmax(190px, 1fr) minmax(160px, .8fr) minmax(150px, .75fr) 140px 145px 145px auto;
        }

        @media (max-width: 1220px) {
            .stock-movement-filter-grid {
                grid-template-columns: repeat(2, minmax(0, 1fr));
            }
        }

        @media (max-width: 620px) {
            .stock-movement-filter-grid {
                grid-template-columns: 1fr;
            }
        }
    </style>

    <div class="page-header">
        <div>
            <p class="eyebrow">Sarana Prasarana</p>
            <h1 class="page-title">Mutasi stok barang</h1>
        </div>

        <div class="actions">
            <a href="{{ route('saldo-stok-barang.index') }}" class="button button-muted">Saldo stok</a>
            @izin('barang.kelola')
                <a href="{{ route('mutasi-stok-barang.create') }}" class="button button-primary">Catat mutasi</a>
            @endizin
        </div>
    </div>

    <div class="stats-grid">
        <div class="panel stat">
            <p class="stat-label">Total mutasi</p>
            <p class="stat-value">{{ $jumlahMutasi }}</p>
        </div>
        <div class="panel stat active">
            <p class="stat-label">Transaksi hari ini</p>
            <p class="stat-value">{{ $jumlahMutasiHariIni }}</p>
        </div>
        <div class="panel stat active">
            <p class="stat-label">Masuk hari ini</p>
            <p class="stat-value">{{ number_format($jumlahMasukHariIni, 2, ',', '.') }}</p>
        </div>
        <div class="panel stat inactive">
            <p class="stat-label">Keluar hari ini</p>
            <p class="stat-value">{{ number_format($jumlahKeluarHariIni, 2, ',', '.') }}</p>
        </div>
    </div>

    @if (session('berhasil'))
        <div class="alert">{{ session('berhasil') }}</div>
    @endif

    <form action="{{ route('mutasi-stok-barang.index') }}" method="GET" class="panel panel-pad" style="margin-bottom: 24px;">
        <div class="filter-grid stock-movement-filter-grid">
            <div class="field">
                <label for="kata_kunci">Cari mutasi</label>
                <input id="kata_kunci" name="kata_kunci" type="search" value="{{ $kataKunci }}" placeholder="Barang, referensi, keterangan" class="input">
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
                <label for="jenis_mutasi">Jenis</label>
                <select id="jenis_mutasi" name="jenis_mutasi" class="select">
                    <option value="semua">Semua jenis</option>
                    @foreach ($daftarJenis as $nilaiJenis => $labelJenis)
                        <option value="{{ $nilaiJenis }}" @selected($jenisMutasi === $nilaiJenis)>{{ $labelJenis }}</option>
                    @endforeach
                </select>
            </div>

            <div class="field">
                <label for="tanggal_mulai">Dari tanggal</label>
                <input id="tanggal_mulai" name="tanggal_mulai" type="date" value="{{ $tanggalMulai }}" class="input">
            </div>

            <div class="field">
                <label for="tanggal_selesai">Sampai tanggal</label>
                <input id="tanggal_selesai" name="tanggal_selesai" type="date" value="{{ $tanggalSelesai }}" class="input">
            </div>

            <div class="actions">
                <button type="submit" class="button button-dark">Terapkan</button>
                <a href="{{ route('mutasi-stok-barang.index') }}" class="button button-muted">Reset</a>
            </div>
        </div>
    </form>

    <section class="panel">
        <div class="desktop-only table-wrap">
            <table class="employee-table">
                <thead>
                    <tr>
                        <th>Tanggal</th>
                        <th>Barang</th>
                        <th>Lokasi</th>
                        <th>Jenis</th>
                        <th>Perubahan</th>
                        <th>Saldo akhir</th>
                        <th>Referensi</th>
                        <th class="text-right">Aksi</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($mutasiStokBarang as $item)
                        @php
                            $perubahan = (float) $item->jumlah_perubahan;
                        @endphp
                        <tr>
                            <td>{{ $item->tanggal_mutasi->locale('id')->translatedFormat('d M Y') }}</td>
                            <td>
                                <p class="person-name">{{ $item->barang->nama }}</p>
                                <p class="person-meta">{{ $item->barang->kode }}</p>
                            </td>
                            <td>{{ $item->lokasiBarang->nama }}</td>
                            <td>
                                <span class="badge {{ $perubahan > 0 ? 'badge-active' : 'badge-inactive' }}">{{ $item->labelJenis() }}</span>
                                <p class="person-meta" style="margin-top: 4px;">{{ $item->labelKategori() }}</p>
                            </td>
                            <td><strong>{{ $perubahan > 0 ? '+' : '' }}{{ number_format($perubahan, 2, ',', '.') }} {{ $item->barang->satuanBarang->nama }}</strong></td>
                            <td>{{ number_format((float) $item->saldo_sesudah, 2, ',', '.') }} {{ $item->barang->satuanBarang->nama }}</td>
                            <td>{{ $item->referensi ?: '-' }}</td>
                            <td>
                                <div class="actions" style="justify-content: flex-end;">
                                    <a href="{{ route('mutasi-stok-barang.show', $item) }}" class="button button-muted">Lihat</a>
                                </div>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="8" class="empty-state">Belum ada mutasi stok barang.</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        <div class="mobile-only mobile-list">
            @forelse ($mutasiStokBarang as $item)
                @php
                    $perubahan = (float) $item->jumlah_perubahan;
                @endphp
                <article class="mobile-card">
                    <div class="mobile-card-head">
                        <div>
                            <p class="person-name">{{ $item->barang->nama }}</p>
                            <p class="person-meta">{{ $item->tanggal_mutasi->locale('id')->translatedFormat('d M Y') }} - {{ $item->lokasiBarang->nama }}</p>
                        </div>
                        <span class="badge {{ $perubahan > 0 ? 'badge-active' : 'badge-inactive' }}">{{ $item->labelJenis() }}</span>
                    </div>

                    <dl class="quick-facts">
                        <div>
                            <dt>Perubahan</dt>
                            <dd>{{ $perubahan > 0 ? '+' : '' }}{{ number_format($perubahan, 2, ',', '.') }} {{ $item->barang->satuanBarang->nama }}</dd>
                        </div>
                        <div>
                            <dt>Saldo akhir</dt>
                            <dd>{{ number_format((float) $item->saldo_sesudah, 2, ',', '.') }} {{ $item->barang->satuanBarang->nama }}</dd>
                        </div>
                        <div>
                            <dt>Kategori</dt>
                            <dd>{{ $item->labelKategori() }}</dd>
                        </div>
                    </dl>

                    <div class="actions" style="margin-top: 14px;">
                        <a href="{{ route('mutasi-stok-barang.show', $item) }}" class="button button-muted">Lihat detail</a>
                    </div>
                </article>
            @empty
                <div class="empty-state">Belum ada mutasi stok barang.</div>
            @endforelse
        </div>
    </section>

    @if ($mutasiStokBarang->hasPages())
        <nav class="pagination-simple">
            <div>Halaman {{ $mutasiStokBarang->currentPage() }} dari {{ $mutasiStokBarang->lastPage() }}</div>
            <div class="actions">
                @if ($mutasiStokBarang->onFirstPage())
                    <span class="button button-muted" aria-disabled="true">Sebelumnya</span>
                @else
                    <a href="{{ $mutasiStokBarang->previousPageUrl() }}" class="button button-muted">Sebelumnya</a>
                @endif

                @if ($mutasiStokBarang->hasMorePages())
                    <a href="{{ $mutasiStokBarang->nextPageUrl() }}" class="button button-muted">Berikutnya</a>
                @else
                    <span class="button button-muted" aria-disabled="true">Berikutnya</span>
                @endif
            </div>
        </nav>
    @endif
@endsection
