@extends('layouts.app')

@section('title', e($dokumenHumas->judul).' - Dokumen Humas')

@section('content')
    <div class="page-header">
        <div>
            <p class="eyebrow">Humas / {{ \App\Models\DokumenHumas::KATEGORI[$dokumenHumas->kategori] ?? 'Dokumen' }}</p>
            <h1 class="page-title">{{ $dokumenHumas->judul }}</h1>
        </div>
        <div class="actions">
            <a href="{{ route('dokumen-humas.index') }}" class="button button-muted">Kembali</a>
            <a href="{{ route('dokumen-humas.unduh', $dokumenHumas) }}" class="button button-primary">Unduh berkas terbaru</a>
            @izin('dokumen_humas.kelola')
                <a href="{{ route('dokumen-humas.edit', $dokumenHumas) }}" class="button button-muted">Edit</a>
            @endizin
        </div>
    </div>

    @if (session('berhasil'))<div class="alert">{{ session('berhasil') }}</div>@endif
    @if ($mouTerhubung->isNotEmpty())<div class="actions" style="margin-bottom:20px"><span>MoU terkait:</span>@foreach ($mouTerhubung as $mou)<a href="{{ route('kemitraan-humas.mou.show', [$mou->mitra, $mou]) }}">{{ $mou->mitra->nama }} &middot; {{ $mou->judul }}</a>@endforeach</div>@endif

    <div class="detail-shell">
        <aside class="section-stack">
            <section class="panel panel-pad">
                <h2 class="panel-title">Status dan masa berlaku</h2>
                <dl class="quick-facts" style="margin-top:14px;">
                    <div><dt>Status arsip</dt><dd>{{ \App\Models\DokumenHumas::STATUS[$dokumenHumas->status] ?? $dokumenHumas->status }}</dd></div>
                    <div><dt>Masa berlaku</dt><dd>{{ $dokumenHumas->statusMasaBerlaku() }}</dd></div>
                    <div><dt>Berlaku mulai</dt><dd>{{ $dokumenHumas->berlaku_mulai?->format('d M Y') ?? 'Tidak ditentukan' }}</dd></div>
                    <div><dt>Berlaku sampai</dt><dd>{{ $dokumenHumas->berlaku_sampai?->format('d M Y') ?? 'Tanpa batas' }}</dd></div>
                    <div><dt>Pengingat</dt><dd>{{ $dokumenHumas->ingatkan_hari_sebelum }} hari sebelumnya</dd></div>
                </dl>
                @izin('dokumen_humas.kelola')
                    <form action="{{ route('dokumen-humas.status', $dokumenHumas) }}" method="POST" style="margin-top:16px;">
                        @csrf @method('PATCH')
                        <input type="hidden" name="status" value="{{ $dokumenHumas->status === 'aktif' ? 'arsip' : 'aktif' }}">
                        <button type="submit" class="button button-muted button-full">{{ $dokumenHumas->status === 'aktif' ? 'Pindahkan ke arsip' : 'Aktifkan kembali' }}</button>
                    </form>
                @endizin
            </section>
            <section class="panel panel-pad">
                <h2 class="panel-title">Berkas terbaru</h2>
                <p style="margin:10px 0 0;font-weight:800;overflow-wrap:anywhere;">{{ $dokumenHumas->nama_file_asli }}</p>
                <p class="help-text">{{ $dokumenHumas->ukuranFileTampil() }} · {{ $dokumenHumas->updated_at?->format('d M Y H:i') }}</p>
                <a href="{{ route('dokumen-humas.unduh', $dokumenHumas) }}" class="button button-muted button-full" style="margin-top:14px;">Unduh</a>
            </section>
        </aside>

        <div class="section-stack">
            <section class="panel panel-pad">
                <h2 class="panel-title">Rincian</h2>
                <dl class="quick-facts" style="margin-top:14px;">
                    <div><dt>Nomor dokumen</dt><dd>{{ $dokumenHumas->nomor_dokumen ?: 'Tidak dicantumkan' }}</dd></div>
                    <div><dt>Dibuat oleh</dt><dd>{{ $dokumenHumas->pembuat?->nama ?: 'Pengguna tidak tersedia' }}</dd></div>
                    <div><dt>Terakhir diperbarui oleh</dt><dd>{{ $dokumenHumas->pengubah?->nama ?: 'Pengguna tidak tersedia' }}</dd></div>
                </dl>
                @if ($dokumenHumas->deskripsi)
                    <div style="margin-top:16px;white-space:pre-line;">{{ $dokumenHumas->deskripsi }}</div>
                @endif
            </section>

            <section class="panel panel-pad">
                <h2 class="panel-title">Riwayat berkas</h2>
                <p class="help-text">Setiap revisi disimpan sebagai versi agar berkas sebelumnya tetap dapat ditelusuri.</p>
                <div class="table-wrap" style="margin-top:12px;">
                    <table class="data-table">
                        <thead><tr><th>Versi</th><th>Berkas</th><th>Diunggah</th><th>Catatan</th><th></th></tr></thead>
                        <tbody>
                            @forelse ($dokumenHumas->riwayat as $versi)
                                <tr>
                                    <td>v{{ $versi->versi }}{{ $loop->first ? ' · Terbaru' : '' }}</td>
                                    <td>{{ $versi->nama_file_asli }}<br><span class="help-text">{{ $versi->ukuranFileTampil() }}</span></td>
                                    <td>{{ $versi->diunggah_pada?->format('d M Y H:i') }}<br><span class="help-text">{{ $versi->pengunggah?->nama ?: 'Pengguna tidak tersedia' }}</span></td>
                                    <td>{{ $versi->catatan ?: '-' }}</td>
                                    <td><a class="button button-muted button-sm" href="{{ route('dokumen-humas.riwayat.unduh', [$dokumenHumas, $versi]) }}">Unduh</a></td>
                                </tr>
                            @empty
                                <tr><td colspan="5" class="empty-state">Belum ada riwayat berkas.</td></tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </section>
        </div>
    </div>
@endsection
