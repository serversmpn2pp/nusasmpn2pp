@extends('layouts.app')

@section('title', 'Batas Proses Pelanggaran - NUSA')

@section('content')
    <style>
        .deadline-values { display:flex; flex-wrap:wrap; gap:7px; }
        .deadline-value { background:#eef3f8; border-radius:6px; color:var(--primary-dark); font-size:12px; font-weight:800; padding:6px 8px; }
        .deadline-mobile-list { display:grid; gap:12px; }
    </style>

    <div class="page-header">
        <div><p class="eyebrow">Kesiswaan & BK</p><h1 class="page-title">Batas Proses Pelanggaran</h1><p class="page-subtitle">Tenggat pemeriksaan dan persetujuan ditetapkan secara terpisah untuk setiap tahun pelajaran.</p></div>
        <a href="{{ route('pusat-verifikasi-pelanggaran.index') }}" class="button button-muted">Pusat Verifikasi</a>
    </div>

    @if(session('berhasil'))<div class="alert">{{ session('berhasil') }}</div>@endif

    <section class="panel panel-pad" style="margin-bottom:20px">
        <h2 class="panel-title">Cara penerapan</h2>
        <p class="help-text" style="margin-bottom:0">Batas disalin ke laporan ketika memasuki suatu tahap. Mengubah pengaturan tidak menggeser tenggat laporan yang sudah berjalan.</p>
    </section>

    <section class="panel">
        <div class="desktop-only table-wrap"><table class="employee-table"><thead><tr><th>Tahun pelajaran</th><th>Batas proses</th><th>Pengingat</th><th>Terakhir diperbarui</th><th class="text-right">Aksi</th></tr></thead><tbody>
            @forelse($daftarTahunPelajaran as $tahun)
                @php $pengaturan=$tahun->pengaturanBatasProsesPelanggaran; @endphp
                <tr><td><p class="person-name">{{ $tahun->nama }}</p><span class="badge {{ $tahun->aktif?'badge-active':'badge-muted' }}">{{ $tahun->aktif?'Aktif':'Tidak aktif' }}</span></td><td><div class="deadline-values"><span class="deadline-value">BK {{ $pengaturan->batas_hari_pemeriksaan_bk }} hari</span><span class="deadline-value">Persetujuan {{ $pengaturan->batas_hari_persetujuan }} hari</span><span class="deadline-value">Musyawarah {{ $pengaturan->batas_hari_musyawarah }} hari</span></div></td><td><p class="person-name">{{ $pengaturan->notifikasi_pengingat_aktif?$pengaturan->pengingat_hari_sebelum_batas.' hari sebelumnya':'Nonaktif' }}</p><p class="person-meta">Terlambat: {{ $pengaturan->notifikasi_terlambat_aktif?'aktif':'nonaktif' }}</p></td><td><p>{{ $pengaturan->exists?($pengaturan->updated_at?->format('d/m/Y H:i')??'-'):'Menggunakan nilai bawaan' }}</p><p class="person-meta">{{ $pengaturan->diperbaruiOlehPengguna?->nama }}</p></td><td><div class="actions" style="justify-content:flex-end"><a href="{{ route('pengaturan-batas-proses-pelanggaran.edit',$tahun) }}" class="button button-dark button-sm">Atur</a></div></td></tr>
            @empty<tr><td colspan="5" class="empty-state">Belum ada tahun pelajaran. Tambahkan tahun pelajaran terlebih dahulu.</td></tr>@endforelse
        </tbody></table></div>

        <div class="mobile-only mobile-list deadline-mobile-list">
            @forelse($daftarTahunPelajaran as $tahun)
                @php $pengaturan=$tahun->pengaturanBatasProsesPelanggaran; @endphp
                <article class="mobile-card"><div class="mobile-card-head"><div><p class="person-name">{{ $tahun->nama }}</p><p class="person-meta">{{ $tahun->aktif?'Tahun pelajaran aktif':'Tidak aktif' }}</p></div><span class="badge {{ $tahun->aktif?'badge-active':'badge-muted' }}">{{ $tahun->aktif?'Aktif':'Arsip' }}</span></div><div class="deadline-values" style="margin-top:13px"><span class="deadline-value">BK {{ $pengaturan->batas_hari_pemeriksaan_bk }} hari</span><span class="deadline-value">Persetujuan {{ $pengaturan->batas_hari_persetujuan }} hari</span><span class="deadline-value">Musyawarah {{ $pengaturan->batas_hari_musyawarah }} hari</span></div><p class="person-meta" style="margin-top:12px">Pengingat {{ $pengaturan->notifikasi_pengingat_aktif?$pengaturan->pengingat_hari_sebelum_batas.' hari sebelum batas':'nonaktif' }}</p><a href="{{ route('pengaturan-batas-proses-pelanggaran.edit',$tahun) }}" class="button button-dark button-full" style="margin-top:12px">Atur batas</a></article>
            @empty<div class="empty-state">Belum ada tahun pelajaran.</div>@endforelse
        </div>
    </section>

    @if($daftarTahunPelajaran->hasPages())<nav class="pagination-simple"><span>Halaman {{ $daftarTahunPelajaran->currentPage() }} dari {{ $daftarTahunPelajaran->lastPage() }}</span><div class="actions">@if($daftarTahunPelajaran->onFirstPage())<span class="button button-muted">Sebelumnya</span>@else<a href="{{ $daftarTahunPelajaran->previousPageUrl() }}" class="button button-muted">Sebelumnya</a>@endif @if($daftarTahunPelajaran->hasMorePages())<a href="{{ $daftarTahunPelajaran->nextPageUrl() }}" class="button button-muted">Berikutnya</a>@else<span class="button button-muted">Berikutnya</span>@endif</div></nav>@endif
@endsection
