@extends('layouts.app')

@section('title', 'Detail Peminjaman Barang - NUSA')

@section('content')
    <div class="page-header">
        <div>
            <p class="eyebrow">Sarana Prasarana</p>
            <h1 class="page-title">Detail peminjaman barang</h1>
        </div>

        <div class="actions">
            <a href="{{ route('peminjaman-barang.index') }}" class="button button-muted">Kembali</a>
            @izin('barang.peminjaman_kelola')
                @if ($peminjamanBarang->status !== 'selesai')
                    <a href="{{ route('pengembalian-barang.create', $peminjamanBarang) }}" class="button button-primary">Catat pengembalian</a>
                @endif
            @endizin
        </div>
    </div>

    @if (session('berhasil'))
        <div class="alert">{{ session('berhasil') }}</div>
    @endif

    <div class="detail-shell">
        <aside class="panel panel-pad">
            <div class="detail-profile">
                <div class="avatar avatar-lg">{{ $peminjamanBarang->jenis_peminjam === 'siswa' ? 'SW' : 'PG' }}</div>
                <h2>{{ $peminjamanBarang->namaPeminjam() }}</h2>
                <p>{{ $peminjamanBarang->identitasPeminjam() }}</p>

                <div class="actions" style="justify-content: center; margin-top: 16px;">
                    <span class="badge {{ $peminjamanBarang->status === 'selesai' ? 'badge-active' : 'badge-inactive' }}">{{ $peminjamanBarang->labelStatus() }}</span>
                </div>
            </div>
        </aside>

        <div class="section-stack">
            <section class="panel panel-pad">
                <h2 class="panel-title">Informasi Transaksi</h2>

                <dl class="detail-grid" style="margin-top: 16px;">
                    <div class="detail-item">
                        <dt>Nomor transaksi</dt>
                        <dd>{{ $peminjamanBarang->nomor_peminjaman }}</dd>
                    </div>
                    <div class="detail-item">
                        <dt>Tanggal peminjaman</dt>
                        <dd>{{ $peminjamanBarang->tanggal_peminjaman->locale('id')->translatedFormat('d F Y') }}</dd>
                    </div>
                    <div class="detail-item">
                        <dt>Rencana kembali</dt>
                        <dd>{{ $peminjamanBarang->rencana_kembali?->locale('id')->translatedFormat('d F Y') ?: '-' }}</dd>
                    </div>
                    <div class="detail-item">
                        <dt>Input peminjam</dt>
                        <dd>{{ ucfirst($peminjamanBarang->cara_input_peminjam) }}</dd>
                    </div>
                    <div class="detail-item">
                        <dt>Dicatat oleh</dt>
                        <dd>{{ $peminjamanBarang->dibuatOleh?->nama ?: 'Sistem' }}</dd>
                    </div>
                    <div class="detail-item span-2">
                        <dt>Catatan</dt>
                        <dd style="white-space: pre-line;">{{ $peminjamanBarang->catatan ?: '-' }}</dd>
                    </div>
                </dl>
            </section>
        </div>
    </div>

    <section class="panel" style="margin-top: 24px;">
        <div class="panel-pad">
            <h2 class="panel-title">Daftar Barang</h2>
        </div>

        <div class="table-wrap">
            <table class="employee-table">
                <thead>
                    <tr>
                        <th>Barang</th>
                        <th>Lokasi asal</th>
                        <th>Jumlah</th>
                        <th>Dikembalikan</th>
                        <th>Sisa</th>
                        <th>Status</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach ($peminjamanBarang->detailPeminjamanBarang as $detail)
                        @php
                            $satuan = $detail->tipe_pengelolaan === 'aset_individual' ? 'unit' : $detail->barang->satuanBarang->nama;
                        @endphp
                        <tr>
                            <td>
                                <p class="person-name">{{ $detail->barang->nama }}</p>
                                <p class="person-meta">{{ $detail->unitBarang?->kode_inventaris ?: $detail->barang->kode }}</p>
                            </td>
                            <td>{{ $detail->lokasiBarang?->nama ?: '-' }}</td>
                            <td>{{ number_format((float) $detail->jumlah, 2, ',', '.') }} {{ $satuan }}</td>
                            <td>{{ number_format((float) $detail->jumlah_dikembalikan, 2, ',', '.') }} {{ $satuan }}</td>
                            <td>{{ number_format($detail->jumlahBelumDikembalikan(), 2, ',', '.') }} {{ $satuan }}</td>
                            <td>
                                @if (! $detail->wajib_dikembalikan)
                                    <span class="badge badge-active">Habis pakai</span>
                                @elseif ($detail->jumlahBelumDikembalikan() <= 0)
                                    <span class="badge badge-active">Sudah kembali</span>
                                @else
                                    <span class="badge badge-inactive">Belum lengkap</span>
                                @endif
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    </section>

    <section class="panel" style="margin-top: 24px;">
        <div class="panel-pad">
            <h2 class="panel-title">Riwayat Pengembalian</h2>
        </div>

        <div class="table-wrap">
            <table class="employee-table">
                <thead>
                    <tr>
                        <th>Nomor</th>
                        <th>Tanggal</th>
                        <th>Barang</th>
                        <th>Dicatat oleh</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($peminjamanBarang->pengembalianBarang->sortByDesc('id') as $pengembalian)
                        <tr>
                            <td><strong>{{ $pengembalian->nomor_pengembalian }}</strong></td>
                            <td>{{ $pengembalian->tanggal_pengembalian->locale('id')->translatedFormat('d M Y') }}</td>
                            <td>
                                @foreach ($pengembalian->detailPengembalianBarang as $detailPengembalian)
                                    @php
                                        $detailPinjam = $detailPengembalian->detailPeminjamanBarang;
                                        $satuan = $detailPinjam->tipe_pengelolaan === 'aset_individual' ? 'unit' : $detailPinjam->barang->satuanBarang->nama;
                                    @endphp
                                    <p>{{ $detailPinjam->barang->nama }}: {{ number_format((float) $detailPengembalian->jumlah, 2, ',', '.') }} {{ $satuan }}</p>
                                @endforeach
                            </td>
                            <td>{{ $pengembalian->dibuatOleh?->nama ?: 'Sistem' }}</td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="4" class="empty-state">Belum ada pengembalian yang dicatat.</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </section>
@endsection
