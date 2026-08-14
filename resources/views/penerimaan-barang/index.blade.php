@extends('layouts.app')

@section('title', 'Barang Datang - NUSA')

@section('content')
    <style>
        .receipt-filter-grid {
            display: grid;
            grid-template-columns: minmax(220px, 1.4fr) minmax(170px, .9fr) 150px 150px auto;
            gap: 14px;
            align-items: end;
        }

        @media (max-width: 1120px) {
            .receipt-filter-grid {
                grid-template-columns: repeat(2, minmax(0, 1fr));
            }
        }

        @media (max-width: 620px) {
            .receipt-filter-grid {
                grid-template-columns: 1fr;
            }
        }
    </style>

    <div class="page-header">
        <div>
            <p class="eyebrow">Sarana Prasarana</p>
            <h1 class="page-title">Barang datang</h1>
            <p class="page-subtitle">Catat penerimaan barang dan perbarui stok atau unit aset secara otomatis.</p>
        </div>

        @izin('barang.kelola')
            <div class="actions">
                <a href="{{ route('penerimaan-barang.import.create') }}" class="button button-muted">Import Excel</a>
                <a href="{{ route('penerimaan-barang.create') }}" class="button button-primary">Catat barang datang</a>
            </div>
        @endizin
    </div>

    <div class="stats-grid">
        <div class="panel stat">
            <p class="stat-label">Total penerimaan</p>
            <p class="stat-value">{{ $jumlahPenerimaan }}</p>
        </div>
        <div class="panel stat active">
            <p class="stat-label">Penerimaan hari ini</p>
            <p class="stat-value">{{ $jumlahHariIni }}</p>
        </div>
        <div class="panel stat active">
            <p class="stat-label">Unit aset dibuat</p>
            <p class="stat-value">{{ $jumlahUnitDibuat }}</p>
        </div>
        <div class="panel stat">
            <p class="stat-label">Baris stok masuk</p>
            <p class="stat-value">{{ $jumlahJenisStokMasuk }}</p>
        </div>
    </div>

    @if (session('berhasil'))
        <div class="alert">{{ session('berhasil') }}</div>
    @endif

    <form id="filter-penerimaan" action="{{ route('penerimaan-barang.index') }}" method="GET" class="panel panel-pad" style="margin-bottom: 24px;">
        <div class="receipt-filter-grid">
            <div class="field">
                <label for="kata_kunci">Cari penerimaan</label>
                <input id="kata_kunci" name="kata_kunci" type="search" value="{{ $kataKunci }}" placeholder="Nomor, dokumen, barang, atau asal" class="input">
            </div>

            <div class="field">
                <label for="sumber_perolehan_barang_id">Sumber</label>
                <select id="sumber_perolehan_barang_id" name="sumber_perolehan_barang_id" class="select" data-auto-filter>
                    <option value="semua">Semua sumber</option>
                    @foreach ($daftarSumberPerolehan as $sumber)
                        <option value="{{ $sumber->id }}" @selected((string) $sumberPerolehanId === (string) $sumber->id)>{{ $sumber->nama }}</option>
                    @endforeach
                </select>
            </div>

            <div class="field">
                <label for="tanggal_mulai">Dari tanggal</label>
                <input id="tanggal_mulai" name="tanggal_mulai" type="date" value="{{ $tanggalMulai }}" class="input" data-auto-filter>
            </div>

            <div class="field">
                <label for="tanggal_selesai">Sampai tanggal</label>
                <input id="tanggal_selesai" name="tanggal_selesai" type="date" value="{{ $tanggalSelesai }}" class="input" data-auto-filter>
            </div>

            <a href="{{ route('penerimaan-barang.index') }}" class="button button-muted">Reset</a>
        </div>
    </form>

    <section class="panel">
        <div class="desktop-only table-wrap">
            <table class="employee-table">
                <thead>
                    <tr>
                        <th>Nomor</th>
                        <th>Tanggal</th>
                        <th>Sumber</th>
                        <th>Asal/dokumen</th>
                        <th>Barang</th>
                        <th>Dicatat oleh</th>
                        <th class="text-right">Aksi</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($penerimaanBarang as $item)
                        <tr>
                            <td><strong>{{ $item->nomor_penerimaan }}</strong></td>
                            <td>{{ $item->tanggal_penerimaan->locale('id')->translatedFormat('d M Y') }}</td>
                            <td>
                                <span class="badge badge-active">{{ $item->sumberPerolehanBarang->nama }}</span>
                                <p class="person-meta" style="margin-top: 4px;">{{ $item->labelCaraPerolehan() }}</p>
                            </td>
                            <td>
                                <p class="person-name">{{ $item->asal_barang ?: '-' }}</p>
                                <p class="person-meta">{{ $item->nomor_dokumen ?: 'Tanpa nomor dokumen' }}</p>
                            </td>
                            <td>{{ $item->detail_penerimaan_barang_count }} jenis</td>
                            <td>{{ $item->dibuatOleh?->nama ?: 'Sistem' }}</td>
                            <td>
                                <div class="actions" style="justify-content: flex-end;">
                                    <a href="{{ route('penerimaan-barang.show', $item) }}" class="button button-muted">Lihat</a>
                                </div>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="7" class="empty-state">Belum ada barang datang yang dicatat.</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        <div class="mobile-only mobile-list">
            @forelse ($penerimaanBarang as $item)
                <article class="mobile-card">
                    <div class="mobile-card-head">
                        <div>
                            <p class="person-name">{{ $item->nomor_penerimaan }}</p>
                            <p class="person-meta">{{ $item->tanggal_penerimaan->locale('id')->translatedFormat('d M Y') }}</p>
                        </div>
                        <span class="badge badge-active">{{ $item->sumberPerolehanBarang->nama }}</span>
                    </div>

                    <dl class="quick-facts">
                        <div><dt>Cara</dt><dd>{{ $item->labelCaraPerolehan() }}</dd></div>
                        <div><dt>Barang</dt><dd>{{ $item->detail_penerimaan_barang_count }} jenis</dd></div>
                        <div><dt>Asal</dt><dd>{{ $item->asal_barang ?: '-' }}</dd></div>
                    </dl>

                    <div class="actions" style="margin-top: 14px;">
                        <a href="{{ route('penerimaan-barang.show', $item) }}" class="button button-muted">Lihat detail</a>
                    </div>
                </article>
            @empty
                <div class="empty-state">Belum ada barang datang yang dicatat.</div>
            @endforelse
        </div>
    </section>

    @if ($penerimaanBarang->hasPages())
        <nav class="pagination-simple">
            <div>Halaman {{ $penerimaanBarang->currentPage() }} dari {{ $penerimaanBarang->lastPage() }}</div>
            <div class="actions">
                @if ($penerimaanBarang->onFirstPage())
                    <span class="button button-muted" aria-disabled="true">Sebelumnya</span>
                @else
                    <a href="{{ $penerimaanBarang->previousPageUrl() }}" class="button button-muted">Sebelumnya</a>
                @endif
                @if ($penerimaanBarang->hasMorePages())
                    <a href="{{ $penerimaanBarang->nextPageUrl() }}" class="button button-muted">Berikutnya</a>
                @else
                    <span class="button button-muted" aria-disabled="true">Berikutnya</span>
                @endif
            </div>
        </nav>
    @endif
@endsection

@push('scripts')
    <script>
        document.addEventListener('DOMContentLoaded', () => {
            const form = document.getElementById('filter-penerimaan');
            let pencarianTimer;

            form.querySelectorAll('[data-auto-filter]').forEach((field) => {
                field.addEventListener('change', () => form.requestSubmit());
            });

            document.getElementById('kata_kunci').addEventListener('input', () => {
                window.clearTimeout(pencarianTimer);
                pencarianTimer = window.setTimeout(() => form.requestSubmit(), 450);
            });
        });
    </script>
@endpush
