@extends('layouts.app')

@section('title', 'Detail Lokasi Barang - NUSA')

@section('content')
    <div class="page-header">
        <div>
            <p class="eyebrow">Sarana Prasarana</p>
            <h1 class="page-title">Detail lokasi barang</h1>
        </div>

        <div class="actions">
            <a href="{{ route('lokasi-barang.index') }}" class="button button-muted">Kembali</a>
            @izin('barang.kelola')
                <a href="{{ route('lokasi-barang.edit', $lokasiBarang) }}" class="button button-dark">Edit</a>
            @endizin
        </div>
    </div>

    @if (session('berhasil'))
        <div class="alert">{{ session('berhasil') }}</div>
    @endif

    <div class="detail-shell">
        <aside class="panel panel-pad">
            <div class="detail-profile">
                <div class="avatar avatar-lg">LB</div>
                <h2>{{ $lokasiBarang->nama }}</h2>
                <p>{{ $lokasiBarang->kode }}</p>

                <div style="margin-top: 16px;">
                    @if ($lokasiBarang->aktif)
                        <span class="badge badge-active">Aktif</span>
                    @else
                        <span class="badge badge-inactive">Nonaktif</span>
                    @endif
                </div>
            </div>

            @izin('barang.kelola')
                @if ($lokasiBarang->aktif)
                    <form action="{{ route('lokasi-barang.destroy', $lokasiBarang) }}" method="POST" style="margin-top: 24px;" onsubmit="return confirm('Nonaktifkan lokasi barang ini?')">
                        @csrf
                        @method('DELETE')
                        <button type="submit" class="button button-danger button-full">Nonaktifkan</button>
                    </form>
                @endif
            @endizin
        </aside>

        <div class="section-stack">
            <section class="panel panel-pad">
                <h2 class="panel-title">Informasi Lokasi</h2>

                <dl class="detail-grid">
                    <div class="detail-item">
                        <dt>Nama lokasi</dt>
                        <dd>{{ $lokasiBarang->nama }}</dd>
                    </div>
                    <div class="detail-item">
                        <dt>Kode lokasi</dt>
                        <dd>{{ $lokasiBarang->kode }}</dd>
                    </div>
                    <div class="detail-item">
                        <dt>Jenis lokasi</dt>
                        <dd>{{ $lokasiBarang->labelJenis() }}</dd>
                    </div>
                    <div class="detail-item">
                        <dt>Jenis barang tersimpan</dt>
                        <dd>{{ $lokasiBarang->barang_sebagai_penyimpanan_count }}</dd>
                    </div>
                    <div class="detail-item span-2">
                        <dt>Penanggung jawab</dt>
                        <dd>{{ $lokasiBarang->penanggungJawab?->nama_lengkap ?: '-' }}</dd>
                    </div>
                    <div class="detail-item span-2">
                        <dt>Deskripsi</dt>
                        <dd style="white-space: pre-line;">{{ $lokasiBarang->deskripsi ?: '-' }}</dd>
                    </div>
                </dl>
            </section>

            <section class="panel panel-pad">
                <h2 class="panel-title">Penggunaan</h2>
                <p class="help-text" style="margin-top: 8px;">Lokasi menjadi acuan tempat penyimpanan awal barang. Pada tahap transaksi, perpindahan barang antar lokasi akan dicatat sebagai riwayat.</p>
            </section>
        </div>
    </div>
@endsection
