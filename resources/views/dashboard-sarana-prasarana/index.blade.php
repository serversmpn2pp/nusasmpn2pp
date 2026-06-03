@extends('layouts.app')

@section('title', 'Dashboard Sarana Prasarana - NUSA')

@section('content')
    <style>
        .sarpras-hero {
            display: grid;
            grid-template-columns: minmax(0, 1.35fr) minmax(240px, .65fr);
            overflow: hidden;
            margin-bottom: 18px;
            border: 1px solid rgba(21, 71, 122, .2);
            border-radius: 8px;
            background: #15477a;
            color: #fff;
            box-shadow: var(--shadow);
        }

        .sarpras-hero-main,
        .sarpras-hero-side {
            padding: 24px;
        }

        .sarpras-hero-side {
            display: grid;
            align-content: center;
            gap: 8px;
            border-left: 1px solid rgba(255, 255, 255, .18);
            background: rgba(255, 255, 255, .08);
        }

        .sarpras-hero .eyebrow {
            color: #f1c40f;
        }

        .sarpras-title {
            margin: 0;
            font-size: 2rem;
            line-height: 1.15;
        }

        .sarpras-subtitle {
            max-width: 720px;
            margin: 10px 0 0;
            color: rgba(255, 255, 255, .84);
            line-height: 1.6;
        }

        .sarpras-date {
            margin: 0;
            color: #f1c40f;
            font-size: .86rem;
            font-weight: 900;
            text-transform: uppercase;
        }

        .sarpras-side-value {
            margin: 0;
            font-size: 1.35rem;
            font-weight: 900;
        }

        .sarpras-side-note {
            margin: 0;
            color: rgba(255, 255, 255, .74);
            font-size: .85rem;
        }

        .sarpras-kpi-grid {
            display: grid;
            grid-template-columns: repeat(4, minmax(0, 1fr));
            gap: 12px;
            margin-bottom: 18px;
        }

        .sarpras-kpi {
            min-height: 112px;
            padding: 16px;
        }

        .sarpras-kpi.attention {
            border-color: #f1c40f;
            background: #fffbea;
        }

        .sarpras-kpi.danger {
            border-color: #fecaca;
            background: #fff7f7;
        }

        .sarpras-kpi p {
            margin: 0;
        }

        .sarpras-kpi-label {
            color: var(--muted);
            font-size: .82rem;
            font-weight: 800;
        }

        .sarpras-kpi-value {
            margin-top: 7px !important;
            color: #15477a;
            font-size: 1.75rem;
            font-weight: 900;
            line-height: 1;
        }

        .sarpras-kpi-note {
            margin-top: 8px !important;
            color: var(--muted);
            font-size: .77rem;
        }

        .sarpras-actions {
            display: grid;
            grid-template-columns: repeat(6, minmax(0, 1fr));
            gap: 10px;
            margin-bottom: 18px;
        }

        .sarpras-action {
            display: flex;
            min-height: 58px;
            align-items: center;
            justify-content: space-between;
            gap: 10px;
            border: 1px solid var(--line);
            border-radius: 8px;
            background: #fff;
            padding: 12px;
            color: #15477a;
            font-size: .84rem;
            font-weight: 900;
            text-decoration: none;
            transition: border-color .16s ease, background .16s ease;
        }

        .sarpras-action:hover {
            border-color: #15477a;
            background: #eff6ff;
        }

        .sarpras-action-mark {
            display: grid;
            width: 28px;
            height: 28px;
            flex: 0 0 auto;
            place-items: center;
            border-radius: 6px;
            background: #15477a;
            color: #f1c40f;
            font-size: .69rem;
        }

        .sarpras-main-grid {
            display: grid;
            grid-template-columns: minmax(0, 1.08fr) minmax(320px, .92fr);
            gap: 18px;
        }

        .sarpras-stack {
            display: grid;
            gap: 18px;
            align-content: start;
        }

        .sarpras-panel {
            overflow: hidden;
            border: 1px solid var(--line);
            border-radius: 8px;
            background: #fff;
            box-shadow: var(--shadow);
        }

        .sarpras-panel-head {
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 12px;
            border-bottom: 1px solid var(--line);
            padding: 14px 16px;
        }

        .sarpras-panel-head h2 {
            margin: 0;
            color: #15477a;
            font-size: 1rem;
        }

        .sarpras-panel-body {
            padding: 0 16px;
        }

        .sarpras-list-item {
            display: flex;
            align-items: flex-start;
            justify-content: space-between;
            gap: 12px;
            border-bottom: 1px solid var(--line);
            padding: 13px 0;
        }

        .sarpras-list-item:last-child {
            border-bottom: 0;
        }

        .sarpras-list-item p {
            margin: 0;
        }

        .sarpras-list-item small {
            display: block;
            margin-top: 4px;
            color: var(--muted);
            line-height: 1.35;
        }

        .sarpras-item-actions {
            display: flex;
            flex: 0 0 auto;
            align-items: center;
            gap: 7px;
        }

        .sarpras-chart {
            display: grid;
            gap: 11px;
            padding: 16px 0;
        }

        .sarpras-chart-row {
            display: grid;
            grid-template-columns: 122px minmax(0, 1fr) 32px;
            gap: 9px;
            align-items: center;
            font-size: .82rem;
        }

        .sarpras-chart-track {
            display: block;
            height: 9px;
            overflow: hidden;
            border-radius: 999px;
            background: #e2e8f0;
        }

        .sarpras-chart-fill {
            display: block;
            height: 100%;
            border-radius: inherit;
            background: var(--bar-color);
        }

        .sarpras-section-note {
            border-top: 1px solid var(--line);
            padding: 12px 16px;
            color: var(--muted);
            font-size: .8rem;
        }

        @media (max-width: 1120px) {
            .sarpras-kpi-grid {
                grid-template-columns: repeat(2, minmax(0, 1fr));
            }

            .sarpras-actions {
                grid-template-columns: repeat(3, minmax(0, 1fr));
            }

            .sarpras-main-grid {
                grid-template-columns: 1fr;
            }
        }

        @media (max-width: 680px) {
            .sarpras-hero {
                grid-template-columns: 1fr;
            }

            .sarpras-hero-side {
                border-top: 1px solid rgba(255, 255, 255, .18);
                border-left: 0;
            }

            .sarpras-title {
                font-size: 1.55rem;
            }

            .sarpras-kpi-grid,
            .sarpras-actions {
                grid-template-columns: 1fr;
            }

            .sarpras-chart-row {
                grid-template-columns: 104px minmax(0, 1fr) 28px;
            }
        }
    </style>

    @php
        $formatJumlah = fn (int|float $nilai) => number_format($nilai, 0, ',', '.');
        $penggunaAktif = auth()->user();
    @endphp

    <section class="sarpras-hero">
        <div class="sarpras-hero-main">
            <p class="eyebrow">Sarana Prasarana</p>
            <h1 class="sarpras-title">Dashboard inventaris sekolah</h1>
            <p class="sarpras-subtitle">Pantau aset, persediaan, peminjaman, dan barang yang perlu ditindaklanjuti dari satu halaman kerja.</p>
        </div>

        <div class="sarpras-hero-side">
            <p class="sarpras-date">{{ $hariIni->locale('id')->translatedFormat('l, d F Y') }}</p>
            <p class="sarpras-side-value">{{ $formatJumlah($ringkasan['jenis_barang']) }} jenis barang aktif</p>
            <p class="sarpras-side-note">{{ $formatJumlah($ringkasan['unit_aset']) }} unit aset tercatat dalam NUSA.</p>
        </div>
    </section>

    <section class="sarpras-kpi-grid" aria-label="Ringkasan sarana prasarana">
        <article class="panel sarpras-kpi">
            <p class="sarpras-kpi-label">Jenis barang aktif</p>
            <p class="sarpras-kpi-value">{{ $formatJumlah($ringkasan['jenis_barang']) }}</p>
            <p class="sarpras-kpi-note">{{ $formatJumlah($ringkasan['unit_aset']) }} unit aset individual.</p>
        </article>
        <article class="panel sarpras-kpi">
            <p class="sarpras-kpi-label">Unit aset tersedia</p>
            <p class="sarpras-kpi-value">{{ $formatJumlah($ringkasan['unit_tersedia']) }}</p>
            <p class="sarpras-kpi-note">Siap digunakan atau dipinjam.</p>
        </article>
        <article class="panel sarpras-kpi attention">
            <p class="sarpras-kpi-label">Stok perlu perhatian</p>
            <p class="sarpras-kpi-value">{{ $formatJumlah($ringkasan['stok_menipis'] + $ringkasan['stok_habis']) }}</p>
            <p class="sarpras-kpi-note">{{ $formatJumlah($ringkasan['stok_habis']) }} habis, {{ $formatJumlah($ringkasan['stok_menipis']) }} menipis.</p>
        </article>
        <article class="panel sarpras-kpi">
            <p class="sarpras-kpi-label">Peminjaman aktif</p>
            <p class="sarpras-kpi-value">{{ $formatJumlah($ringkasan['peminjaman_aktif']) }}</p>
            <p class="sarpras-kpi-note">{{ $formatJumlah($ringkasan['jatuh_tempo']) }} jatuh tempo dalam 7 hari.</p>
        </article>
        <article class="panel sarpras-kpi danger">
            <p class="sarpras-kpi-label">Terlambat dikembalikan</p>
            <p class="sarpras-kpi-value">{{ $formatJumlah($ringkasan['peminjaman_terlambat']) }}</p>
            <p class="sarpras-kpi-note">Perlu tindak lanjut petugas.</p>
        </article>
        <article class="panel sarpras-kpi attention">
            <p class="sarpras-kpi-label">Unit perlu perhatian</p>
            <p class="sarpras-kpi-value">{{ $formatJumlah($ringkasan['unit_perlu_perhatian']) }}</p>
            <p class="sarpras-kpi-note">Rusak, hilang, atau dalam perbaikan.</p>
        </article>
        <article class="panel sarpras-kpi">
            <p class="sarpras-kpi-label">Stok belum dicatat</p>
            <p class="sarpras-kpi-value">{{ $formatJumlah($ringkasan['stok_belum_dicatat']) }}</p>
            <p class="sarpras-kpi-note">Barang stok tanpa saldo awal.</p>
        </article>
    </section>

    <nav class="sarpras-actions" aria-label="Aksi cepat sarana prasarana">
        @izin('barang.lihat', 'barang.kelola')
            <a href="{{ route('barang.index') }}" class="sarpras-action"><span>Inventaris</span><span class="sarpras-action-mark">IB</span></a>
            <a href="{{ route('saldo-stok-barang.index') }}" class="sarpras-action"><span>Saldo stok</span><span class="sarpras-action-mark">SS</span></a>
            <a href="{{ route('unit-barang.index') }}" class="sarpras-action"><span>Unit aset</span><span class="sarpras-action-mark">UA</span></a>
            <a href="{{ route('label-barcode-inventaris.index') }}" class="sarpras-action"><span>Label barcode</span><span class="sarpras-action-mark">BC</span></a>
            <a href="{{ route('laporan-inventaris-bulanan.index') }}" class="sarpras-action"><span>Laporan bulanan</span><span class="sarpras-action-mark">LI</span></a>
        @endizin
        @izin('barang.kelola')
            <a href="{{ route('mutasi-stok-barang.create') }}" class="sarpras-action"><span>Catat mutasi</span><span class="sarpras-action-mark">MS</span></a>
        @endizin
        @izin('barang.peminjaman_kelola')
            <a href="{{ route('peminjaman-barang.create') }}" class="sarpras-action"><span>Catat peminjaman</span><span class="sarpras-action-mark">PB</span></a>
        @endizin
        @izin('barang.lihat', 'barang.peminjaman_kelola')
            <a href="{{ route('rekap-peminjaman-barang.index') }}" class="sarpras-action"><span>Rekap peminjaman</span><span class="sarpras-action-mark">RP</span></a>
        @endizin
    </nav>

    <section class="sarpras-main-grid">
        <div class="sarpras-stack">
            <article class="sarpras-panel">
                <div class="sarpras-panel-head">
                    <h2>Stok Perlu Perhatian</h2>
                    @izin('barang.lihat', 'barang.kelola')
                        <a href="{{ route('saldo-stok-barang.index', ['status_stok' => 'menipis']) }}" class="button button-muted button-sm">Lihat saldo</a>
                    @endizin
                </div>
                <div class="sarpras-panel-body">
                    @forelse ($stokPerluPerhatian as $item)
                        <div class="sarpras-list-item">
                            <div>
                                <p class="person-name">{{ $item->barang->nama }}</p>
                                <small>{{ $item->barang->kode }} - {{ $item->lokasiBarang->nama }} - minimum {{ number_format((float) $item->barang->stok_minimum, 2, ',', '.') }} {{ $item->barang->satuanBarang->nama }}</small>
                            </div>
                            <span class="badge {{ (float) $item->jumlah <= 0 ? 'badge-danger' : 'badge-warning' }}">
                                {{ number_format((float) $item->jumlah, 2, ',', '.') }} {{ $item->barang->satuanBarang->nama }}
                            </span>
                        </div>
                    @empty
                        <div class="empty-state">Tidak ada stok menipis atau habis.</div>
                    @endforelse
                </div>
                @if ($barangStokBelumDicatat->isNotEmpty())
                    <div class="sarpras-section-note">
                        Saldo awal belum dicatat:
                        {{ $barangStokBelumDicatat->pluck('nama')->implode(', ') }}
                        @if ($ringkasan['stok_belum_dicatat'] > $barangStokBelumDicatat->count())
                            dan {{ $formatJumlah($ringkasan['stok_belum_dicatat'] - $barangStokBelumDicatat->count()) }} lainnya.
                        @endif
                    </div>
                @endif
            </article>

            <article class="sarpras-panel">
                <div class="sarpras-panel-head">
                    <h2>Peminjaman Terlambat</h2>
                    @izin('barang.lihat', 'barang.peminjaman_kelola')
                        <a href="{{ route('rekap-peminjaman-barang.index', ['status_pemantauan' => 'terlambat']) }}" class="button button-muted button-sm">Lihat rekap</a>
                    @endizin
                </div>
                <div class="sarpras-panel-body">
                    @forelse ($peminjamanTerlambat as $item)
                        <div class="sarpras-list-item">
                            <div>
                                <p class="person-name">{{ $item->namaPeminjam() }}</p>
                                <small>{{ $item->nomor_peminjaman }} - {{ $item->identitasPeminjam() }} - rencana {{ $item->rencana_kembali->locale('id')->translatedFormat('d M Y') }}</small>
                            </div>
                            <div class="sarpras-item-actions">
                                <span class="badge badge-danger">{{ $item->jumlahHariTerlambat() }} hari</span>
                                @if ($penggunaAktif?->memilikiIzin(['barang.lihat', 'barang.peminjaman_kelola']))
                                    <a href="{{ route('peminjaman-barang.show', $item) }}" class="button button-muted button-sm">Lihat</a>
                                @endif
                            </div>
                        </div>
                    @empty
                        <div class="empty-state">Tidak ada peminjaman yang terlambat.</div>
                    @endforelse
                </div>
            </article>

            <article class="sarpras-panel">
                <div class="sarpras-panel-head">
                    <h2>Aktivitas Terbaru</h2>
                    <span class="badge badge-muted">10 terbaru</span>
                </div>
                <div class="sarpras-panel-body">
                    @forelse ($aktivitasTerbaru as $item)
                        <div class="sarpras-list-item">
                            <div>
                                <p class="person-name">{{ $item['judul'] }}</p>
                                <small>{{ $item['keterangan'] }} - {{ $item['waktu']?->locale('id')->translatedFormat('d M Y H:i') }}</small>
                            </div>
                            <div class="sarpras-item-actions">
                                <span class="badge {{ $item['warna'] }}">{{ $item['jenis'] }}</span>
                                @if ($penggunaAktif?->memilikiIzin($item['izin']))
                                    <a href="{{ $item['route'] }}" class="button button-muted button-sm">Lihat</a>
                                @endif
                            </div>
                        </div>
                    @empty
                        <div class="empty-state">Belum ada aktivitas inventaris.</div>
                    @endforelse
                </div>
            </article>
        </div>

        <div class="sarpras-stack">
            <article class="sarpras-panel">
                <div class="sarpras-panel-head">
                    <h2>Status Unit Aset</h2>
                    @izin('barang.lihat', 'barang.kelola')
                        <a href="{{ route('unit-barang.index') }}" class="button button-muted button-sm">Lihat unit</a>
                    @endizin
                </div>
                <div class="sarpras-panel-body">
                    <div class="sarpras-chart">
                        @foreach ($distribusiStatusUnit as $item)
                            @php
                                $lebar = $maksDistribusiUnit > 0 ? round(($item['jumlah'] / $maksDistribusiUnit) * 100, 2) : 0;
                            @endphp
                            <div class="sarpras-chart-row">
                                <span>{{ $item['label'] }}</span>
                                <span class="sarpras-chart-track" aria-hidden="true">
                                    <span class="sarpras-chart-fill" style="width: {{ $lebar }}%; --bar-color: {{ $item['warna'] }};"></span>
                                </span>
                                <strong>{{ $formatJumlah($item['jumlah']) }}</strong>
                            </div>
                        @endforeach
                    </div>
                </div>
            </article>

            <article class="sarpras-panel">
                <div class="sarpras-panel-head">
                    <h2>Unit Aset Perlu Perhatian</h2>
                    <span class="badge badge-warning">{{ $formatJumlah($ringkasan['unit_perlu_perhatian']) }} unit</span>
                </div>
                <div class="sarpras-panel-body">
                    @forelse ($unitPerluPerhatian as $item)
                        <div class="sarpras-list-item">
                            <div>
                                <p class="person-name">{{ $item->barang->nama }}</p>
                                <small>{{ $item->kode_inventaris }} - {{ $item->lokasiBarang?->nama ?: 'Lokasi belum diisi' }}</small>
                            </div>
                            <div class="sarpras-item-actions">
                                <span class="badge {{ $item->status_unit === 'hilang' || $item->kondisi === 'rusak_berat' ? 'badge-danger' : 'badge-warning' }}">{{ $item->labelStatus() }}</span>
                                @izin('barang.lihat', 'barang.kelola')
                                    <a href="{{ route('unit-barang.show', $item) }}" class="button button-muted button-sm">Lihat</a>
                                @endizin
                            </div>
                        </div>
                    @empty
                        <div class="empty-state">Tidak ada unit aset yang memerlukan perhatian.</div>
                    @endforelse
                </div>
            </article>
        </div>
    </section>
@endsection
