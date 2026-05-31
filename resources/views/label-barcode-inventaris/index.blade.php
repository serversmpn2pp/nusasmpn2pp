@extends('layouts.app')

@section('title', 'Label Barcode Inventaris - NUSA')

@section('content')
    <style>
        .barcode-filter-grid {
            display: grid;
            grid-template-columns: repeat(4, minmax(0, 1fr)) 130px 110px auto;
            gap: 14px;
            align-items: end;
        }

        .barcode-toolbar {
            display: flex;
            flex-wrap: wrap;
            align-items: center;
            justify-content: space-between;
            gap: 14px;
            margin-bottom: 18px;
        }

        .barcode-sheet {
            display: flex;
            flex-wrap: wrap;
            align-items: flex-start;
            gap: 4mm;
        }

        .inventory-label {
            display: grid;
            align-content: center;
            overflow: hidden;
            border: .35mm solid #15477a;
            border-radius: 1.5mm;
            background: #fff;
            color: #0f2942;
            font-family: Arial, sans-serif;
        }

        .inventory-label.label-kecil {
            width: 40mm;
            height: 20mm;
            padding: 1.4mm 1.8mm;
        }

        .inventory-label.label-sedang {
            width: 60mm;
            height: 30mm;
            padding: 2mm 2.5mm;
        }

        .inventory-label.label-besar {
            width: 80mm;
            height: 40mm;
            padding: 2.8mm 3mm;
        }

        .label-brand {
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 2mm;
            color: #15477a;
            font-size: 5.5pt;
            font-weight: 900;
            text-transform: uppercase;
        }

        .label-sedang .label-brand {
            font-size: 7pt;
        }

        .label-besar .label-brand {
            font-size: 8.5pt;
        }

        .label-barcode-svg {
            height: 7mm;
            margin-top: .8mm;
        }

        .label-sedang .label-barcode-svg {
            height: 11mm;
            margin-top: 1.2mm;
        }

        .label-besar .label-barcode-svg {
            height: 15mm;
            margin-top: 1.8mm;
        }

        .label-barcode-svg svg {
            display: block;
            width: 100%;
            height: 100%;
        }

        .label-code {
            margin: .8mm 0 0;
            overflow: hidden;
            color: #0f2942;
            font-size: 5.5pt;
            font-weight: 900;
            letter-spacing: 0;
            line-height: 1;
            text-align: center;
            white-space: nowrap;
        }

        .label-sedang .label-code {
            font-size: 7pt;
        }

        .label-besar .label-code {
            font-size: 8.5pt;
        }

        .label-meta {
            display: flex;
            justify-content: space-between;
            gap: 2mm;
            margin: .8mm 0 0;
            overflow: hidden;
            color: #4b657e;
            font-size: 4.6pt;
            font-weight: 700;
            white-space: nowrap;
        }

        .label-sedang .label-meta {
            font-size: 5.8pt;
        }

        .label-besar .label-meta {
            font-size: 7pt;
        }

        @media (max-width: 1080px) {
            .barcode-filter-grid {
                grid-template-columns: repeat(2, minmax(0, 1fr));
            }
        }

        @media (max-width: 620px) {
            .barcode-filter-grid {
                grid-template-columns: 1fr;
            }
        }

        @media print {
            @page {
                size: A4;
                margin: 8mm;
            }

            body {
                background: #fff !important;
            }

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

            .barcode-sheet {
                gap: 3mm;
            }

            .inventory-label {
                break-inside: avoid;
                box-shadow: none;
                print-color-adjust: exact;
                -webkit-print-color-adjust: exact;
            }
        }
    </style>

    <div class="page-header">
        <div>
            <p class="eyebrow">Sarana Prasarana</p>
            <h1 class="page-title">Label barcode inventaris</h1>
        </div>

        <div class="actions">
            <a href="{{ route('unit-barang.index') }}" class="button button-muted">Unit aset</a>
            <a href="{{ route('saldo-stok-barang.index') }}" class="button button-muted">Saldo stok</a>
            <button type="button" class="button button-primary" onclick="window.print()">Cetak label</button>
        </div>
    </div>

    <form action="{{ route('label-barcode-inventaris.index') }}" method="GET" class="panel panel-pad print-hidden" style="margin-bottom: 24px;">
        @foreach ($unitBarangIds as $unitBarangId)
            <input type="hidden" name="unit_barang_id[]" value="{{ $unitBarangId }}">
        @endforeach

        <div class="barcode-filter-grid">
            <div class="field">
                <label for="jenis_label">Jenis label</label>
                <select id="jenis_label" name="jenis_label" class="select">
                    <option value="unit" @selected($jenisLabel === 'unit')>Unit aset individual</option>
                    <option value="stok" @selected($jenisLabel === 'stok')>Barang stok</option>
                </select>
            </div>

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
                    <option value="">{{ $jenisLabel === 'unit' ? 'Semua aset individual' : 'Semua barang stok' }}</option>
                    @foreach ($daftarBarang as $item)
                        <option value="{{ $item->id }}" @selected((string) $barangId === (string) $item->id)>{{ $item->nama }}</option>
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

            <div class="field">
                <label for="ukuran">Ukuran label</label>
                <select id="ukuran" name="ukuran" class="select">
                    <option value="kecil" @selected($ukuran === 'kecil')>40 x 20 mm</option>
                    <option value="sedang" @selected($ukuran === 'sedang')>60 x 30 mm</option>
                    <option value="besar" @selected($ukuran === 'besar')>80 x 40 mm</option>
                </select>
            </div>

            <div class="field">
                <label for="salinan">Salinan</label>
                <input id="salinan" name="salinan" type="number" min="1" max="20" value="{{ $salinan }}" class="input">
            </div>

            <div class="actions">
                <button type="submit" class="button button-dark">Tampilkan</button>
                <a href="{{ route('label-barcode-inventaris.index') }}" class="button button-muted">Reset</a>
            </div>
        </div>
    </form>

    <div class="barcode-toolbar print-hidden">
        <div>
            <h2 class="panel-title">{{ $labelBarcode->count() }} label siap cetak</h2>
            <p class="help-text" style="margin-top: 4px;">Gunakan kertas label A4 atau potong label mengikuti garis tepinya.</p>
        </div>
        <button type="button" class="button button-primary" onclick="window.print()">Cetak label</button>
    </div>

    @if ($labelBarcode->isEmpty())
        <section class="panel panel-pad print-hidden">
            <h2 class="panel-title">Belum ada label</h2>
            <p class="help-text" style="margin-top: 8px;">Tambahkan {{ $jenisLabel === 'unit' ? 'unit aset individual' : 'saldo barang stok' }} atau ubah pilihan filter untuk menampilkan label.</p>
        </section>
    @else
        <section class="barcode-sheet">
            @foreach ($labelBarcode as $label)
                <article class="inventory-label label-{{ $ukuran }}">
                    <div class="label-brand">
                        <span>NUSA Inventaris</span>
                        <span>SMPN 2 PP</span>
                    </div>
                    <div class="label-barcode-svg">{!! $label['barcode_svg'] !!}</div>
                    <p class="label-code">{{ $label['kode'] }}</p>
                    <p class="label-meta">
                        <span>{{ $label['nama'] }}</span>
                        <span>{{ $label['lokasi'] }}</span>
                    </p>
                </article>
            @endforeach
        </section>
    @endif
@endsection
