@extends('layouts.app')

@section('title', 'Katalog Barang - NUSA')

@section('content')
    <style>
        .catalog-filter-grid {
            display: grid;
            grid-template-columns: minmax(240px, 1.25fr) repeat(3, minmax(170px, .75fr)) auto;
            gap: 12px;
            align-items: end;
        }

        .catalog-grid {
            display: grid;
            grid-template-columns: repeat(2, minmax(0, 1fr));
            gap: 14px;
        }

        .catalog-stats {
            grid-template-columns: repeat(4, minmax(0, 1fr));
        }

        .catalog-card {
            display: flex;
            min-width: 0;
            flex-direction: column;
            border: 1px solid var(--line);
            border-radius: 8px;
            background: #fff;
            overflow: hidden;
        }

        .catalog-card-head,
        .catalog-card-body,
        .catalog-card-foot {
            padding: 16px;
        }

        .catalog-card-head {
            display: flex;
            align-items: flex-start;
            justify-content: space-between;
            gap: 14px;
            border-bottom: 1px solid var(--line);
        }

        .catalog-card-title {
            min-width: 0;
        }

        .catalog-card-title h2 {
            margin: 3px 0 0;
            overflow-wrap: anywhere;
            color: var(--primary-dark);
            font-size: 1.04rem;
            line-height: 1.35;
        }

        .catalog-card-body {
            display: grid;
            flex: 1;
            gap: 14px;
        }

        .catalog-availability {
            display: grid;
            grid-template-columns: repeat(2, minmax(0, 1fr));
            gap: 10px;
        }

        .catalog-metric {
            border: 1px solid #d9e3ee;
            border-radius: 7px;
            background: #f7f9fc;
            padding: 11px 12px;
        }

        .catalog-metric strong,
        .catalog-metric span {
            display: block;
        }

        .catalog-metric strong {
            color: #0b3967;
            font-size: 1.18rem;
            line-height: 1.2;
        }

        .catalog-metric span {
            margin-top: 4px;
            color: var(--muted);
            font-size: .82rem;
        }

        .catalog-progress {
            height: 7px;
            border-radius: 999px;
            background: #e5eaf0;
            overflow: hidden;
        }

        .catalog-progress span {
            display: block;
            height: 100%;
            border-radius: inherit;
            background: #1f8a55;
        }

        .catalog-section-title {
            margin: 0 0 7px;
            color: #334155;
            font-size: .78rem;
            font-weight: 800;
            text-transform: uppercase;
        }

        .catalog-location-list,
        .catalog-borrower-list {
            display: grid;
            gap: 7px;
        }

        .catalog-location-row,
        .catalog-borrower-row {
            display: flex;
            align-items: flex-start;
            justify-content: space-between;
            gap: 12px;
            border-top: 1px solid #e8edf3;
            padding-top: 7px;
            color: #334155;
            font-size: .86rem;
        }

        .catalog-location-row:first-child,
        .catalog-borrower-row:first-child {
            border-top: 0;
            padding-top: 0;
        }

        .catalog-location-row span:last-child {
            flex: none;
            font-weight: 800;
        }

        .catalog-borrower-row div {
            min-width: 0;
        }

        .catalog-borrower-row strong,
        .catalog-borrower-row span {
            display: block;
            overflow-wrap: anywhere;
        }

        .catalog-borrower-row span {
            margin-top: 2px;
            color: var(--muted);
            font-size: .78rem;
        }

        .catalog-card-foot {
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 12px;
            border-top: 1px solid var(--line);
            background: #fbfcfe;
            color: var(--muted);
            font-size: .82rem;
        }

        .catalog-card-foot > span {
            min-width: 0;
        }

        .catalog-empty {
            grid-column: 1 / -1;
            padding: 44px 20px;
            text-align: center;
        }

        @media (max-width: 1080px) {
            .catalog-stats {
                grid-template-columns: repeat(2, minmax(0, 1fr));
            }

            .catalog-filter-grid {
                grid-template-columns: repeat(2, minmax(0, 1fr));
            }

            .catalog-filter-actions {
                grid-column: 1 / -1;
            }
        }

        @media (max-width: 760px) {
            .catalog-stats,
            .catalog-grid,
            .catalog-filter-grid {
                grid-template-columns: 1fr;
            }

            .catalog-filter-actions {
                grid-column: auto;
            }

            .catalog-card-head {
                align-items: stretch;
                flex-direction: column;
            }

            .catalog-card-foot {
                align-items: stretch;
                flex-direction: column;
            }
        }

        @media (max-width: 420px) {
            .catalog-availability {
                grid-template-columns: 1fr;
            }
        }
    </style>

    <div class="page-header">
        <div>
            <p class="eyebrow">Layanan Sarana Prasarana</p>
            <h1 class="page-title">Katalog barang sekolah</h1>
            <p class="help-text" style="margin-top: 6px;">Lihat barang yang tersedia sebelum menghubungi petugas inventaris.</p>
        </div>
        @if (auth()->user()?->akunPegawai())
            <a href="{{ route('pengajuan-barang-saya.index') }}" class="button button-muted">Pengajuan saya</a>
        @endif
    </div>

    <section class="stats-grid catalog-stats">
        <article class="panel stat">
            <p class="stat-label">Jenis barang aktif</p>
            <p class="stat-value">{{ $ringkasan['barang_aktif'] }}</p>
        </article>
        <article class="panel stat active">
            <p class="stat-label">Unit aset tersedia</p>
            <p class="stat-value">{{ $ringkasan['unit_tersedia'] }}</p>
        </article>
        <article class="panel stat inactive">
            <p class="stat-label">Unit sedang dipinjam</p>
            <p class="stat-value">{{ $ringkasan['unit_dipinjam'] }}</p>
        </article>
        <article class="panel stat">
            <p class="stat-label">Barang berbasis stok</p>
            <p class="stat-value">{{ $ringkasan['stok_tersedia'] }}</p>
            <p class="person-meta">{{ $ringkasan['stok_habis'] }} jenis sedang habis.</p>
        </article>
    </section>

    <form action="{{ route('katalog-barang.index') }}" method="GET" class="panel panel-pad" style="margin-bottom: 24px;">
        <div class="catalog-filter-grid">
            <div class="field">
                <label for="kata_kunci">Cari barang</label>
                <input id="kata_kunci" name="kata_kunci" type="search" value="{{ $kata_kunci }}" placeholder="Nama, kode, atau kategori" class="input" autocomplete="off">
            </div>

            <div class="field">
                <label for="kategori_barang_id">Kategori</label>
                <select id="kategori_barang_id" name="kategori_barang_id" class="select">
                    <option value="">Semua kategori</option>
                    @foreach ($daftarKategori as $kategori)
                        <option value="{{ $kategori->id }}" @selected((string) $kategori_barang_id === (string) $kategori->id)>{{ $kategori->nama }}</option>
                    @endforeach
                </select>
            </div>

            <div class="field">
                <label for="jenis_barang">Jenis barang</label>
                <select id="jenis_barang" name="jenis_barang" class="select">
                    <option value="semua">Semua jenis</option>
                    @foreach ($daftarJenisBarang as $nilai => $label)
                        <option value="{{ $nilai }}" @selected($jenis_barang === $nilai)>{{ $label }}</option>
                    @endforeach
                </select>
            </div>

            <div class="field">
                <label for="ketersediaan">Ketersediaan</label>
                <select id="ketersediaan" name="ketersediaan" class="select">
                    @foreach ($daftarKetersediaan as $nilai => $label)
                        <option value="{{ $nilai }}" @selected($ketersediaan === $nilai)>{{ $label }}</option>
                    @endforeach
                </select>
            </div>

            <div class="actions catalog-filter-actions">
                <button type="submit" class="button button-dark">Tampilkan</button>
                @if ($kata_kunci !== '' || $kategori_barang_id || $jenis_barang !== 'semua' || $ketersediaan !== 'semua')
                    <a href="{{ route('katalog-barang.index') }}" class="button button-muted">Reset</a>
                @endif
            </div>
        </div>
    </form>

    <section class="catalog-grid" aria-live="polite">
        @forelse ($barang as $item)
            @php
                $asetIndividual = $item->tipe_pengelolaan === 'aset_individual';
                $jumlahUnit = (int) $item->jumlah_unit_aktif;
                $jumlahTersedia = (int) $item->jumlah_unit_tersedia;
                $jumlahDipinjam = (int) $item->jumlah_unit_dipinjam;
                $jumlahStok = max((float) ($item->jumlah_stok ?? 0), 0);
                $punyaKetersediaan = $asetIndividual ? $jumlahTersedia > 0 : $jumlahStok > 0;
                $persentaseTersedia = $asetIndividual && $jumlahUnit > 0
                    ? min(($jumlahTersedia / $jumlahUnit) * 100, 100)
                    : ($jumlahStok > 0 ? 100 : 0);
            @endphp

            <article class="catalog-card">
                <header class="catalog-card-head">
                    <div class="catalog-card-title">
                        <p class="person-meta">{{ $item->kategoriBarang->nama }} &middot; {{ $item->kode }}</p>
                        <h2>{{ $item->nama }}</h2>
                    </div>
                    <span class="badge {{ $punyaKetersediaan ? 'badge-active' : 'badge-inactive' }}">
                        {{ $punyaKetersediaan ? 'Tersedia' : 'Tidak tersedia' }}
                    </span>
                </header>

                <div class="catalog-card-body">
                    <div>
                        <p class="catalog-section-title">Ketersediaan</p>
                        <div class="catalog-availability">
                            @if ($asetIndividual)
                                <div class="catalog-metric">
                                    <strong>{{ $jumlahTersedia }} dari {{ $jumlahUnit }}</strong>
                                    <span>unit tersedia</span>
                                </div>
                                <div class="catalog-metric">
                                    <strong>{{ $jumlahDipinjam }}</strong>
                                    <span>unit sedang dipinjam</span>
                                </div>
                            @else
                                <div class="catalog-metric">
                                    <strong>{{ number_format($jumlahStok, 2, ',', '.') }}</strong>
                                    <span>{{ $item->satuanBarang->nama }} tersedia</span>
                                </div>
                                <div class="catalog-metric">
                                    <strong>{{ $item->labelJenisBarang() }}</strong>
                                    <span>pengelolaan berdasarkan stok</span>
                                </div>
                            @endif
                        </div>
                        <div class="catalog-progress" style="margin-top: 9px;" aria-hidden="true">
                            <span style="width: {{ $persentaseTersedia }}%;"></span>
                        </div>
                    </div>

                    <div>
                        <p class="catalog-section-title">Lokasi barang</p>
                        <div class="catalog-location-list">
                            @forelse ($item->lokasi_ketersediaan as $lokasi)
                                <div class="catalog-location-row">
                                    <span>{{ $lokasi['lokasi'] }}</span>
                                    @if ($asetIndividual)
                                        <span>{{ $lokasi['tersedia'] }} dari {{ $lokasi['jumlah'] }} tersedia</span>
                                    @else
                                        <span>{{ number_format((float) $lokasi['jumlah'], 2, ',', '.') }} {{ $item->satuanBarang->nama }}</span>
                                    @endif
                                </div>
                            @empty
                                <p class="help-text">Lokasi dan jumlah barang belum dicatat.</p>
                            @endforelse
                        </div>
                    </div>

                    @if ($asetIndividual && $item->daftar_peminjam_aktif->isNotEmpty())
                        <div>
                            <p class="catalog-section-title">Sedang dipinjam oleh</p>
                            <div class="catalog-borrower-list">
                                @foreach ($item->daftar_peminjam_aktif as $peminjaman)
                                    <div class="catalog-borrower-row">
                                        <div>
                                            <strong>{{ $peminjaman['nama'] }}</strong>
                                            <span>{{ $peminjaman['unit'] }}</span>
                                        </div>
                                        <span>
                                            {{ $peminjaman['rencana_kembali']?->locale('id')->translatedFormat('d M Y') ?: 'Belum ditentukan' }}
                                        </span>
                                    </div>
                                @endforeach
                            </div>
                        </div>
                    @endif
                </div>

                <footer class="catalog-card-foot">
                    <span>{{ $asetIndividual ? 'Barang tidak habis pakai' : $item->labelJenisBarang() }}</span>
                    @if (auth()->user()?->akunPegawai())
                        @if ($punyaKetersediaan)
                            <a href="{{ route('pengajuan-barang-saya.create', $item) }}" class="button button-primary">
                                {{ $item->jenis_barang === 'habis_pakai' ? 'Minta barang' : 'Ajukan peminjaman' }}
                            </a>
                        @else
                            <span class="button button-muted" aria-disabled="true">Belum tersedia</span>
                        @endif
                    @endif
                </footer>
            </article>
        @empty
            <div class="panel catalog-empty">
                <p class="person-name">Barang tidak ditemukan.</p>
                <p class="help-text" style="margin-top: 5px;">Ubah pencarian atau pilihan filter.</p>
            </div>
        @endforelse
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
