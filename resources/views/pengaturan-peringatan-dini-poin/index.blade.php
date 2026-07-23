@extends('layouts.app')

@section('title', 'Pengaturan Peringatan Dini - NUSA')

@section('content')
    <style>
        .detail-list{display:grid;gap:8px}
        .detail-list>div{align-items:center;border-bottom:1px solid var(--border);display:flex;gap:12px;justify-content:space-between;padding-bottom:8px}
        .detail-list>div:last-child{border-bottom:0;padding-bottom:0}
        .detail-list span{color:var(--muted);font-size:13px}
        .detail-list strong{text-align:right}
    </style>

    <div class="page-header">
        <div>
            <p class="eyebrow">Pengaturan Kesiswaan</p>
            <h1 class="page-title">Peringatan Dini Poin</h1>
            <p class="page-subtitle">Batas deteksi dapat berbeda untuk setiap tahun pelajaran.</p>
        </div>
    </div>

    @if(session('berhasil'))
        <div class="alert">{{ session('berhasil') }}</div>
    @endif

    <section class="panel">
        <div class="desktop-only table-wrap">
            <table class="employee-table" style="min-width:920px">
                <thead>
                    <tr>
                        <th>Tahun pelajaran</th>
                        <th>Status</th>
                        <th>Mendekati sanksi</th>
                        <th>Pelanggaran berulang</th>
                        <th>Keterlambatan berulang</th>
                        <th>Notifikasi</th>
                        <th class="text-right">Aksi</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($daftarTahunPelajaran as $tahun)
                        @php
                            $item = $tahun->pengaturanPeringatanDiniPoin;
                            $persentase = $item?->persentase_mendekati_ambang ?? 80;
                            $pelanggaranJumlah = $item?->jumlah_pelanggaran_berulang ?? 3;
                            $pelanggaranHari = $item?->periode_pelanggaran_hari ?? 30;
                            $terlambatJumlah = $item?->jumlah_keterlambatan_berulang ?? 3;
                            $terlambatHari = $item?->periode_keterlambatan_hari ?? 30;
                        @endphp
                        <tr>
                            <td>
                                <p class="person-name">{{ $tahun->nama }}</p>
                                <p class="person-meta">{{ $tahun->aktif ? 'Tahun pelajaran aktif' : 'Arsip' }}</p>
                            </td>
                            <td><span class="badge {{ ! $item || $item->aktif ? 'badge-active' : 'badge-muted' }}">{{ ! $item || $item->aktif ? 'Aktif' : 'Nonaktif' }}</span></td>
                            <td><strong>{{ $persentase }}%</strong> dari ambang berikutnya</td>
                            <td>{{ $pelanggaranJumlah }} kejadian / {{ $pelanggaranHari }} hari</td>
                            <td>{{ $terlambatJumlah }} kali / {{ $terlambatHari }} hari</td>
                            <td><span class="badge {{ ! $item || $item->notifikasi_aktif ? 'badge-active' : 'badge-muted' }}">{{ ! $item || $item->notifikasi_aktif ? 'Dikirim' : 'Tidak dikirim' }}</span></td>
                            <td class="text-right"><a class="button button-dark button-sm" href="{{ route('pengaturan-peringatan-dini-poin.edit', $tahun) }}">Atur</a></td>
                        </tr>
                    @empty
                        <tr><td colspan="7" class="empty-state">Belum ada tahun pelajaran.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        <div class="mobile-only mobile-list">
            @forelse($daftarTahunPelajaran as $tahun)
                @php $item = $tahun->pengaturanPeringatanDiniPoin; @endphp
                <article class="mobile-card">
                    <div class="mobile-card-head">
                        <div>
                            <p class="person-name">{{ $tahun->nama }}</p>
                            <p class="person-meta">{{ $tahun->aktif ? 'Tahun aktif' : 'Arsip' }}</p>
                        </div>
                        <span class="badge {{ ! $item || $item->aktif ? 'badge-active' : 'badge-muted' }}">{{ ! $item || $item->aktif ? 'Aktif' : 'Nonaktif' }}</span>
                    </div>
                    <div class="detail-list" style="margin-top:12px">
                        <div><span>Mendekati sanksi</span><strong>{{ $item?->persentase_mendekati_ambang ?? 80 }}%</strong></div>
                        <div><span>Pelanggaran</span><strong>{{ $item?->jumlah_pelanggaran_berulang ?? 3 }} / {{ $item?->periode_pelanggaran_hari ?? 30 }} hari</strong></div>
                        <div><span>Keterlambatan</span><strong>{{ $item?->jumlah_keterlambatan_berulang ?? 3 }} / {{ $item?->periode_keterlambatan_hari ?? 30 }} hari</strong></div>
                    </div>
                    <a class="button button-dark button-full" style="margin-top:14px" href="{{ route('pengaturan-peringatan-dini-poin.edit', $tahun) }}">Atur peringatan</a>
                </article>
            @empty
                <div class="empty-state">Belum ada tahun pelajaran.</div>
            @endforelse
        </div>
    </section>

    <div style="margin-top:18px">{{ $daftarTahunPelajaran->links() }}</div>
@endsection
