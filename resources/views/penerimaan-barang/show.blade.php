@extends('layouts.app')

@section('title', 'Detail Barang Datang - NUSA')

@section('content')
    <style>
        .receipt-result {
            display: grid;
            grid-template-columns: repeat(2, minmax(0, 1fr));
            gap: 8px;
            margin-top: 8px;
        }

        .receipt-code {
            padding: 8px 10px;
            border: 1px solid #d8e2ec;
            border-radius: 5px;
            background: #f8fafc;
            font-size: .85rem;
            overflow-wrap: anywhere;
        }

        @media (max-width: 620px) {
            .receipt-result {
                grid-template-columns: 1fr;
            }
        }
    </style>

    <div class="page-header">
        <div>
            <p class="eyebrow">Sarana Prasarana</p>
            <h1 class="page-title">Detail barang datang</h1>
        </div>
        <div class="actions">
            <a href="{{ route('penerimaan-barang.index') }}" class="button button-muted">Kembali</a>
            <a href="{{ route('label-barcode-inventaris.index', ['penerimaan_barang_id' => $penerimaanBarang->id]) }}" class="button button-muted">Cetak label</a>
            @izin('barang.kelola')
                <a href="{{ route('penerimaan-barang.create') }}" class="button button-primary">Catat lagi</a>
            @endizin
        </div>
    </div>

    @if (session('berhasil'))
        <div class="alert">{{ session('berhasil') }}</div>
    @endif

    <div class="detail-shell">
        <aside class="panel panel-pad">
            <div class="detail-profile">
                <div class="avatar avatar-lg">BD</div>
                <h2>{{ $penerimaanBarang->nomor_penerimaan }}</h2>
                <p>{{ $penerimaanBarang->tanggal_penerimaan->locale('id')->translatedFormat('d F Y') }}</p>
                <div class="actions" style="justify-content:center; margin-top:16px;">
                    <span class="badge badge-active">Sudah masuk inventaris</span>
                </div>
            </div>
        </aside>

        <section class="panel panel-pad">
            <h2 class="panel-title">Informasi penerimaan</h2>
            <dl class="detail-grid" style="margin-top:16px;">
                <div class="detail-item"><dt>Sumber barang</dt><dd>{{ $penerimaanBarang->sumberPerolehanBarang->nama }} {{ $penerimaanBarang->tanggal_penerimaan->year }}</dd></div>
                <div class="detail-item"><dt>Cara perolehan</dt><dd>{{ $penerimaanBarang->labelCaraPerolehan() }}</dd></div>
                <div class="detail-item"><dt>Nomor dokumen</dt><dd>{{ $penerimaanBarang->nomor_dokumen ?: '-' }}</dd></div>
                <div class="detail-item"><dt>Asal/penyedia</dt><dd>{{ $penerimaanBarang->asal_barang ?: '-' }}</dd></div>
                <div class="detail-item"><dt>Dicatat oleh</dt><dd>{{ $penerimaanBarang->dibuatOleh?->nama ?: 'Sistem' }}</dd></div>
                <div class="detail-item"><dt>Nilai tercatat</dt><dd>{{ $penerimaanBarang->nilaiTotal() > 0 ? 'Rp '.number_format($penerimaanBarang->nilaiTotal(), 0, ',', '.') : '-' }}</dd></div>
                <div class="detail-item span-2"><dt>Catatan</dt><dd style="white-space:pre-line;">{{ $penerimaanBarang->catatan ?: '-' }}</dd></div>
            </dl>
        </section>
    </div>

    <section class="panel" style="margin-top:24px;">
        <div class="panel-pad">
            <h2 class="panel-title">Rincian barang</h2>
            <p class="help-text">Hasil pencatatan stok dan unit aset ditampilkan pada setiap barang.</p>
        </div>

        <div class="desktop-only table-wrap">
            <table class="employee-table">
                <thead>
                    <tr>
                        <th>Barang</th>
                        <th>Jenis</th>
                        <th>Lokasi</th>
                        <th>Jumlah</th>
                        <th>Harga satuan</th>
                        <th>Hasil inventaris</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach ($penerimaanBarang->detailPenerimaanBarang as $detail)
                        <tr>
                            <td>
                                <p class="person-name">{{ $detail->barang->nama }}</p>
                                <p class="person-meta">{{ $detail->barang->kode }}</p>
                                @if ($detail->merek || $detail->tipe)
                                    <p class="person-meta">{{ collect([$detail->merek, $detail->tipe])->filter()->join(' - ') }}</p>
                                @endif
                            </td>
                            <td><span class="badge {{ $detail->barang->jenis_barang === 'habis_pakai' ? 'badge-active' : 'badge-inactive' }}">{{ $detail->barang->labelJenisBarang() }}</span></td>
                            <td>{{ $detail->lokasiBarang->nama }}</td>
                            <td><strong>{{ number_format((float) $detail->jumlah, $detail->barang->jenis_barang === 'habis_pakai' ? 2 : 0, ',', '.') }} {{ $detail->barang->jenis_barang === 'habis_pakai' ? $detail->barang->satuanBarang->nama : 'unit' }}</strong></td>
                            <td>{{ $detail->harga_satuan !== null ? 'Rp '.number_format((float) $detail->harga_satuan, 0, ',', '.') : '-' }}</td>
                            <td>
                                @if ($detail->mutasiStokBarang)
                                    <a href="{{ route('mutasi-stok-barang.show', $detail->mutasiStokBarang) }}" class="button button-muted">Lihat stok masuk</a>
                                @else
                                    <strong>{{ $detail->unitBarang->count() }} unit dibuat</strong>
                                    <div class="receipt-result">
                                        @foreach ($detail->unitBarang as $unit)
                                            <a href="{{ route('unit-barang.show', $unit) }}" class="receipt-code">{{ $unit->kode_inventaris }}</a>
                                        @endforeach
                                    </div>
                                @endif
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>

        <div class="mobile-only mobile-list">
            @foreach ($penerimaanBarang->detailPenerimaanBarang as $detail)
                <article class="mobile-card">
                    <div class="mobile-card-head">
                        <div><p class="person-name">{{ $detail->barang->nama }}</p><p class="person-meta">{{ $detail->barang->kode }}</p></div>
                        <span class="badge {{ $detail->barang->jenis_barang === 'habis_pakai' ? 'badge-active' : 'badge-inactive' }}">{{ $detail->barang->jenis_barang === 'habis_pakai' ? 'Habis pakai' : 'Aset' }}</span>
                    </div>
                    <dl class="quick-facts">
                        <div><dt>Lokasi</dt><dd>{{ $detail->lokasiBarang->nama }}</dd></div>
                        <div><dt>Jumlah</dt><dd>{{ number_format((float) $detail->jumlah, $detail->barang->jenis_barang === 'habis_pakai' ? 2 : 0, ',', '.') }} {{ $detail->barang->jenis_barang === 'habis_pakai' ? $detail->barang->satuanBarang->nama : 'unit' }}</dd></div>
                        <div><dt>Harga</dt><dd>{{ $detail->harga_satuan !== null ? 'Rp '.number_format((float) $detail->harga_satuan, 0, ',', '.') : '-' }}</dd></div>
                    </dl>
                    <div style="margin-top:14px;">
                        @if ($detail->mutasiStokBarang)
                            <a href="{{ route('mutasi-stok-barang.show', $detail->mutasiStokBarang) }}" class="button button-muted">Lihat stok masuk</a>
                        @else
                            <strong>{{ $detail->unitBarang->count() }} unit dibuat</strong>
                            <div class="receipt-result">
                                @foreach ($detail->unitBarang as $unit)<a href="{{ route('unit-barang.show', $unit) }}" class="receipt-code">{{ $unit->kode_inventaris }}</a>@endforeach
                            </div>
                        @endif
                    </div>
                </article>
            @endforeach
        </div>
    </section>
@endsection
