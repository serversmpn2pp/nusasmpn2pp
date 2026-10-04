@extends('layouts.app')

@section('title', 'Pusat Dokumen Humas - NUSA')

@section('content')
    <style>
        .humas-summary { display:grid; grid-template-columns:repeat(3,minmax(0,1fr)); gap:12px; margin-bottom:20px; }
        .humas-filter { display:grid; grid-template-columns:minmax(200px,1fr) minmax(170px,.7fr) minmax(150px,.6fr) auto; gap:12px; align-items:end; }
        .humas-table { width:100%; border-collapse:collapse; }
        .humas-table th,.humas-table td { border-bottom:1px solid var(--line); padding:12px 10px; text-align:left; vertical-align:top; }
        .humas-table th { color:var(--muted); font-size:.8rem; font-weight:800; }
        .humas-table tr:last-child td { border-bottom:0; }
        .humas-title { color:var(--primary-dark); font-weight:800; }
        .humas-row-actions { display:flex; flex-wrap:wrap; gap:8px; justify-content:flex-end; }
        .humas-expiry { display:block; margin-top:4px; color:var(--muted); font-size:.8rem; }
        @media(max-width:800px) { .humas-filter { grid-template-columns:repeat(2,minmax(0,1fr)); } }
        @media(max-width:560px) { .humas-summary,.humas-filter { grid-template-columns:1fr; } .humas-table th:nth-child(3),.humas-table td:nth-child(3) { display:none; } }
    </style>

    <div class="page-header">
        <div>
            <p class="eyebrow">Humas</p>
            <h1 class="page-title">Pusat Dokumen Humas</h1>
            <p class="help-text">Arsip surat, notulen, program, kemitraan, publikasi, dan dokumen sekolah lainnya.</p>
        </div>
        @izin('dokumen_humas.kelola')
            <a href="{{ route('dokumen-humas.create') }}" class="button button-primary">Tambah dokumen</a>
        @endizin
    </div>

    @if (session('berhasil'))
        <div class="alert">{{ session('berhasil') }}</div>
    @endif

    <div class="humas-summary">
        <article class="panel stat active"><p class="stat-label">Dokumen aktif</p><p class="stat-value">{{ number_format($jumlahAktif, 0, ',', '.') }}</p></article>
        <article class="panel stat inactive"><p class="stat-label">Segera berakhir, 30 hari</p><p class="stat-value">{{ number_format($jumlahSegeraBerakhir, 0, ',', '.') }}</p></article>
        <article class="panel stat"><p class="stat-label">Kedaluwarsa</p><p class="stat-value">{{ number_format($jumlahKedaluwarsa, 0, ',', '.') }}</p></article>
    </div>

    <form action="{{ route('dokumen-humas.index') }}" method="GET" class="panel panel-pad" style="margin-bottom:18px;">
        <div class="humas-filter">
            <div class="field">
                <label for="kata_kunci">Cari dokumen</label>
                <input id="kata_kunci" name="kata_kunci" type="search" class="input" value="{{ $filter['kata_kunci'] ?? '' }}" placeholder="Judul, nomor, atau keterangan">
            </div>
            <div class="field">
                <label for="kategori">Kategori</label>
                <select id="kategori" name="kategori" class="select">
                    <option value="">Semua kategori</option>
                    @foreach ($kategori as $kode => $label)
                        <option value="{{ $kode }}" @selected(($filter['kategori'] ?? '') === $kode)>{{ $label }}</option>
                    @endforeach
                </select>
            </div>
            <div class="field">
                <label for="status">Status arsip</label>
                <select id="status" name="status" class="select">
                    <option value="">Semua status</option>
                    @foreach ($status as $kode => $label)
                        <option value="{{ $kode }}" @selected(($filter['status'] ?? '') === $kode)>{{ $label }}</option>
                    @endforeach
                </select>
            </div>
            <div class="actions">
                <button type="submit" class="button button-dark">Tampilkan</button>
                @if ($filter)
                    <a href="{{ route('dokumen-humas.index') }}" class="button button-muted">Reset</a>
                @endif
            </div>
        </div>
    </form>

    <section class="panel panel-pad">
        @if ($dokumen->isEmpty())
            <div class="empty-state">Belum ada dokumen yang sesuai. Tambahkan arsip pertama untuk mulai mengelola dokumen Humas.</div>
        @else
            <div class="table-wrap">
                <table class="humas-table">
                    <thead>
                        <tr><th>Dokumen</th><th>Kategori</th><th>Nomor</th><th>Masa berlaku</th><th>Status</th><th></th></tr>
                    </thead>
                    <tbody>
                        @foreach ($dokumen as $item)
                            @php
                                $masaBerlaku = $item->statusMasaBerlaku();
                                $badgeMasa = match ($masaBerlaku) {
                                    'Kedaluwarsa' => 'badge-danger',
                                    'Segera berakhir' => 'badge-inactive',
                                    'Masih berlaku' => 'badge-active',
                                    default => 'badge-muted',
                                };
                            @endphp
                            <tr>
                                <td>
                                    <a class="humas-title" href="{{ route('dokumen-humas.show', $item) }}">{{ $item->judul }}</a>
                                    <span class="humas-expiry">Diperbarui {{ $item->updated_at?->format('d M Y') }}</span>
                                </td>
                                <td>{{ $kategori[$item->kategori] ?? 'Lainnya' }}</td>
                                <td>{{ $item->nomor_dokumen ?: '-' }}</td>
                                <td>
                                    @if ($item->berlaku_sampai)
                                        <span class="badge {{ $badgeMasa }}">{{ $masaBerlaku }}</span>
                                        <span class="humas-expiry">s.d. {{ $item->berlaku_sampai->format('d M Y') }}</span>
                                    @else
                                        <span class="badge badge-muted">Tanpa batas</span>
                                    @endif
                                </td>
                                <td><span class="badge {{ $item->status === 'aktif' ? 'badge-active' : 'badge-muted' }}">{{ $status[$item->status] ?? $item->status }}</span></td>
                                <td><div class="humas-row-actions"><a class="button button-muted button-sm" href="{{ route('dokumen-humas.show', $item) }}">Buka</a></div></td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
            <div style="margin-top:16px;">{{ $dokumen->links() }}</div>
        @endif
    </section>
@endsection
