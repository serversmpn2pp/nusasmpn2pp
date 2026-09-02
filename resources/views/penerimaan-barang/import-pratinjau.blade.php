@extends('layouts.app')

@section('title', 'Pratinjau Import Barang Datang - NUSA')

@section('content')
    <style>
        .import-preview-stats {
            display: grid;
            grid-template-columns: repeat(4, minmax(0, 1fr));
            gap: 12px;
            margin-bottom: 22px;
        }

        .import-preview-info {
            display: grid;
            grid-template-columns: repeat(3, minmax(0, 1fr));
            gap: 14px 22px;
        }

        .import-preview-info div {
            min-width: 0;
        }

        .import-preview-info span,
        .import-preview-info strong {
            display: block;
        }

        .import-preview-info span {
            color: var(--muted);
            font-size: .78rem;
            font-weight: 700;
        }

        .import-preview-info strong {
            margin-top: 4px;
            overflow-wrap: anywhere;
        }

        .import-row-errors {
            margin: 8px 0 0;
            padding-left: 18px;
            color: var(--danger);
            font-size: .8rem;
        }

        .import-preview-table {
            min-width: 1050px;
        }

        @media (max-width: 820px) {
            .import-preview-stats,
            .import-preview-info {
                grid-template-columns: repeat(2, minmax(0, 1fr));
            }
        }

        @media (max-width: 560px) {
            .import-preview-stats,
            .import-preview-info {
                grid-template-columns: 1fr;
            }
        }
    </style>

    <div class="page-header">
        <div>
            <p class="eyebrow">Sarana Prasarana</p>
            <h1 class="page-title">Pratinjau import</h1>
            <p class="page-subtitle">Belum ada data yang disimpan. Periksa hasil pembacaan terlebih dahulu.</p>
        </div>

        <a href="{{ route('penerimaan-barang.import.create') }}" class="button button-muted">Unggah ulang</a>
    </div>

    <div class="import-preview-stats">
        <div class="panel stat">
            <p class="stat-label">Baris terbaca</p>
            <p class="stat-value">{{ $pratinjau['jumlah_baris'] }}</p>
        </div>
        <div class="panel stat active">
            <p class="stat-label">Barang terdaftar</p>
            <p class="stat-value">{{ $pratinjau['jumlah_barang_lama'] }}</p>
        </div>
        <div class="panel stat active">
            <p class="stat-label">Barang baru</p>
            <p class="stat-value">{{ $pratinjau['jumlah_barang_baru'] }}</p>
        </div>
        <div class="panel stat {{ $pratinjau['jumlah_kesalahan'] > 0 ? 'inactive' : '' }}">
            <p class="stat-label">Kesalahan</p>
            <p class="stat-value">{{ $pratinjau['jumlah_kesalahan'] }}</p>
        </div>
    </div>

    @if ($errors->any())
        <div class="alert alert-danger">
            <strong>Import belum dapat disimpan.</strong>
            <ul>
                @foreach ($errors->all() as $error)
                    <li>{{ $error }}</li>
                @endforeach
            </ul>
        </div>
    @endif

    @if ($pratinjau['kesalahan_umum'])
        <div class="alert alert-danger">
            <strong>Informasi penerimaan perlu diperbaiki.</strong>
            <ul>
                @foreach ($pratinjau['kesalahan_umum'] as $kesalahan)
                    <li>{{ $kesalahan }}</li>
                @endforeach
            </ul>
        </div>
    @elseif ($pratinjau['valid'])
        <div class="alert">Semua data sudah valid. Konfirmasi untuk menyimpan penerimaan, memperbarui stok, dan membuat unit aset.</div>
    @endif

    <div class="section-stack">
        <section class="panel panel-pad">
            <div class="page-header" style="margin-bottom: 16px;">
                <h2 class="panel-title">Informasi penerimaan</h2>
                <span class="badge {{ $pratinjau['kesalahan_umum'] ? 'badge-warning' : 'badge-active' }}">{{ $pratinjau['kesalahan_umum'] ? 'Perlu diperbaiki' : 'Valid' }}</span>
            </div>

            <div class="import-preview-info">
                <div><span>Tanggal</span><strong>{{ $pratinjau['informasi']['tanggal_penerimaan'] ?: '-' }}</strong></div>
                <div><span>Sumber</span><strong>{{ $pratinjau['informasi']['sumber_perolehan'] }}</strong></div>
                <div><span>Cara perolehan</span><strong>{{ $pratinjau['informasi']['label_cara_perolehan'] }}</strong></div>
                <div><span>Nomor dokumen</span><strong>{{ $pratinjau['informasi']['nomor_dokumen'] ?: '-' }}</strong></div>
                <div><span>Asal barang</span><strong>{{ $pratinjau['informasi']['asal_barang'] ?: '-' }}</strong></div>
                <div><span>Catatan</span><strong>{{ $pratinjau['informasi']['catatan'] ?: '-' }}</strong></div>
            </div>
        </section>

        <section class="panel">
            <div class="panel-pad" style="border-bottom: 1px solid var(--line);">
                <div class="page-header" style="margin-bottom: 0;">
                    <div>
                        <h2 class="panel-title">Rincian barang</h2>
                        <p class="help-text">Barang baru dibuat saat dikonfirmasi. Nomor unit setiap baris dimulai kembali dari .01.</p>
                    </div>
                    <span class="badge badge-muted">{{ $pratinjau['total_unit_aset'] }} unit aset</span>
                </div>
            </div>

            <div class="table-wrap">
                <table class="employee-table import-preview-table">
                    <thead>
                        <tr>
                            <th>Baris</th>
                            <th>Barang</th>
                            <th>Jenis</th>
                            <th>Lokasi</th>
                            <th>Jumlah</th>
                            <th>Harga</th>
                            <th>Kondisi</th>
                            <th>Status</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($pratinjau['rincian'] as $item)
                            <tr>
                                <td>{{ $item['nomor_baris'] }}</td>
                                <td>
                                    <p class="person-name">{{ $item['nama_barang'] }}</p>
                                    <p class="person-meta">{{ $item['kode_barang'] }}</p>
                                    @if ($item['kesalahan'])
                                        <ul class="import-row-errors">
                                            @foreach ($item['kesalahan'] as $kesalahan)
                                                <li>{{ $kesalahan }}</li>
                                            @endforeach
                                        </ul>
                                    @endif
                                </td>
                                <td>
                                    {{ $item['label_jenis_barang'] }}
                                    <p class="person-meta">{{ $item['kategori'] }} &middot; {{ $item['satuan'] }}</p>
                                </td>
                                <td>{{ $item['lokasi'] }}</td>
                                <td>{{ $item['jumlah'] !== null ? number_format($item['jumlah'], 2, ',', '.') : '-' }}</td>
                                <td>{{ $item['harga_satuan'] !== null ? 'Rp '.number_format($item['harga_satuan'], 0, ',', '.') : '-' }}</td>
                                <td>{{ $item['label_kondisi'] }}</td>
                                <td>
                                    @if ($item['kesalahan'])
                                        <span class="badge badge-inactive">Perlu diperbaiki</span>
                                    @elseif ($item['status_barang'] === 'baru')
                                        <span class="badge badge-warning">Barang baru</span>
                                    @else
                                        <span class="badge badge-active">Sudah terdaftar</span>
                                    @endif
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </section>

        <div class="form-actions">
            <a href="{{ route('penerimaan-barang.import.create') }}" class="button button-muted">Batal dan unggah ulang</a>

            @if ($pratinjau['valid'])
                <form action="{{ route('penerimaan-barang.import.konfirmasi') }}" method="POST" onsubmit="this.querySelector('button').disabled = true; this.querySelector('button').textContent = 'Menyimpan...';">
                    @csrf
                    <input type="hidden" name="token_import" value="{{ $token }}">
                    <button type="submit" class="button button-primary">Simpan import barang datang</button>
                </form>
            @else
                <button type="button" class="button button-muted" disabled>Perbaiki Excel terlebih dahulu</button>
            @endif
        </div>
    </div>
@endsection
