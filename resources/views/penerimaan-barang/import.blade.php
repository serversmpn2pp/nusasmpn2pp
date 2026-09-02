@extends('layouts.app')

@section('title', 'Import Barang Datang - NUSA')

@section('content')
    <style>
        .import-flow {
            display: grid;
            grid-template-columns: repeat(3, minmax(0, 1fr));
            gap: 1px;
            overflow: hidden;
            margin-bottom: 22px;
            border: 1px solid var(--line);
            border-radius: 8px;
            background: var(--line);
        }

        .import-flow-item {
            display: grid;
            grid-template-columns: 36px minmax(0, 1fr);
            gap: 11px;
            align-items: center;
            background: #fff;
            padding: 15px;
        }

        .import-flow-number {
            display: grid;
            width: 36px;
            height: 36px;
            place-items: center;
            border-radius: 50%;
            background: var(--primary);
            color: #fff;
            font-weight: 800;
        }

        .import-flow-item strong,
        .import-flow-item span {
            display: block;
        }

        .import-flow-item span {
            margin-top: 2px;
            color: var(--muted);
            font-size: .8rem;
        }

        .import-upload-shell {
            width: min(760px, 100%);
            margin-inline: auto;
        }

        @media (max-width: 720px) {
            .import-flow {
                grid-template-columns: 1fr;
            }
        }
    </style>

    <div class="page-header">
        <div>
            <p class="eyebrow">Sarana Prasarana</p>
            <h1 class="page-title">Import barang datang</h1>
            <p class="page-subtitle">Periksa seluruh data sebelum stok dan aset disimpan ke NUSA.</p>
        </div>

        <div class="actions">
            <a href="{{ route('penerimaan-barang.import.template') }}" class="button button-primary">Unduh template Excel</a>
            <a href="{{ route('penerimaan-barang.index') }}" class="button button-muted">Kembali</a>
        </div>
    </div>

    <div class="import-flow" aria-label="Tahapan import">
        <div class="import-flow-item">
            <span class="import-flow-number">1</span>
            <div><strong>Isi template</strong><span>Gunakan kode pada Referensi NUSA.</span></div>
        </div>
        <div class="import-flow-item">
            <span class="import-flow-number">2</span>
            <div><strong>Periksa data</strong><span>NUSA menandai kesalahan per baris.</span></div>
        </div>
        <div class="import-flow-item">
            <span class="import-flow-number">3</span>
            <div><strong>Konfirmasi</strong><span>Stok dan unit aset dibuat bersamaan.</span></div>
        </div>
    </div>

    @if ($errors->any())
        <div class="alert alert-danger">
            <strong>Import belum dapat diproses.</strong>
            <ul>
                @foreach ($errors->all() as $error)
                    <li>{{ $error }}</li>
                @endforeach
            </ul>
        </div>
    @endif

    <div class="import-upload-shell section-stack">
        <section class="panel panel-pad">
            <h2 class="panel-title">Ketentuan template</h2>
            <ul style="margin: 12px 0 0; padding-left: 20px; color: var(--muted); line-height: 1.75;">
                <li>Jangan mengganti nama sheet atau judul kolom.</li>
                <li>Barang yang sudah terdaftar cukup diisi menggunakan kode barang pada sheet Referensi NUSA.</li>
                <li>Barang baru wajib dilengkapi dengan nama, jenis, kategori, satuan, dan lokasi.</li>
                <li>Kode barang tidak habis pakai berisi sepuluh angka, misalnya <strong>02.06.01.05.40</strong>. Titik boleh diketik atau tidak.</li>
                <li>Jangan menulis akhiran unit. NUSA membuat <strong>.01, .02, dan seterusnya</strong> saat import disimpan.</li>
                <li>Setiap baris penerimaan baru selalu memulai nomor unit kembali dari <strong>.01</strong>.</li>
            </ul>
        </section>

        <form action="{{ route('penerimaan-barang.import.unggah') }}" method="POST" enctype="multipart/form-data" class="panel panel-pad">
            @csrf

            <div class="field">
                <label for="berkas_excel">Berkas Excel barang datang</label>
                <input id="berkas_excel" name="berkas_excel" type="file" accept=".xlsx" class="file-input" required>
                <p class="help-text">Format .xlsx, maksimal 10 MB, dan maksimal 100 baris barang.</p>
            </div>

            <div class="form-actions" style="margin-top: 20px;">
                <button type="submit" class="button button-primary">Periksa isi Excel</button>
            </div>
        </form>
    </div>
@endsection
