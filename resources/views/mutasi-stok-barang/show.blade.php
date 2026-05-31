@extends('layouts.app')

@section('title', 'Detail Mutasi Stok - NUSA')

@section('content')
    @php
        $perubahan = (float) $mutasiStokBarang->jumlah_perubahan;
        $satuan = $mutasiStokBarang->barang->satuanBarang->nama;
    @endphp

    <div class="page-header">
        <div>
            <p class="eyebrow">Sarana Prasarana</p>
            <h1 class="page-title">Detail mutasi stok</h1>
        </div>

        <div class="actions">
            <a href="{{ route('mutasi-stok-barang.index') }}" class="button button-muted">Kembali</a>
            @izin('barang.kelola')
                <a href="{{ route('mutasi-stok-barang.create', ['barang_id' => $mutasiStokBarang->barang_id]) }}" class="button button-primary">Catat mutasi baru</a>
            @endizin
        </div>
    </div>

    @if (session('berhasil'))
        <div class="alert">{{ session('berhasil') }}</div>
    @endif

    <div class="detail-shell">
        <aside class="panel panel-pad">
            <div class="detail-profile">
                <div class="avatar avatar-lg">MS</div>
                <h2>{{ $mutasiStokBarang->barang->nama }}</h2>
                <p>{{ $mutasiStokBarang->barang->kode }}</p>

                <div class="actions" style="justify-content: center; margin-top: 16px;">
                    <span class="badge {{ $perubahan > 0 ? 'badge-active' : 'badge-inactive' }}">{{ $mutasiStokBarang->labelJenis() }}</span>
                </div>
            </div>
        </aside>

        <div class="section-stack">
            <section class="panel panel-pad">
                <h2 class="panel-title">Informasi Mutasi</h2>

                <dl class="detail-grid">
                    <div class="detail-item">
                        <dt>Tanggal</dt>
                        <dd>{{ $mutasiStokBarang->tanggal_mutasi->locale('id')->translatedFormat('d F Y') }}</dd>
                    </div>
                    <div class="detail-item">
                        <dt>Kategori mutasi</dt>
                        <dd>{{ $mutasiStokBarang->labelKategori() }}</dd>
                    </div>
                    <div class="detail-item">
                        <dt>Lokasi</dt>
                        <dd>{{ $mutasiStokBarang->lokasiBarang->nama }}</dd>
                    </div>
                    <div class="detail-item">
                        <dt>Referensi</dt>
                        <dd>{{ $mutasiStokBarang->referensi ?: '-' }}</dd>
                    </div>
                    <div class="detail-item">
                        <dt>Perubahan saldo</dt>
                        <dd><strong>{{ $perubahan > 0 ? '+' : '' }}{{ number_format($perubahan, 2, ',', '.') }} {{ $satuan }}</strong></dd>
                    </div>
                    <div class="detail-item">
                        <dt>Saldo sebelum</dt>
                        <dd>{{ number_format((float) $mutasiStokBarang->saldo_sebelum, 2, ',', '.') }} {{ $satuan }}</dd>
                    </div>
                    <div class="detail-item">
                        <dt>Saldo sesudah</dt>
                        <dd>{{ number_format((float) $mutasiStokBarang->saldo_sesudah, 2, ',', '.') }} {{ $satuan }}</dd>
                    </div>
                    <div class="detail-item">
                        <dt>Dicatat oleh</dt>
                        <dd>{{ $mutasiStokBarang->dibuatOleh?->nama ?: 'Sistem' }}</dd>
                    </div>
                    <div class="detail-item span-2">
                        <dt>Keterangan</dt>
                        <dd style="white-space: pre-line;">{{ $mutasiStokBarang->keterangan ?: '-' }}</dd>
                    </div>
                </dl>
            </section>

            <section class="panel panel-pad">
                <h2 class="panel-title">Jejak Audit</h2>
                <p class="help-text" style="margin-top: 8px;">Riwayat mutasi disimpan sebagai catatan tetap. Jika terdapat kesalahan pencatatan, buat transaksi penyesuaian stok agar perubahan tetap dapat ditelusuri.</p>
            </section>
        </div>
    </div>
@endsection
