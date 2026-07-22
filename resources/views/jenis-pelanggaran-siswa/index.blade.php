@extends('layouts.app')

@section('title', 'Jenis Pelanggaran & Poin - NUSA')

@section('content')
    <div class="page-header">
        <div><p class="eyebrow">Pengaturan Pembinaan</p><h1 class="page-title">Jenis Pelanggaran & Poin</h1><p class="page-subtitle">Bobot baru berlaku untuk laporan berikutnya; riwayat lama tetap memakai bobot saat kejadian.</p></div>
        <a href="{{ route('jenis-pelanggaran-siswa.create') }}" class="button button-primary">Tambah</a>
    </div>
    @if (session('berhasil'))<div class="alert">{{ session('berhasil') }}</div>@endif

    <form method="GET" class="panel panel-pad" style="margin-bottom: 20px;">
        <div class="form-grid">
            <div class="field"><label for="kata_kunci">Cari</label><input id="kata_kunci" name="kata_kunci" value="{{ $kataKunci }}" class="input" placeholder="Kode atau nama pelanggaran"></div>
            <div class="field"><label for="tingkat">Tingkat</label><select id="tingkat" name="tingkat" class="select"><option value="semua">Semua</option>@foreach (\App\Models\LaporanPembinaanSiswa::DAFTAR_TINGKAT as $kode => $label)<option value="{{ $kode }}" @selected($tingkat === $kode)>{{ $label }}</option>@endforeach</select></div>
        </div>
        <div class="actions" style="justify-content:flex-end;margin-top:12px;"><a href="{{ route('jenis-pelanggaran-siswa.index') }}" class="button button-muted">Reset</a><button class="button button-dark">Terapkan</button></div>
    </form>

    <section class="panel">
        <div class="desktop-only table-wrap">
            <table class="employee-table"><thead><tr><th>Kode</th><th>Jenis Pelanggaran</th><th>Kategori</th><th>Tingkat</th><th>Poin</th><th>Status</th><th class="text-right">Aksi</th></tr></thead>
                <tbody>@forelse($jenisPelanggaran as $jenis)<tr><td><strong>{{ $jenis->kode }}</strong></td><td>{{ $jenis->nama }}</td><td>{{ $jenis->kategoriPembinaanSiswa?->nama ?: '-' }}</td><td><span class="badge {{ $jenis->tingkat === 'berat' ? 'badge-danger' : ($jenis->tingkat === 'sedang' ? 'badge-warning' : 'badge-active') }}">{{ str($jenis->tingkat)->headline() }}</span></td><td><strong>{{ $jenis->poin }}</strong></td><td><span class="badge {{ $jenis->aktif ? 'badge-active' : 'badge-inactive' }}">{{ $jenis->aktif ? 'Aktif' : 'Nonaktif' }}</span></td><td><div class="actions" style="justify-content:flex-end"><a href="{{ route('jenis-pelanggaran-siswa.edit',$jenis) }}" class="button button-dark button-sm">Edit</a>@if($jenis->aktif)<form method="POST" action="{{ route('jenis-pelanggaran-siswa.destroy',$jenis) }}">@csrf @method('DELETE')<button class="button button-danger button-sm">Nonaktifkan</button></form>@endif</div></td></tr>@empty<tr><td colspan="7" class="empty-state">Belum ada jenis pelanggaran.</td></tr>@endforelse</tbody>
            </table>
        </div>
        <div class="mobile-only mobile-list">@foreach($jenisPelanggaran as $jenis)<article class="mobile-card"><div class="mobile-card-head"><div><p class="person-name">{{ $jenis->kode }} · {{ $jenis->nama }}</p><p class="person-meta">{{ $jenis->kategoriPembinaanSiswa?->nama ?: '-' }}</p></div><span class="badge badge-warning">{{ $jenis->poin }} poin</span></div><a href="{{ route('jenis-pelanggaran-siswa.edit',$jenis) }}" class="button button-dark button-sm" style="margin-top:12px">Edit</a></article>@endforeach</div>
    </section>
    @if($jenisPelanggaran->hasPages())<nav class="pagination-simple"><span>Halaman {{ $jenisPelanggaran->currentPage() }} dari {{ $jenisPelanggaran->lastPage() }}</span><div class="actions">@if($jenisPelanggaran->onFirstPage())<span class="button button-muted" aria-disabled="true">Sebelumnya</span>@else<a class="button button-muted" href="{{ $jenisPelanggaran->previousPageUrl() }}">Sebelumnya</a>@endif @if($jenisPelanggaran->hasMorePages())<a class="button button-muted" href="{{ $jenisPelanggaran->nextPageUrl() }}">Berikutnya</a>@else<span class="button button-muted" aria-disabled="true">Berikutnya</span>@endif</div></nav>@endif
@endsection
