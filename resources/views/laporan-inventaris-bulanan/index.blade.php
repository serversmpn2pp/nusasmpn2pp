@extends('layouts.app')

@section('title', 'Laporan Inventaris Bulanan - NUSA')

@section('content')
    <style>
        .inventory-report-filter-grid {
            display: grid;
            grid-template-columns: repeat(12, minmax(0, 1fr));
            gap: 12px;
            align-items: end;
        }

        .inventory-report-period {
            grid-column: span 3;
        }

        .inventory-report-location {
            grid-column: span 5;
        }

        .inventory-report-actions {
            display: flex;
            grid-column: span 4;
            gap: 8px;
            justify-content: flex-end;
        }

        .inventory-report-section {
            margin-top: 24px;
        }

        .inventory-report-head {
            display: flex;
            align-items: flex-start;
            justify-content: space-between;
            gap: 16px;
            border-bottom: 1px solid var(--line);
            padding: 16px;
        }

        .inventory-report-head h2,
        .inventory-report-head p {
            margin: 0;
        }

        .inventory-report-head p {
            margin-top: 5px;
        }

        .inventory-report-note {
            margin-bottom: 18px;
            border-left: 4px solid var(--accent);
            border-radius: 4px;
            background: #fffbeb;
            padding: 12px 14px;
            color: #713f12;
            font-size: .9rem;
            line-height: 1.55;
        }

        .inventory-asset-status {
            display: grid;
            grid-template-columns: repeat(5, minmax(0, 1fr));
            gap: 12px;
            padding: 16px;
        }

        .inventory-asset-status div {
            border-left: 3px solid var(--primary);
            padding-left: 10px;
        }

        .inventory-asset-status span,
        .inventory-asset-status strong {
            display: block;
        }

        .inventory-asset-status span {
            color: var(--muted);
            font-size: .78rem;
            font-weight: 800;
            text-transform: uppercase;
        }

        .inventory-asset-status strong {
            margin-top: 3px;
            color: var(--primary-dark);
            font-size: 1.55rem;
        }

        .inventory-report-warning-row {
            background: #fff7ed;
        }

        .inventory-report-danger-row {
            background: #fff1f2;
        }

        .inventory-unrecorded-list {
            display: flex;
            flex-wrap: wrap;
            gap: 8px;
            margin-top: 10px;
        }

        .inventory-unrecorded-list span {
            border: 1px solid #fde68a;
            border-radius: 999px;
            background: #fff;
            padding: 4px 9px;
            color: #713f12;
            font-size: .82rem;
            font-weight: 800;
        }

        .inventory-signatory-grid {
            display: grid;
            grid-template-columns: repeat(3, minmax(0, 1fr));
            gap: 12px;
            padding: 16px;
        }

        .inventory-signatory-item {
            border-left: 3px solid var(--accent);
            padding-left: 10px;
        }

        .inventory-signatory-item p {
            margin: 0;
        }

        .inventory-signatory-item .person-name {
            margin-top: 5px;
        }

        @media (max-width: 920px) {
            .inventory-report-period,
            .inventory-report-location {
                grid-column: span 6;
            }

            .inventory-report-actions {
                grid-column: 1 / -1;
            }

            .inventory-asset-status {
                grid-template-columns: repeat(3, minmax(0, 1fr));
            }
        }

        @media (max-width: 620px) {
            .inventory-report-period,
            .inventory-report-location,
            .inventory-report-actions {
                grid-column: 1 / -1;
            }

            .inventory-report-actions {
                flex-direction: column;
            }

            .inventory-report-head {
                align-items: stretch;
                flex-direction: column;
            }

            .inventory-asset-status {
                grid-template-columns: repeat(2, minmax(0, 1fr));
            }

            .inventory-signatory-grid {
                grid-template-columns: 1fr;
            }
        }
    </style>

    @php
        $formatJumlah = fn ($nilai) => number_format((float) $nilai, 2, ',', '.');
        $statusBadge = fn ($status) => match ($status) {
            'Aman' => 'badge-active',
            'Menipis' => 'badge-warning',
            default => 'badge-danger',
        };
    @endphp

    <div class="page-header">
        <div>
            <p class="eyebrow">Sarana Prasarana</p>
            <h1 class="page-title">Laporan inventaris bulanan</h1>
            <p class="help-text" style="margin-top: 6px;">Rekap operasional inventaris untuk {{ $labelPeriode }}.</p>
        </div>

        <div class="actions">
            <a href="{{ route('dashboard-sarana-prasarana.index') }}" class="button button-muted">Dashboard</a>
            <a href="{{ route('laporan-inventaris-bulanan.cetak', request()->query()) }}" class="button button-primary" target="_blank" rel="noopener">Cetak laporan</a>
        </div>
    </div>

    <form action="{{ route('laporan-inventaris-bulanan.index') }}" method="GET" class="panel panel-pad" style="margin-bottom: 24px;">
        <div class="inventory-report-filter-grid">
            <div class="field inventory-report-period">
                <label for="periode">Bulan laporan</label>
                <input id="periode" name="periode" type="month" value="{{ $periode }}" class="input">
            </div>

            <div class="field inventory-report-location">
                <label for="lokasi_barang_id">Lokasi barang</label>
                <select id="lokasi_barang_id" name="lokasi_barang_id" class="select">
                    <option value="">Semua lokasi</option>
                    @foreach ($daftarLokasi as $item)
                        <option value="{{ $item->id }}" @selected((string) $lokasi_barang_id === (string) $item->id)>{{ $item->nama }}</option>
                    @endforeach
                </select>
            </div>

            <div class="inventory-report-actions">
                <button type="submit" class="button button-dark">Terapkan</button>
                <a href="{{ route('laporan-inventaris-bulanan.index') }}" class="button button-muted">Reset</a>
            </div>
        </div>
    </form>

    <section class="stats-grid">
        <article class="panel stat">
            <p class="stat-label">Baris stok</p>
            <p class="stat-value">{{ $ringkasan['baris_stok'] }}</p>
        </article>
        <article class="panel stat active">
            <p class="stat-label">Mutasi bulan ini</p>
            <p class="stat-value">{{ $ringkasan['jumlah_mutasi'] }}</p>
        </article>
        <article class="panel stat inactive">
            <p class="stat-label">Stok perlu perhatian</p>
            <p class="stat-value">{{ $ringkasan['stok_menipis'] + $ringkasan['stok_habis'] }}</p>
            <p class="person-meta">{{ $ringkasan['stok_habis'] }} habis, {{ $ringkasan['stok_menipis'] }} menipis.</p>
        </article>
        <article class="panel stat">
            <p class="stat-label">Unit aset aktif</p>
            <p class="stat-value">{{ $ringkasan['unit_aset'] }}</p>
            <p class="person-meta">{{ $ringkasan['unit_diperoleh'] }} diperoleh pada periode ini.</p>
        </article>
        <article class="panel stat inactive">
            <p class="stat-label">Unit perlu perhatian</p>
            <p class="stat-value">{{ $ringkasan['unit_perlu_perhatian'] }}</p>
        </article>
    </section>

    @if ($barangStokBelumDicatat->isNotEmpty())
        <div class="inventory-report-note">
            <strong>{{ $ringkasan['stok_belum_dicatat'] }} barang stok belum memiliki saldo awal.</strong>
            Catat saldo awal agar laporan inventaris lebih lengkap.
            <div class="inventory-unrecorded-list">
                @foreach ($barangStokBelumDicatat as $item)
                    <span>{{ $item->nama }}</span>
                @endforeach
            </div>
        </div>
    @endif

    <section class="panel">
        <div class="inventory-report-head">
            <div>
                <h2 class="panel-title">Penandatangan Laporan</h2>
                <p class="help-text">Nama otomatis mengikuti role akun pegawai aktif pada halaman Akun Pegawai.</p>
            </div>
        </div>
        <div class="inventory-signatory-grid">
            @foreach ([
                'wakil_sarpras' => 'Wakil Kepala Sekolah Bidang Sarpras',
                'petugas_inventaris' => 'Petugas Inventaris',
                'kepala_sekolah' => 'Kepala Sekolah',
            ] as $kode => $label)
                @php
                    $pegawaiPenandatangan = $penandatangan[$kode];
                @endphp
                <div class="inventory-signatory-item">
                    <p class="person-meta">{{ $label }}</p>
                    <p class="person-name">{{ $pegawaiPenandatangan?->nama_lengkap ?: 'Belum ditentukan' }}</p>
                    <p class="person-meta">NIP {{ $pegawaiPenandatangan?->nip ?: '-' }}</p>
                </div>
            @endforeach
        </div>
    </section>

    <section class="panel inventory-report-section">
        <div class="inventory-report-head">
            <div>
                <h2 class="panel-title">Rekap Stok Bulanan</h2>
                <p class="help-text">Saldo awal, pergerakan, dan saldo akhir setiap barang berbasis jumlah.</p>
            </div>
            <a href="{{ route('mutasi-stok-barang.index', ['tanggal_mulai' => $awalPeriode->toDateString(), 'tanggal_selesai' => $akhirPeriode->toDateString(), 'lokasi_barang_id' => $lokasi_barang_id ?: 'semua']) }}" class="button button-muted button-sm">Buka riwayat mutasi</a>
        </div>

        <div class="desktop-only table-wrap">
            <table class="employee-table" style="min-width: 1020px;">
                <thead>
                    <tr>
                        <th>Barang</th>
                        <th>Lokasi</th>
                        <th>Saldo awal</th>
                        <th>Masuk</th>
                        <th>Keluar</th>
                        <th>Penyesuaian</th>
                        <th>Saldo akhir</th>
                        <th>Status</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($rekapStok as $item)
                        @php
                            $saldo = $item['saldo'];
                            $satuan = $saldo->barang->satuanBarang->nama;
                        @endphp
                        <tr class="{{ $item['status'] === 'Habis' ? 'inventory-report-danger-row' : ($item['status'] === 'Menipis' ? 'inventory-report-warning-row' : '') }}">
                            <td>
                                <p class="person-name">{{ $saldo->barang->nama }}</p>
                                <p class="person-meta">{{ $saldo->barang->kode }} - {{ $item['jumlah_mutasi'] }} mutasi</p>
                            </td>
                            <td>{{ $saldo->lokasiBarang->nama }}</td>
                            <td>{{ $formatJumlah($item['saldo_awal']) }} {{ $satuan }}</td>
                            <td>{{ $formatJumlah($item['stok_masuk']) }} {{ $satuan }}</td>
                            <td>{{ $formatJumlah($item['stok_keluar']) }} {{ $satuan }}</td>
                            <td>{{ $item['penyesuaian'] > 0 ? '+' : '' }}{{ $formatJumlah($item['penyesuaian']) }} {{ $satuan }}</td>
                            <td><strong>{{ $formatJumlah($item['saldo_akhir']) }} {{ $satuan }}</strong></td>
                            <td><span class="badge {{ $statusBadge($item['status']) }}">{{ $item['status'] }}</span></td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="8" class="empty-state">Belum ada saldo stok pada pilihan laporan ini.</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        <div class="mobile-only mobile-list">
            @forelse ($rekapStok as $item)
                @php
                    $saldo = $item['saldo'];
                    $satuan = $saldo->barang->satuanBarang->nama;
                @endphp
                <article class="mobile-card">
                    <div class="mobile-card-head">
                        <div>
                            <p class="person-name">{{ $saldo->barang->nama }}</p>
                            <p class="person-meta">{{ $saldo->barang->kode }} - {{ $saldo->lokasiBarang->nama }}</p>
                        </div>
                        <span class="badge {{ $statusBadge($item['status']) }}">{{ $item['status'] }}</span>
                    </div>
                    <dl class="quick-facts">
                        <div><dt>Saldo awal</dt><dd>{{ $formatJumlah($item['saldo_awal']) }} {{ $satuan }}</dd></div>
                        <div><dt>Masuk</dt><dd>{{ $formatJumlah($item['stok_masuk']) }} {{ $satuan }}</dd></div>
                        <div><dt>Keluar</dt><dd>{{ $formatJumlah($item['stok_keluar']) }} {{ $satuan }}</dd></div>
                        <div><dt>Penyesuaian</dt><dd>{{ $item['penyesuaian'] > 0 ? '+' : '' }}{{ $formatJumlah($item['penyesuaian']) }} {{ $satuan }}</dd></div>
                        <div><dt>Saldo akhir</dt><dd>{{ $formatJumlah($item['saldo_akhir']) }} {{ $satuan }}</dd></div>
                    </dl>
                </article>
            @empty
                <div class="empty-state">Belum ada saldo stok pada pilihan laporan ini.</div>
            @endforelse
        </div>
    </section>

    <section class="panel inventory-report-section">
        <div class="inventory-report-head">
            <div>
                <h2 class="panel-title">Snapshot Unit Aset</h2>
                <p class="help-text">Kondisi unit aktif saat laporan dibuka. Bagian ini membantu pemeriksaan fisik berkala.</p>
            </div>
            <a href="{{ route('unit-barang.index') }}" class="button button-muted button-sm">Buka unit aset</a>
        </div>

        <div class="inventory-asset-status">
            @foreach ($distribusiStatusUnit as $item)
                <div>
                    <span>{{ $item['label'] }}</span>
                    <strong>{{ $item['jumlah'] }}</strong>
                </div>
            @endforeach
        </div>

        <div class="desktop-only table-wrap">
            <table class="employee-table">
                <thead>
                    <tr>
                        <th>Kode inventaris</th>
                        <th>Barang</th>
                        <th>Lokasi</th>
                        <th>Kondisi</th>
                        <th>Status unit</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($unitPerluPerhatian as $item)
                        <tr class="inventory-report-warning-row">
                            <td><strong>{{ $item->kode_inventaris }}</strong></td>
                            <td>{{ $item->barang->nama }}</td>
                            <td>{{ $item->lokasiBarang?->nama ?: '-' }}</td>
                            <td>{{ $item->labelKondisi() }}</td>
                            <td>{{ $item->labelStatus() }}</td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="5" class="empty-state">Tidak ada unit aset yang perlu perhatian.</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        <div class="mobile-only mobile-list">
            @forelse ($unitPerluPerhatian as $item)
                <article class="mobile-card">
                    <div class="mobile-card-head">
                        <div>
                            <p class="person-name">{{ $item->barang->nama }}</p>
                            <p class="person-meta">{{ $item->kode_inventaris }}</p>
                        </div>
                        <span class="badge badge-warning">{{ $item->labelStatus() }}</span>
                    </div>
                    <dl class="quick-facts">
                        <div><dt>Lokasi</dt><dd>{{ $item->lokasiBarang?->nama ?: '-' }}</dd></div>
                        <div><dt>Kondisi</dt><dd>{{ $item->labelKondisi() }}</dd></div>
                    </dl>
                </article>
            @empty
                <div class="empty-state">Tidak ada unit aset yang perlu perhatian.</div>
            @endforelse
        </div>
    </section>

    <section class="panel inventory-report-section">
        <div class="inventory-report-head">
            <div>
                <h2 class="panel-title">Rincian Mutasi Periode</h2>
                <p class="help-text">Menampilkan hingga 30 transaksi terbaru. Lembar cetak memuat seluruh transaksi pada periode terpilih.</p>
            </div>
        </div>

        <div class="desktop-only table-wrap">
            <table class="employee-table">
                <thead>
                    <tr>
                        <th>Tanggal</th>
                        <th>Barang</th>
                        <th>Lokasi</th>
                        <th>Kategori</th>
                        <th>Perubahan</th>
                        <th>Referensi</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($mutasiPeriode->take(30) as $item)
                        @php
                            $perubahan = (float) $item->jumlah_perubahan;
                        @endphp
                        <tr>
                            <td>{{ $item->tanggal_mutasi->locale('id')->translatedFormat('d M Y') }}</td>
                            <td><strong>{{ $item->barang->nama }}</strong></td>
                            <td>{{ $item->lokasiBarang->nama }}</td>
                            <td>{{ $item->labelKategori() }}</td>
                            <td><strong>{{ $perubahan > 0 ? '+' : '' }}{{ $formatJumlah($perubahan) }} {{ $item->barang->satuanBarang->nama }}</strong></td>
                            <td>{{ $item->referensi ?: '-' }}</td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="6" class="empty-state">Belum ada mutasi stok pada periode terpilih.</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        <div class="mobile-only mobile-list">
            @forelse ($mutasiPeriode->take(30) as $item)
                @php
                    $perubahan = (float) $item->jumlah_perubahan;
                @endphp
                <article class="mobile-card">
                    <div class="mobile-card-head">
                        <div>
                            <p class="person-name">{{ $item->barang->nama }}</p>
                            <p class="person-meta">{{ $item->tanggal_mutasi->locale('id')->translatedFormat('d M Y') }} - {{ $item->lokasiBarang->nama }}</p>
                        </div>
                        <span class="badge {{ $perubahan > 0 ? 'badge-active' : 'badge-inactive' }}">{{ $perubahan > 0 ? '+' : '' }}{{ $formatJumlah($perubahan) }}</span>
                    </div>
                    <p class="person-meta" style="margin-top: 10px;">{{ $item->labelKategori() }} - {{ $item->referensi ?: 'Tanpa referensi' }}</p>
                </article>
            @empty
                <div class="empty-state">Belum ada mutasi stok pada periode terpilih.</div>
            @endforelse
        </div>
    </section>
@endsection
