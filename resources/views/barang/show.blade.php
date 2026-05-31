@extends('layouts.app')

@section('title', 'Detail Barang - NUSA')

@section('content')
    <div class="page-header">
        <div>
            <p class="eyebrow">Sarana Prasarana</p>
            <h1 class="page-title">Detail barang</h1>
        </div>

        <div class="actions">
            <a href="{{ route('barang.index') }}" class="button button-muted">Kembali</a>
            @izin('barang.kelola')
                <a href="{{ route('barang.edit', $barang) }}" class="button button-dark">Edit</a>
            @endizin
        </div>
    </div>

    @if (session('berhasil'))
        <div class="alert">{{ session('berhasil') }}</div>
    @endif

    <div class="detail-shell">
        <aside class="panel panel-pad">
            <div class="detail-profile">
                <div class="avatar avatar-lg">IB</div>
                <h2>{{ $barang->nama }}</h2>
                <p>{{ $barang->kode }}</p>

                <div class="actions" style="justify-content: center; margin-top: 16px;">
                    @if ($barang->aktif)
                        <span class="badge badge-active">Aktif</span>
                    @else
                        <span class="badge badge-inactive">Nonaktif</span>
                    @endif
                </div>
            </div>

            @izin('barang.kelola')
                @if ($barang->aktif)
                    <form action="{{ route('barang.destroy', $barang) }}" method="POST" style="margin-top: 24px;" onsubmit="return confirm('Nonaktifkan barang ini?')">
                        @csrf
                        @method('DELETE')
                        <button type="submit" class="button button-danger button-full">Nonaktifkan</button>
                    </form>
                @endif
            @endizin
        </aside>

        <div class="section-stack">
            <section class="panel panel-pad">
                <h2 class="panel-title">Informasi Barang</h2>

                <dl class="detail-grid">
                    <div class="detail-item">
                        <dt>Kode barang</dt>
                        <dd>{{ $barang->kode }}</dd>
                    </div>
                    <div class="detail-item">
                        <dt>Kategori</dt>
                        <dd>{{ $barang->kategoriBarang->nama }}</dd>
                    </div>
                    <div class="detail-item">
                        <dt>Tipe pengelolaan</dt>
                        <dd>{{ $barang->labelTipePengelolaan() }}</dd>
                    </div>
                    <div class="detail-item">
                        <dt>Satuan</dt>
                        <dd>{{ $barang->satuanBarang->nama }}</dd>
                    </div>
                    <div class="detail-item">
                        <dt>Lokasi penyimpanan awal</dt>
                        <dd>{{ $barang->lokasiPenyimpanan?->nama ?: '-' }}</dd>
                    </div>
                    <div class="detail-item">
                        <dt>Stok minimum</dt>
                        <dd>{{ number_format((float) $barang->stok_minimum, 2, ',', '.') }} {{ $barang->satuanBarang->nama }}</dd>
                    </div>
                    @if ($barang->tipe_pengelolaan === 'aset_individual')
                        <div class="detail-item">
                            <dt>Unit aset tercatat</dt>
                            <dd>{{ $barang->unit_barang_count }}</dd>
                        </div>
                    @else
                        <div class="detail-item">
                            <dt>Saldo stok seluruh lokasi</dt>
                            <dd>{{ number_format((float) ($barang->saldo_stok_barang_sum_jumlah ?? 0), 2, ',', '.') }} {{ $barang->satuanBarang->nama }}</dd>
                        </div>
                    @endif
                    <div class="detail-item span-2">
                        <dt>Deskripsi</dt>
                        <dd style="white-space: pre-line;">{{ $barang->deskripsi ?: '-' }}</dd>
                    </div>
                </dl>
            </section>

            <section class="panel panel-pad">
                <h2 class="panel-title">Tahap berikutnya</h2>
                @if ($barang->tipe_pengelolaan === 'aset_individual')
                    <p class="help-text" style="margin-top: 8px;">Setiap aset individual memiliki kode inventaris dan barcode masing-masing agar lokasi, kondisi, dan peminjamnya dapat dilacak.</p>
                    <div class="actions" style="margin-top: 14px;">
                        <a href="{{ route('unit-barang.index', ['barang_id' => $barang->id]) }}" class="button button-muted">Lihat unit aset</a>
                        @izin('barang.kelola')
                            <a href="{{ route('unit-barang.create', ['barang_id' => $barang->id]) }}" class="button button-primary">Tambah unit aset</a>
                        @endizin
                    </div>
                @else
                    <p class="help-text" style="margin-top: 8px;">Saldo barang diperbarui melalui mutasi stok masuk, keluar, atau penyesuaian hasil pemeriksaan fisik.</p>
                    <div class="actions" style="margin-top: 14px;">
                        <a href="{{ route('saldo-stok-barang.index', ['kata_kunci' => $barang->kode]) }}" class="button button-muted">Lihat saldo stok</a>
                        @izin('barang.kelola')
                            <a href="{{ route('mutasi-stok-barang.create', ['barang_id' => $barang->id]) }}" class="button button-primary">Catat mutasi stok</a>
                        @endizin
                    </div>
                @endif
            </section>
        </div>
    </div>
@endsection
