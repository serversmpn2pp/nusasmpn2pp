@extends('layouts.app')

@section('title', 'Detail Unit Aset - NUSA')

@section('content')
    <div class="page-header">
        <div>
            <p class="eyebrow">Sarana Prasarana</p>
            <h1 class="page-title">Detail unit aset</h1>
        </div>

        <div class="actions">
            <a href="{{ route('unit-barang.index') }}" class="button button-muted">Kembali</a>
            <a href="{{ route('label-barcode-inventaris.index', ['unit_barang_id' => [$unitBarang->id]]) }}" class="button button-muted">Cetak label</a>
            @izin('barang.kelola')
                <a href="{{ route('unit-barang.edit', $unitBarang) }}" class="button button-dark">Edit</a>
            @endizin
        </div>
    </div>

    @if (session('berhasil'))
        <div class="alert">{{ session('berhasil') }}</div>
    @endif

    <div class="detail-shell">
        <aside class="panel panel-pad">
            <div class="detail-profile">
                <div class="avatar avatar-lg">UA</div>
                <h2>{{ $unitBarang->barang->nama }}</h2>
                <p>{{ $unitBarang->kode_inventaris }}</p>

                <div class="actions" style="justify-content: center; margin-top: 16px;">
                    <span class="badge {{ $unitBarang->status_unit === 'tersedia' ? 'badge-active' : 'badge-inactive' }}">{{ $unitBarang->labelStatus() }}</span>
                    @if ($unitBarang->aktif)
                        <span class="badge badge-active">Aktif</span>
                    @else
                        <span class="badge badge-inactive">Nonaktif</span>
                    @endif
                </div>
            </div>

            @izin('barang.kelola')
                @if ($unitBarang->aktif)
                    <form action="{{ route('unit-barang.destroy', $unitBarang) }}" method="POST" style="margin-top: 24px;" onsubmit="return confirm('Nonaktifkan unit aset ini?')">
                        @csrf
                        @method('DELETE')
                        <button type="submit" class="button button-danger button-full">Nonaktifkan</button>
                    </form>
                @endif
            @endizin
        </aside>

        <div class="section-stack">
            <section class="panel panel-pad">
                <h2 class="panel-title">Identitas Aset</h2>

                <dl class="detail-grid">
                    <div class="detail-item">
                        <dt>Kode inventaris</dt>
                        <dd>{{ $unitBarang->kode_inventaris }}</dd>
                    </div>
                    <div class="detail-item">
                        <dt>Nomor unit</dt>
                        <dd>{{ $unitBarang->nomor_unit }}</dd>
                    </div>
                    <div class="detail-item">
                        <dt>Barang</dt>
                        <dd>{{ $unitBarang->barang->nama }}</dd>
                    </div>
                    <div class="detail-item">
                        <dt>Kategori</dt>
                        <dd>{{ $unitBarang->barang->kategoriBarang->nama }}</dd>
                    </div>
                    <div class="detail-item">
                        <dt>Nomor seri</dt>
                        <dd>{{ $unitBarang->nomor_seri ?: '-' }}</dd>
                    </div>
                    <div class="detail-item">
                        <dt>Lokasi saat ini</dt>
                        <dd>{{ $unitBarang->lokasiBarang?->nama ?: '-' }}</dd>
                    </div>
                    <div class="detail-item">
                        <dt>Kondisi</dt>
                        <dd>{{ $unitBarang->labelKondisi() }}</dd>
                    </div>
                    <div class="detail-item">
                        <dt>Status unit</dt>
                        <dd>{{ $unitBarang->labelStatus() }}</dd>
                    </div>
                </dl>
            </section>

            <section class="panel panel-pad">
                <h2 class="panel-title">Perolehan dan Catatan</h2>

                <dl class="detail-grid">
                    <div class="detail-item">
                        <dt>Tanggal perolehan</dt>
                        <dd>{{ $unitBarang->tanggal_perolehan?->locale('id')->translatedFormat('d F Y') ?: '-' }}</dd>
                    </div>
                    <div class="detail-item">
                        <dt>Sumber perolehan</dt>
                        <dd>{{ $unitBarang->sumber_perolehan ?: '-' }}</dd>
                    </div>
                    <div class="detail-item">
                        <dt>Harga perolehan</dt>
                        <dd>{{ $unitBarang->harga_perolehan !== null ? 'Rp ' . number_format((float) $unitBarang->harga_perolehan, 0, ',', '.') : '-' }}</dd>
                    </div>
                    <div class="detail-item span-2">
                        <dt>Keterangan</dt>
                        <dd style="white-space: pre-line;">{{ $unitBarang->keterangan ?: '-' }}</dd>
                    </div>
                </dl>
            </section>
        </div>
    </div>
@endsection
