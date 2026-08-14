@extends('layouts.app')

@section('title', 'Detail Unit Aset - NUSA')

@section('content')
    <style>
        .asset-state-grid {
            display: grid;
            grid-template-columns: repeat(4, minmax(0, 1fr));
            gap: 1px;
            margin-top: 16px;
            overflow: hidden;
            border: 1px solid var(--line);
            border-radius: 8px;
            background: var(--line);
        }

        .asset-state-item {
            min-width: 0;
            background: #f8fafc;
            padding: 14px;
        }

        .asset-state-item span {
            display: block;
            color: var(--muted);
            font-size: .76rem;
            font-weight: 750;
        }

        .asset-state-item strong {
            display: block;
            margin-top: 5px;
            overflow-wrap: anywhere;
            color: var(--primary-dark);
            line-height: 1.35;
        }

        .asset-loan-notice {
            display: grid;
            grid-template-columns: minmax(0, 1fr) auto;
            gap: 14px;
            align-items: center;
            margin-top: 14px;
            border-left: 4px solid var(--accent);
            background: #fff9dc;
            padding: 13px 14px;
        }

        .asset-loan-notice p {
            margin: 4px 0 0;
            color: #675300;
            font-size: .84rem;
        }

        .asset-history {
            position: relative;
            display: grid;
            gap: 0;
            margin: 18px 0 0 7px;
            border-left: 2px solid #d7e2ee;
            padding-left: 24px;
        }

        .asset-history-item {
            position: relative;
            padding: 0 0 22px;
        }

        .asset-history-item:last-child {
            padding-bottom: 0;
        }

        .asset-history-item::before {
            position: absolute;
            top: 4px;
            left: -31px;
            width: 12px;
            height: 12px;
            border: 3px solid #fff;
            border-radius: 50%;
            background: var(--accent);
            box-shadow: 0 0 0 1px #b8c9da;
            content: '';
        }

        .asset-history-item[data-kind="peminjaman"]::before {
            background: #15477a;
        }

        .asset-history-item[data-kind="pengembalian"]::before {
            background: #18864b;
        }

        .asset-history-head {
            display: flex;
            justify-content: space-between;
            gap: 14px;
            align-items: flex-start;
        }

        .asset-history-title {
            margin: 6px 0 0;
            color: var(--primary-dark);
            font-size: .98rem;
        }

        .asset-history-date {
            flex: 0 0 auto;
            color: var(--muted);
            font-size: .78rem;
            font-weight: 750;
        }

        .asset-history-description {
            margin: 7px 0 0;
            color: #475569;
            line-height: 1.55;
        }

        .asset-history-meta {
            display: flex;
            flex-wrap: wrap;
            gap: 7px 14px;
            margin: 9px 0 0;
            color: #334155;
            font-size: .8rem;
        }

        .asset-history-meta strong {
            color: var(--primary-dark);
        }

        .asset-history-link {
            display: inline-flex;
            margin-top: 10px;
            color: var(--primary);
            font-size: .8rem;
            font-weight: 800;
            text-decoration: none;
        }

        .asset-history-link:hover {
            text-decoration: underline;
        }

        @media (max-width: 850px) {
            .asset-state-grid {
                grid-template-columns: repeat(2, minmax(0, 1fr));
            }
        }

        @media (max-width: 600px) {
            .asset-state-grid,
            .asset-loan-notice {
                grid-template-columns: 1fr;
            }

            .asset-history-head {
                display: grid;
                grid-template-columns: 1fr;
                gap: 6px;
            }
        }
    </style>

    <div class="page-header">
        <div>
            <p class="eyebrow">Sarana Prasarana</p>
            <h1 class="page-title">Detail unit aset</h1>
        </div>

        <div class="actions">
            <a href="{{ route('unit-barang.index') }}" class="button button-muted">Kembali</a>
            <a href="{{ route('label-barcode-inventaris.index', ['unit_barang_id' => [$unitBarang->id]]) }}" class="button button-muted">Cetak label</a>
            @if ($detailPeminjamanAktif)
                @izin('barang.peminjaman_kelola')
                    <a href="{{ route('pengembalian-barang.index', ['kode' => $unitBarang->kode_inventaris]) }}" class="button button-primary">Proses pengembalian</a>
                @endizin
            @endif
            @izin('barang.kelola')
                <a href="{{ route('unit-barang.edit', $unitBarang) }}" class="button button-dark">Edit</a>
            @endizin
        </div>
    </div>

    @if (session('berhasil'))
        <div class="alert">{{ session('berhasil') }}</div>
    @endif

    <div class="detail-shell">
        <aside class="panel panel-pad">
            <div class="detail-profile">
                <div class="avatar avatar-lg">UA</div>
                <h2>{{ $unitBarang->barang->nama }}</h2>
                <p>{{ $unitBarang->kode_inventaris }}</p>

                <div class="actions" style="justify-content: center; margin-top: 16px;">
                    <span class="badge {{ $unitBarang->status_unit === 'tersedia' ? 'badge-active' : 'badge-inactive' }}">{{ $unitBarang->labelStatus() }}</span>
                    @if ($unitBarang->aktif)
                        <span class="badge badge-active">Aktif</span>
                    @else
                        <span class="badge badge-inactive">Nonaktif</span>
                    @endif
                </div>
            </div>

            @izin('barang.kelola')
                @if ($unitBarang->aktif)
                    <form action="{{ route('unit-barang.destroy', $unitBarang) }}" method="POST" style="margin-top: 24px;" onsubmit="return confirm('Nonaktifkan unit aset ini?')">
                        @csrf
                        @method('DELETE')
                        <button type="submit" class="button button-danger button-full">Nonaktifkan</button>
                    </form>
                @endif
            @endizin
        </aside>

        <div class="section-stack">
            <section class="panel panel-pad">
                <div class="page-header" style="margin-bottom: 0;">
                    <div>
                        <p class="eyebrow">Status terkini</p>
                        <h2 class="panel-title">Keadaan aset saat ini</h2>
                    </div>
                    <span class="badge {{ $unitBarang->kondisi === 'baik' ? 'badge-active' : 'badge-warning' }}">{{ $unitBarang->labelKondisi() }}</span>
                </div>

                <div class="asset-state-grid">
                    <div class="asset-state-item">
                        <span>Status unit</span>
                        <strong>{{ $unitBarang->labelStatus() }}</strong>
                    </div>
                    <div class="asset-state-item">
                        <span>Kondisi</span>
                        <strong>{{ $unitBarang->labelKondisi() }}</strong>
                    </div>
                    <div class="asset-state-item">
                        <span>Lokasi tercatat</span>
                        <strong>{{ $unitBarang->lokasiBarang?->nama ?: '-' }}</strong>
                    </div>
                    <div class="asset-state-item">
                        <span>Penguasaan saat ini</span>
                        <strong>{{ $detailPeminjamanAktif?->peminjamanBarang?->namaPeminjam() ?: 'Sekolah' }}</strong>
                    </div>
                </div>

                @if ($detailPeminjamanAktif)
                    <div class="asset-loan-notice">
                        <div>
                            <strong>Sedang dipinjam melalui {{ $detailPeminjamanAktif->peminjamanBarang->nomor_peminjaman }}</strong>
                            <p>{{ $detailPeminjamanAktif->peminjamanBarang->identitasPeminjam() }} &middot; Rencana kembali {{ $detailPeminjamanAktif->peminjamanBarang->rencana_kembali?->locale('id')->translatedFormat('d F Y') ?: 'belum ditentukan' }}</p>
                        </div>
                        <a href="{{ route('peminjaman-barang.show', $detailPeminjamanAktif->peminjamanBarang) }}" class="button button-muted button-sm">Lihat transaksi</a>
                    </div>
                @endif
            </section>

            <section class="panel panel-pad">
                <h2 class="panel-title">Identitas Aset</h2>

                <dl class="detail-grid">
                    <div class="detail-item">
                        <dt>ID internal NUSA</dt>
                        <dd>{{ $unitBarang->kode_inventaris }}</dd>
                    </div>
                    <div class="detail-item">
                        <dt>Nomor aset resmi</dt>
                        <dd>{{ $unitBarang->nomor_aset_resmi ?: '-' }}</dd>
                    </div>
                    <div class="detail-item">
                        <dt>Barang</dt>
                        <dd>{{ $unitBarang->barang->nama }}</dd>
                    </div>
                    <div class="detail-item">
                        <dt>Kode barang</dt>
                        <dd>{{ $unitBarang->barang->kode }}</dd>
                    </div>
                    <div class="detail-item">
                        <dt>Kategori</dt>
                        <dd>{{ $unitBarang->barang->kategoriBarang->nama }}</dd>
                    </div>
                    <div class="detail-item">
                        <dt>Nomor seri</dt>
                        <dd>{{ $unitBarang->nomor_seri ?: '-' }}</dd>
                    </div>
                    <div class="detail-item">
                        <dt>Merek/tipe</dt>
                        <dd>{{ collect([$unitBarang->merek, $unitBarang->tipe])->filter()->join(' - ') ?: '-' }}</dd>
                    </div>
                    <div class="detail-item">
                        <dt>Lokasi saat ini</dt>
                        <dd>{{ $unitBarang->lokasiBarang?->nama ?: '-' }}</dd>
                    </div>
                    <div class="detail-item">
                        <dt>Kondisi</dt>
                        <dd>{{ $unitBarang->labelKondisi() }}</dd>
                    </div>
                    <div class="detail-item">
                        <dt>Status unit</dt>
                        <dd>{{ $unitBarang->labelStatus() }}</dd>
                    </div>
                </dl>
            </section>

            <section class="panel panel-pad">
                <h2 class="panel-title">Perolehan dan Catatan</h2>

                <dl class="detail-grid">
                    <div class="detail-item">
                        <dt>Tanggal perolehan</dt>
                        <dd>{{ $unitBarang->tanggal_perolehan?->locale('id')->translatedFormat('d F Y') ?: '-' }}</dd>
                    </div>
                    <div class="detail-item">
                        <dt>Tahun perolehan</dt>
                        <dd>{{ $unitBarang->tahun_perolehan ?: '-' }}</dd>
                    </div>
                    <div class="detail-item">
                        <dt>Sumber perolehan</dt>
                        <dd>{{ $unitBarang->sumberPerolehanBarang?->nama ?: ($unitBarang->sumber_perolehan ?: '-') }}</dd>
                    </div>
                    <div class="detail-item">
                        <dt>Harga perolehan</dt>
                        <dd>{{ $unitBarang->harga_perolehan !== null ? 'Rp ' . number_format((float) $unitBarang->harga_perolehan, 0, ',', '.') : '-' }}</dd>
                    </div>
                    <div class="detail-item span-2">
                        <dt>Keterangan</dt>
                        <dd style="white-space: pre-line;">{{ $unitBarang->keterangan ?: '-' }}</dd>
                    </div>
                </dl>
            </section>

            <section class="panel panel-pad">
                <div class="page-header" style="margin-bottom: 0;">
                    <div>
                        <p class="eyebrow">Jejak aset</p>
                        <h2 class="panel-title">Riwayat aset</h2>
                        <p class="help-text">Penerimaan, peminjaman, pengembalian, dan kondisi dicatat berurutan.</p>
                    </div>
                    <span class="badge badge-muted">{{ $riwayatUnit->count() }} peristiwa</span>
                </div>

                <div class="asset-history">
                    @foreach ($riwayatUnit as $riwayat)
                        <article class="asset-history-item" data-kind="{{ $riwayat['jenis'] }}">
                            <div class="asset-history-head">
                                <div>
                                    <span class="badge {{ $riwayat['jenis'] === 'pengembalian' ? 'badge-active' : ($riwayat['jenis'] === 'peminjaman' ? 'badge-warning' : 'badge-muted') }}">{{ $riwayat['label'] }}</span>
                                    <h3 class="asset-history-title">{{ $riwayat['judul'] }}</h3>
                                </div>
                                <time class="asset-history-date">{{ $riwayat['tanggal']?->locale('id')->translatedFormat('d F Y') ?: '-' }}</time>
                            </div>

                            <p class="asset-history-description">{{ $riwayat['keterangan'] }}</p>

                            @if ($riwayat['meta'])
                                <div class="asset-history-meta">
                                    @foreach ($riwayat['meta'] as $label => $nilai)
                                        <span><strong>{{ $label }}:</strong> {{ $nilai }}</span>
                                    @endforeach
                                </div>
                            @endif

                            @if ($riwayat['tautan'])
                                <a href="{{ $riwayat['tautan'] }}" class="asset-history-link">{{ $riwayat['label_tautan'] }} &rarr;</a>
                            @endif
                        </article>
                    @endforeach
                </div>
            </section>
        </div>
    </div>
@endsection
