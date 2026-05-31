@extends('layouts.app')

@section('title', 'Peminjaman Barang - NUSA')

@section('content')
    <style>
        .loan-filter-grid {
            grid-template-columns: minmax(190px, 1fr) 150px 170px 145px 145px auto;
        }

        @media (max-width: 1120px) {
            .loan-filter-grid {
                grid-template-columns: repeat(2, minmax(0, 1fr));
            }
        }

        @media (max-width: 620px) {
            .loan-filter-grid {
                grid-template-columns: 1fr;
            }
        }
    </style>

    <div class="page-header">
        <div>
            <p class="eyebrow">Sarana Prasarana</p>
            <h1 class="page-title">Peminjaman barang</h1>
        </div>

        @izin('barang.peminjaman_kelola')
            <a href="{{ route('peminjaman-barang.create') }}" class="button button-primary">Catat peminjaman</a>
        @endizin
    </div>

    <div class="stats-grid">
        <div class="panel stat">
            <p class="stat-label">Total transaksi</p>
            <p class="stat-value">{{ $jumlahTransaksi }}</p>
        </div>
        <div class="panel stat active">
            <p class="stat-label">Masih dipinjam</p>
            <p class="stat-value">{{ $jumlahAktif }}</p>
        </div>
        <div class="panel stat active">
            <p class="stat-label">Transaksi hari ini</p>
            <p class="stat-value">{{ $jumlahHariIni }}</p>
        </div>
        <div class="panel stat">
            <p class="stat-label">Selesai</p>
            <p class="stat-value">{{ $jumlahSelesai }}</p>
        </div>
    </div>

    @if (session('berhasil'))
        <div class="alert">{{ session('berhasil') }}</div>
    @endif

    <form action="{{ route('peminjaman-barang.index') }}" method="GET" class="panel panel-pad" style="margin-bottom: 24px;">
        <div class="filter-grid loan-filter-grid">
            <div class="field">
                <label for="kata_kunci">Cari transaksi</label>
                <input id="kata_kunci" name="kata_kunci" type="search" value="{{ $kataKunci }}" placeholder="Nomor, nama, NISN, atau NIP" class="input">
            </div>

            <div class="field">
                <label for="jenis_peminjam">Peminjam</label>
                <select id="jenis_peminjam" name="jenis_peminjam" class="select">
                    <option value="semua">Semua</option>
                    @foreach ($daftarJenisPeminjam as $nilai => $label)
                        <option value="{{ $nilai }}" @selected($jenisPeminjam === $nilai)>{{ $label }}</option>
                    @endforeach
                </select>
            </div>

            <div class="field">
                <label for="status">Status</label>
                <select id="status" name="status" class="select">
                    <option value="semua">Semua status</option>
                    @foreach ($daftarStatus as $nilai => $label)
                        <option value="{{ $nilai }}" @selected($status === $nilai)>{{ $label }}</option>
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
                <a href="{{ route('peminjaman-barang.index') }}" class="button button-muted">Reset</a>
            </div>
        </div>
    </form>

    <section class="panel">
        <div class="desktop-only table-wrap">
            <table class="employee-table">
                <thead>
                    <tr>
                        <th>Nomor</th>
                        <th>Tanggal</th>
                        <th>Peminjam</th>
                        <th>Barang</th>
                        <th>Rencana kembali</th>
                        <th>Status</th>
                        <th class="text-right">Aksi</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($peminjamanBarang as $item)
                        <tr>
                            <td><strong>{{ $item->nomor_peminjaman }}</strong></td>
                            <td>{{ $item->tanggal_peminjaman->locale('id')->translatedFormat('d M Y') }}</td>
                            <td>
                                <p class="person-name">{{ $item->namaPeminjam() }}</p>
                                <p class="person-meta">{{ $item->identitasPeminjam() }}</p>
                            </td>
                            <td>{{ $item->detail_peminjaman_barang_count }} jenis</td>
                            <td>{{ $item->rencana_kembali?->locale('id')->translatedFormat('d M Y') ?: '-' }}</td>
                            <td><span class="badge {{ $item->status === 'selesai' ? 'badge-active' : 'badge-inactive' }}">{{ $item->labelStatus() }}</span></td>
                            <td>
                                <div class="actions" style="justify-content: flex-end;">
                                    <a href="{{ route('peminjaman-barang.show', $item) }}" class="button button-muted">Lihat</a>
                                </div>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="7" class="empty-state">Belum ada transaksi peminjaman barang.</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        <div class="mobile-only mobile-list">
            @forelse ($peminjamanBarang as $item)
                <article class="mobile-card">
                    <div class="mobile-card-head">
                        <div>
                            <p class="person-name">{{ $item->namaPeminjam() }}</p>
                            <p class="person-meta">{{ $item->nomor_peminjaman }}</p>
                        </div>
                        <span class="badge {{ $item->status === 'selesai' ? 'badge-active' : 'badge-inactive' }}">{{ $item->labelStatus() }}</span>
                    </div>

                    <dl class="quick-facts">
                        <div>
                            <dt>Tanggal</dt>
                            <dd>{{ $item->tanggal_peminjaman->locale('id')->translatedFormat('d M Y') }}</dd>
                        </div>
                        <div>
                            <dt>Identitas</dt>
                            <dd>{{ $item->identitasPeminjam() }}</dd>
                        </div>
                        <div>
                            <dt>Barang</dt>
                            <dd>{{ $item->detail_peminjaman_barang_count }} jenis</dd>
                        </div>
                    </dl>

                    <div class="actions" style="margin-top: 14px;">
                        <a href="{{ route('peminjaman-barang.show', $item) }}" class="button button-muted">Lihat detail</a>
                    </div>
                </article>
            @empty
                <div class="empty-state">Belum ada transaksi peminjaman barang.</div>
            @endforelse
        </div>
    </section>

    @if ($peminjamanBarang->hasPages())
        <nav class="pagination-simple">
            <div>Halaman {{ $peminjamanBarang->currentPage() }} dari {{ $peminjamanBarang->lastPage() }}</div>
            <div class="actions">
                @if ($peminjamanBarang->onFirstPage())
                    <span class="button button-muted" aria-disabled="true">Sebelumnya</span>
                @else
                    <a href="{{ $peminjamanBarang->previousPageUrl() }}" class="button button-muted">Sebelumnya</a>
                @endif

                @if ($peminjamanBarang->hasMorePages())
                    <a href="{{ $peminjamanBarang->nextPageUrl() }}" class="button button-muted">Berikutnya</a>
                @else
                    <span class="button button-muted" aria-disabled="true">Berikutnya</span>
                @endif
            </div>
        </nav>
    @endif
@endsection
