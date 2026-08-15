@extends('layouts.app')

@section('title', 'Detail Pengajuan Saya - NUSA')

@section('content')
    <div class="page-header">
        <div>
            <p class="eyebrow">Layanan Sarana Prasarana</p>
            <h1 class="page-title">Detail pengajuan saya</h1>
        </div>
        <a href="{{ route('pengajuan-barang-saya.index') }}" class="button button-muted">Kembali</a>
    </div>

    @if (session('berhasil'))<div class="alert">{{ session('berhasil') }}</div>@endif
    @if ($errors->any())<div class="alert alert-error">{{ $errors->first() }}</div>@endif

    <div class="detail-shell">
        <aside class="panel panel-pad">
            <p class="eyebrow">{{ $pengajuanBarang->nomor_pengajuan }}</p>
            <h2 class="panel-title" style="margin-top: 7px;">{{ $pengajuanBarang->barang->nama }}</h2>
            <div class="actions" style="margin-top: 15px;">
                <span class="badge {{ $pengajuanBarang->status === 'dipenuhi' ? 'badge-active' : ($pengajuanBarang->status === 'menunggu' ? 'badge-warning' : 'badge-muted') }}">{{ $pengajuanBarang->labelStatus() }}</span>
            </div>
            @if ($pengajuanBarang->masihMenunggu())
                <form action="{{ route('pengajuan-barang-saya.batalkan', $pengajuanBarang) }}" method="POST" style="margin-top: 22px;" onsubmit="return confirm('Batalkan pengajuan ini?')">
                    @csrf
                    @method('PATCH')
                    <button type="submit" class="button button-danger">Batalkan pengajuan</button>
                </form>
            @endif
        </aside>

        <section class="panel panel-pad">
            <h2 class="panel-title">Informasi pengajuan</h2>
            <dl class="detail-grid" style="margin-top: 16px;">
                <div class="detail-item"><dt>Jenis</dt><dd>{{ $pengajuanBarang->labelJenis() }}</dd></div>
                <div class="detail-item"><dt>Jumlah</dt><dd>{{ number_format((float) $pengajuanBarang->jumlah, 2, ',', '.') }} {{ $pengajuanBarang->barang->satuanBarang->nama }}</dd></div>
                <div class="detail-item"><dt>Tanggal pengajuan</dt><dd>{{ $pengajuanBarang->tanggal_pengajuan->locale('id')->translatedFormat('d F Y') }}</dd></div>
                <div class="detail-item"><dt>Tanggal dibutuhkan</dt><dd>{{ $pengajuanBarang->tanggal_dibutuhkan->locale('id')->translatedFormat('d F Y') }}</dd></div>
                @if ($pengajuanBarang->jenis_pengajuan === 'peminjaman')
                    <div class="detail-item"><dt>Rencana kembali</dt><dd>{{ $pengajuanBarang->rencana_kembali?->locale('id')->translatedFormat('d F Y') ?: '-' }}</dd></div>
                @endif
                <div class="detail-item span-2"><dt>Tujuan penggunaan</dt><dd style="white-space: pre-line;">{{ $pengajuanBarang->tujuan }}</dd></div>
                <div class="detail-item span-2"><dt>Catatan petugas</dt><dd style="white-space: pre-line;">{{ $pengajuanBarang->catatan_petugas ?: 'Belum ada catatan.' }}</dd></div>
                @if ($pengajuanBarang->peminjamanBarang)
                    <div class="detail-item"><dt>Nomor transaksi</dt><dd>{{ $pengajuanBarang->peminjamanBarang->nomor_peminjaman }}</dd></div>
                @endif
            </dl>
        </section>
    </div>
@endsection
