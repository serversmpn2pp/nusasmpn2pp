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
            display: block;
            padding: 8px 10px;
            border: 1px solid #d8e2ec;
            border-radius: 5px;
            background: #f8fafc;
            font-size: .85rem;
            overflow-wrap: anywhere;
        }

        .receipt-code strong,
        .receipt-code small {
            display: block;
        }

        .receipt-code small {
            margin-top: 3px;
            color: var(--muted);
        }

        .receipt-cancel-dialog {
            width: min(520px, calc(100vw - 32px));
            padding: 0;
            border: 0;
            border-radius: 8px;
            box-shadow: 0 24px 64px rgba(15, 23, 42, .24);
        }

        .receipt-cancel-dialog::backdrop {
            background: rgba(15, 23, 42, .55);
        }

        .receipt-cancel-head,
        .receipt-cancel-body,
        .receipt-cancel-foot {
            padding: 20px;
        }

        .receipt-cancel-head {
            border-bottom: 1px solid var(--line);
        }

        .receipt-cancel-head h2,
        .receipt-cancel-head p {
            margin: 0;
        }

        .receipt-cancel-head p {
            margin-top: 6px;
            color: var(--muted);
        }

        .receipt-cancel-body {
            display: grid;
            gap: 16px;
        }

        .receipt-cancel-warning {
            padding: 12px 14px;
            border-left: 3px solid var(--danger);
            background: #fef2f2;
            color: #991b1b;
            line-height: 1.55;
        }

        .receipt-cancel-check {
            display: flex;
            align-items: flex-start;
            gap: 10px;
            line-height: 1.45;
        }

        .receipt-cancel-check input {
            width: 18px;
            height: 18px;
            margin-top: 2px;
            flex: 0 0 auto;
        }

        .receipt-cancel-foot {
            display: flex;
            justify-content: flex-end;
            gap: 10px;
            border-top: 1px solid var(--line);
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
            @unless ($penerimaanBarang->sudahDibatalkan())
                <a href="{{ route('label-barcode-inventaris.index', ['penerimaan_barang_id' => $penerimaanBarang->id]) }}" class="button button-muted">Cetak label</a>
            @endunless
            @izin('barang.kelola')
                @unless ($penerimaanBarang->sudahDibatalkan())
                    <button type="button" class="button button-danger" data-open-cancel-receipt>Batalkan penerimaan</button>
                @endunless
                <a href="{{ route('penerimaan-barang.create') }}" class="button button-primary">Catat lagi</a>
            @endizin
        </div>
    </div>

    @if (session('berhasil'))
        <div class="alert">{{ session('berhasil') }}</div>
    @endif

    @if ($errors->any())
        <div class="alert alert-danger">{{ $errors->first() }}</div>
    @endif

    <div class="detail-shell">
        <aside class="panel panel-pad">
            <div class="detail-profile">
                <div class="avatar avatar-lg">BD</div>
                <h2>{{ $penerimaanBarang->nomor_penerimaan }}</h2>
                <p>{{ $penerimaanBarang->tanggal_penerimaan->locale('id')->translatedFormat('d F Y') }}</p>
                <div class="actions" style="justify-content:center; margin-top:16px;">
                    <span class="badge {{ $penerimaanBarang->sudahDibatalkan() ? 'badge-inactive' : 'badge-active' }}">
                        {{ $penerimaanBarang->sudahDibatalkan() ? 'Penerimaan dibatalkan' : 'Sudah masuk inventaris' }}
                    </span>
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
                @if ($penerimaanBarang->sudahDibatalkan())
                    <div class="detail-item"><dt>Dibatalkan oleh</dt><dd>{{ $penerimaanBarang->dibatalkanOleh?->nama ?: 'Sistem' }}</dd></div>
                    <div class="detail-item"><dt>Waktu pembatalan</dt><dd>{{ $penerimaanBarang->dibatalkan_pada?->locale('id')->translatedFormat('d F Y, H:i') }}</dd></div>
                    <div class="detail-item span-2"><dt>Alasan pembatalan</dt><dd style="white-space:pre-line;">{{ $penerimaanBarang->alasan_pembatalan }}</dd></div>
                @endif
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
                                <p class="person-meta">{{ $detail->barang->kodeKlasifikasi() }}</p>
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
                                    <div class="actions">
                                        <a href="{{ route('mutasi-stok-barang.show', $detail->mutasiStokBarang) }}" class="button button-muted">Lihat stok masuk</a>
                                        @if ($detail->mutasiPembatalanStokBarang)
                                            <a href="{{ route('mutasi-stok-barang.show', $detail->mutasiPembatalanStokBarang) }}" class="button button-muted">Lihat koreksi</a>
                                        @endif
                                    </div>
                                @else
                                    <strong>{{ $detail->unitBarang->count() }} unit {{ $penerimaanBarang->sudahDibatalkan() ? 'dinonaktifkan' : 'dibuat' }}</strong>
                                    <div class="receipt-result">
                                        @foreach ($detail->unitBarang as $unit)
                                            <a href="{{ route('unit-barang.show', $unit) }}" class="receipt-code">
                                                <strong>{{ $unit->kodeBarangUnit() }}</strong>
                                                <small>ID NUSA {{ $unit->kode_inventaris }}</small>
                                            </a>
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
                        <div><p class="person-name">{{ $detail->barang->nama }}</p><p class="person-meta">{{ $detail->barang->kodeKlasifikasi() }}</p></div>
                        <span class="badge {{ $detail->barang->jenis_barang === 'habis_pakai' ? 'badge-active' : 'badge-inactive' }}">{{ $detail->barang->jenis_barang === 'habis_pakai' ? 'Habis pakai' : 'Aset' }}</span>
                    </div>
                    <dl class="quick-facts">
                        <div><dt>Lokasi</dt><dd>{{ $detail->lokasiBarang->nama }}</dd></div>
                        <div><dt>Jumlah</dt><dd>{{ number_format((float) $detail->jumlah, $detail->barang->jenis_barang === 'habis_pakai' ? 2 : 0, ',', '.') }} {{ $detail->barang->jenis_barang === 'habis_pakai' ? $detail->barang->satuanBarang->nama : 'unit' }}</dd></div>
                        <div><dt>Harga</dt><dd>{{ $detail->harga_satuan !== null ? 'Rp '.number_format((float) $detail->harga_satuan, 0, ',', '.') : '-' }}</dd></div>
                    </dl>
                    <div style="margin-top:14px;">
                        @if ($detail->mutasiStokBarang)
                            <div class="actions">
                                <a href="{{ route('mutasi-stok-barang.show', $detail->mutasiStokBarang) }}" class="button button-muted">Lihat stok masuk</a>
                                @if ($detail->mutasiPembatalanStokBarang)
                                    <a href="{{ route('mutasi-stok-barang.show', $detail->mutasiPembatalanStokBarang) }}" class="button button-muted">Lihat koreksi</a>
                                @endif
                            </div>
                        @else
                            <strong>{{ $detail->unitBarang->count() }} unit {{ $penerimaanBarang->sudahDibatalkan() ? 'dinonaktifkan' : 'dibuat' }}</strong>
                            <div class="receipt-result">
                                @foreach ($detail->unitBarang as $unit)
                                    <a href="{{ route('unit-barang.show', $unit) }}" class="receipt-code">
                                        <strong>{{ $unit->kodeBarangUnit() }}</strong>
                                        <small>ID NUSA {{ $unit->kode_inventaris }}</small>
                                    </a>
                                @endforeach
                            </div>
                        @endif
                    </div>
                </article>
            @endforeach
        </div>
    </section>

    @izin('barang.kelola')
        @unless ($penerimaanBarang->sudahDibatalkan())
            <dialog class="receipt-cancel-dialog" data-cancel-receipt-dialog>
                <form method="POST" action="{{ route('penerimaan-barang.batalkan', $penerimaanBarang) }}">
                    @csrf
                    @method('PATCH')
                    <div class="receipt-cancel-head">
                        <h2>Batalkan penerimaan</h2>
                        <p>{{ $penerimaanBarang->nomor_penerimaan }}</p>
                    </div>
                    <div class="receipt-cancel-body">
                        <div class="receipt-cancel-warning">
                            Stok barang habis pakai akan dikurangi dan unit aset dari penerimaan ini akan dinonaktifkan. Riwayat penerimaan tetap disimpan.
                        </div>
                        <div class="field">
                            <label for="alasan_pembatalan">Alasan pembatalan</label>
                            <textarea id="alasan_pembatalan" name="alasan_pembatalan" class="textarea" rows="4" minlength="10" maxlength="1000" required placeholder="Contoh: Penerimaan tercatat dua kali akibat pengiriman formulir berulang.">{{ old('alasan_pembatalan') }}</textarea>
                            <p class="help-text">Minimal 10 karakter dan akan tersimpan dalam jejak audit.</p>
                        </div>
                        <label class="receipt-cancel-check">
                            <input type="checkbox" name="konfirmasi_pembatalan" value="1" required @checked(old('konfirmasi_pembatalan'))>
                            <span>Saya sudah memastikan bahwa penerimaan ini memang harus dibatalkan.</span>
                        </label>
                    </div>
                    <div class="receipt-cancel-foot">
                        <button type="button" class="button button-muted" data-close-cancel-receipt>Kembali</button>
                        <button type="submit" class="button button-danger">Ya, batalkan penerimaan</button>
                    </div>
                </form>
            </dialog>
        @endunless
    @endizin
@endsection

@push('scripts')
    <script>
        document.addEventListener('DOMContentLoaded', () => {
            const dialog = document.querySelector('[data-cancel-receipt-dialog]');
            const openButton = document.querySelector('[data-open-cancel-receipt]');
            const closeButton = document.querySelector('[data-close-cancel-receipt]');

            openButton?.addEventListener('click', () => dialog?.showModal());
            closeButton?.addEventListener('click', () => dialog?.close());
            dialog?.addEventListener('click', (event) => {
                if (event.target === dialog) dialog.close();
            });

            @if ($errors->has('alasan_pembatalan') || $errors->has('konfirmasi_pembatalan') || $errors->has('pembatalan'))
                dialog?.showModal();
            @endif
        });
    </script>
@endpush
