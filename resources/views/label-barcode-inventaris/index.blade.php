@extends('layouts.app')

@section('title', 'Label Inventaris - NUSA')

@section('content')
    <style>
        .label-filter-grid {
            display: grid;
            grid-template-columns: repeat(3, minmax(0, 1fr));
            gap: 14px;
        }

        .label-selection-toolbar {
            display: grid;
            grid-template-columns: minmax(160px, .8fr) 120px minmax(0, 1fr) auto;
            gap: 14px;
            align-items: end;
        }

        .label-choice-list {
            display: grid;
            grid-template-columns: repeat(3, minmax(0, 1fr));
            gap: 8px;
            max-height: 320px;
            margin-top: 16px;
            padding: 2px;
            overflow-y: auto;
        }

        .label-choice {
            display: grid;
            grid-template-columns: auto minmax(0, 1fr);
            gap: 10px;
            align-items: start;
            min-width: 0;
            padding: 11px 12px;
            border: 1px solid #d8e2ec;
            border-radius: 6px;
            background: #fff;
            cursor: pointer;
        }

        .label-choice:hover {
            border-color: #15477a;
            background: #f6f9fc;
        }

        .label-choice input {
            width: 17px;
            height: 17px;
            margin-top: 2px;
            accent-color: #15477a;
        }

        .label-choice strong,
        .label-choice small {
            display: block;
            min-width: 0;
            overflow-wrap: anywhere;
        }

        .label-choice small {
            margin-top: 3px;
            color: #617187;
            line-height: 1.35;
        }

        .label-sheet {
            display: flex;
            flex-wrap: wrap;
            align-items: flex-start;
            gap: 3mm;
        }

        .inventory-label {
            display: grid;
            overflow: hidden;
            box-sizing: border-box;
            border: .35mm solid #15477a;
            border-radius: 1.5mm;
            background: #fff;
            color: #0d2d4b;
            font-family: Arial, sans-serif;
            print-color-adjust: exact;
            -webkit-print-color-adjust: exact;
        }

        .inventory-label.label-kecil {
            width: 50mm;
            height: 30mm;
            padding: 1.2mm 1.6mm;
        }

        .inventory-label.label-sedang {
            width: 65mm;
            height: 35mm;
            padding: 1.5mm 2mm;
        }

        .inventory-label.label-besar {
            width: 80mm;
            height: 45mm;
            padding: 2mm 2.6mm;
        }

        .label-heading {
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 2mm;
            padding-bottom: .7mm;
            border-bottom: .25mm solid #f1c40f;
            color: #15477a;
            font-size: 5.2pt;
            font-weight: 900;
            line-height: 1;
            text-transform: uppercase;
            white-space: nowrap;
        }

        .label-heading span:first-child {
            min-width: 0;
            overflow: hidden;
            text-overflow: ellipsis;
        }

        .label-sedang .label-heading { font-size: 6.2pt; }
        .label-besar .label-heading { font-size: 7.4pt; }

        .asset-identity {
            display: grid;
            gap: .25mm;
            margin-top: .7mm;
            font-size: 4.2pt;
            font-weight: 800;
            line-height: 1.05;
            text-align: center;
            white-space: nowrap;
        }

        .label-sedang .asset-identity { gap: .35mm; font-size: 5.2pt; }
        .label-besar .asset-identity { gap: .45mm; font-size: 6.3pt; }

        .label-barcode {
            height: 7mm;
            margin-top: .7mm;
        }

        .label-sedang .label-barcode { height: 9mm; margin-top: .9mm; }
        .label-besar .label-barcode { height: 13mm; margin-top: 1.2mm; }

        .label-barcode svg {
            display: block;
            width: 100%;
            height: 100%;
        }

        .label-code {
            margin: .45mm 0 0;
            overflow: hidden;
            color: #0d2d4b;
            font-size: 4.5pt;
            font-weight: 900;
            line-height: 1;
            text-align: center;
            white-space: nowrap;
        }

        .label-sedang .label-code { font-size: 5.6pt; }
        .label-besar .label-code { font-size: 6.8pt; }

        .stock-name {
            margin: 1.3mm 0 .5mm;
            overflow: hidden;
            font-size: 6pt;
            font-weight: 900;
            line-height: 1.1;
            text-align: center;
            text-overflow: ellipsis;
            white-space: nowrap;
        }

        .label-sedang .stock-name { font-size: 7.4pt; }
        .label-besar .stock-name { font-size: 9pt; }

        .stock-meta {
            display: flex;
            justify-content: space-between;
            gap: 2mm;
            color: #526b83;
            font-size: 4.2pt;
            font-weight: 700;
            white-space: nowrap;
        }

        .label-sedang .stock-meta { font-size: 5.2pt; }
        .label-besar .stock-meta { font-size: 6.3pt; }

        .label-preview-toolbar {
            display: flex;
            flex-wrap: wrap;
            align-items: center;
            justify-content: space-between;
            gap: 14px;
            margin: 24px 0 16px;
        }

        @media (max-width: 1120px) {
            .label-filter-grid,
            .label-choice-list {
                grid-template-columns: repeat(2, minmax(0, 1fr));
            }

            .label-selection-toolbar {
                grid-template-columns: repeat(2, minmax(0, 1fr));
            }
        }

        @media (max-width: 640px) {
            .label-filter-grid,
            .label-choice-list,
            .label-selection-toolbar {
                grid-template-columns: 1fr;
            }

            .label-choice-list {
                max-height: 420px;
            }

            .label-sheet {
                justify-content: center;
            }
        }

        @media print {
            @page {
                size: A4;
                margin: 8mm;
            }

            body { background: #fff !important; }

            .app-sidebar,
            .app-topbar,
            .sidebar-backdrop,
            .page-header,
            .print-hidden {
                display: none !important;
            }

            .app-shell,
            .app-main,
            .app-content {
                display: block !important;
                width: auto !important;
                height: auto !important;
                min-height: 0 !important;
                margin: 0 !important;
                overflow: visible !important;
                padding: 0 !important;
            }

            .label-sheet {
                gap: 3mm;
                justify-content: flex-start;
            }

            .inventory-label {
                break-inside: avoid;
                box-shadow: none;
            }
        }
    </style>

    <div class="page-header">
        <div>
            <p class="eyebrow">Sarana Prasarana</p>
            <h1 class="page-title">Label inventaris</h1>
            <p class="page-subtitle">Pilih aset atau barang habis pakai, tinjau, lalu cetak pada lembar A4.</p>
        </div>

        <div class="actions">
            <a href="{{ route('penerimaan-barang.index') }}" class="button button-muted">Barang datang</a>
            <button type="button" class="button button-primary" onclick="window.print()" @disabled($labelBarcode->isEmpty())>Cetak label</button>
        </div>
    </div>

    <form action="{{ route('label-barcode-inventaris.index') }}" method="GET" class="panel panel-pad print-hidden" style="margin-bottom: 20px;">
        <h2 class="panel-title">Saring sumber label</h2>
        <div class="label-filter-grid" style="margin-top: 16px;">
            <div class="field">
                <label for="jenis_label">Jenis label</label>
                <select id="jenis_label" name="jenis_label" class="select">
                    <option value="unit" @selected($jenisLabel === 'unit')>Barang tidak habis pakai</option>
                    <option value="stok" @selected($jenisLabel === 'stok')>Barang habis pakai</option>
                </select>
            </div>

            <div class="field">
                <label for="penerimaan_barang_id">Transaksi barang datang</label>
                <select id="penerimaan_barang_id" name="penerimaan_barang_id" class="select">
                    <option value="">Semua transaksi</option>
                    @foreach ($daftarPenerimaan as $penerimaan)
                        <option value="{{ $penerimaan->id }}" @selected((string) $penerimaanBarangId === (string) $penerimaan->id)>{{ $penerimaan->nomor_penerimaan }} - {{ $penerimaan->tanggal_penerimaan->format('d-m-Y') }}</option>
                    @endforeach
                </select>
            </div>

            @if ($jenisLabel === 'unit')
                <div class="field">
                    <label for="tahun_perolehan">Tahun perolehan</label>
                    <input id="tahun_perolehan" name="tahun_perolehan" type="number" min="1900" max="2100" value="{{ $tahunPerolehan }}" placeholder="Semua tahun" class="input">
                </div>
            @endif

            <div class="field">
                <label for="kategori_barang_id">Kategori</label>
                <select id="kategori_barang_id" name="kategori_barang_id" class="select">
                    <option value="">Semua kategori</option>
                    @foreach ($daftarKategori as $item)
                        <option value="{{ $item->id }}" @selected((string) $kategoriBarangId === (string) $item->id)>{{ $item->nama }}</option>
                    @endforeach
                </select>
            </div>

            <div class="field">
                <label for="barang_id">Barang</label>
                <select id="barang_id" name="barang_id" class="select">
                    <option value="">Semua barang</option>
                    @foreach ($daftarBarang as $item)
                        <option value="{{ $item->id }}" @selected((string) $barangId === (string) $item->id)>{{ $item->nama }} - {{ $item->kodeKlasifikasi() }}</option>
                    @endforeach
                </select>
            </div>

            <div class="field">
                <label for="lokasi_barang_id">Lokasi</label>
                <select id="lokasi_barang_id" name="lokasi_barang_id" class="select">
                    <option value="">Semua lokasi</option>
                    @foreach ($daftarLokasi as $item)
                        <option value="{{ $item->id }}" @selected((string) $lokasiBarangId === (string) $item->id)>{{ $item->nama }}</option>
                    @endforeach
                </select>
            </div>
        </div>

        <div class="actions" style="margin-top: 16px; justify-content: flex-end;">
            <a href="{{ route('label-barcode-inventaris.index') }}" class="button button-muted">Reset</a>
            <button type="submit" class="button button-dark">Tampilkan</button>
        </div>
    </form>

    <form id="form-seleksi-label" action="{{ route('label-barcode-inventaris.index') }}" method="GET" class="panel panel-pad print-hidden">
        <input type="hidden" name="jenis_label" value="{{ $jenisLabel }}">
        <input type="hidden" name="seleksi" value="1">
        @if ($penerimaanBarangId)<input type="hidden" name="penerimaan_barang_id" value="{{ $penerimaanBarangId }}">@endif
        @if ($tahunPerolehan)<input type="hidden" name="tahun_perolehan" value="{{ $tahunPerolehan }}">@endif
        @if ($kategoriBarangId)<input type="hidden" name="kategori_barang_id" value="{{ $kategoriBarangId }}">@endif
        @if ($barangId)<input type="hidden" name="barang_id" value="{{ $barangId }}">@endif
        @if ($lokasiBarangId)<input type="hidden" name="lokasi_barang_id" value="{{ $lokasiBarangId }}">@endif

        <div class="label-selection-toolbar">
            <div class="field">
                <label for="ukuran">Ukuran label</label>
                <select id="ukuran" name="ukuran" class="select">
                    @foreach ($daftarUkuran as $nilai => $label)
                        <option value="{{ $nilai }}" @selected($ukuran === $nilai)>{{ $label }}</option>
                    @endforeach
                </select>
            </div>
            <div class="field">
                <label for="salinan">Salinan</label>
                <input id="salinan" name="salinan" type="number" min="1" max="20" value="{{ $salinan }}" class="input">
            </div>
            <div>
                <h2 class="panel-title">Pilih yang dicetak</h2>
                <p class="help-text">{{ $jenisLabel === 'unit' ? $daftarPilihanUnit->count() : $daftarPilihanStok->count() }} pilihan tersedia.</p>
            </div>
            <div class="actions">
                <button type="button" class="button button-muted" data-pilih-semua>Pilih semua</button>
                <button type="button" class="button button-muted" data-pilih-nol>Pilih nol</button>
                <button type="submit" class="button button-dark">Perbarui label</button>
            </div>
        </div>

        <div class="label-choice-list">
            @if ($jenisLabel === 'unit')
                @forelse ($daftarPilihanUnit as $unit)
                    <label class="label-choice">
                        <input type="checkbox" name="unit_barang_id[]" value="{{ $unit->id }}" @checked(! $seleksiDiterapkan || in_array($unit->id, $unitBarangIds, true))>
                        <span>
                            <strong>{{ $unit->kodeBarangUnit() }}</strong>
                            <small>{{ $unit->barang->nama }} - {{ $unit->lokasiBarang?->nama ?: 'Tanpa lokasi' }}</small>
                            <small>ID NUSA {{ $unit->kode_inventaris }}</small>
                        </span>
                    </label>
                @empty
                    <div class="empty-state">Tidak ada unit aset yang sesuai dengan filter.</div>
                @endforelse
            @else
                @forelse ($daftarPilihanStok as $saldo)
                    <label class="label-choice">
                        <input type="checkbox" name="saldo_stok_barang_id[]" value="{{ $saldo->id }}" @checked(! $seleksiDiterapkan || in_array($saldo->id, $saldoStokBarangIds, true))>
                        <span>
                            <strong>{{ $saldo->barang->nama }}</strong>
                            <small>{{ $saldo->barang->kodeKlasifikasi() }} - {{ $saldo->lokasiBarang->nama }}</small>
                        </span>
                    </label>
                @empty
                    <div class="empty-state">Tidak ada barang habis pakai yang sesuai dengan filter.</div>
                @endforelse
            @endif
        </div>
    </form>

    <div class="label-preview-toolbar print-hidden">
        <div>
            <h2 class="panel-title">{{ $labelBarcode->count() }} label siap cetak</h2>
            <p class="help-text">Ukuran {{ $daftarUkuran[$ukuran] }}. Gunakan kertas A4 dan skala cetak 100%.</p>
        </div>
        <button type="button" class="button button-primary" onclick="window.print()" @disabled($labelBarcode->isEmpty())>Cetak label</button>
    </div>

    @if ($labelBarcode->isEmpty())
        <section class="panel panel-pad print-hidden">
            <h2 class="panel-title">Belum ada label dipilih</h2>
            <p class="help-text" style="margin-top: 8px;">Pilih minimal satu item, lalu tekan Perbarui label.</p>
        </section>
    @else
        <section class="label-sheet">
            @foreach ($labelBarcode as $label)
                <article class="inventory-label label-{{ $ukuran }} label-{{ $label['jenis'] }}">
                    @if ($label['jenis'] === 'unit')
                        <div class="label-heading">
                            <span>{{ $label['nama'] }}</span>
                            <span>ASET NUSA</span>
                        </div>
                        <div class="asset-identity">
                            <span>{{ $label['nomor_aset_resmi'] }}</span>
                            <span>{{ $label['kode_barang'] }}</span>
                            <span>{{ $label['sumber_tahun'] }}</span>
                            <span>{{ $label['pemilik'] }}</span>
                        </div>
                    @else
                        <div class="label-heading"><span>{{ $label['judul'] }}</span><span>NUSA</span></div>
                        <p class="stock-name">{{ $label['nama'] }}</p>
                        <p class="stock-meta"><span>{{ $label['lokasi'] }}</span><span>{{ $label['satuan'] }}</span></p>
                    @endif
                    <div class="label-barcode">{!! $label['barcode_svg'] !!}</div>
                    <p class="label-code">{{ $label['kode'] }}</p>
                </article>
            @endforeach
        </section>
    @endif
@endsection

@push('scripts')
    <script>
        document.addEventListener('DOMContentLoaded', () => {
            const form = document.getElementById('form-seleksi-label');
            const semuaKotak = () => form.querySelectorAll('input[type="checkbox"]');

            form.querySelector('[data-pilih-semua]').addEventListener('click', () => {
                semuaKotak().forEach((kotak) => kotak.checked = true);
            });
            form.querySelector('[data-pilih-nol]').addEventListener('click', () => {
                semuaKotak().forEach((kotak) => kotak.checked = false);
            });
        });
    </script>
@endpush
